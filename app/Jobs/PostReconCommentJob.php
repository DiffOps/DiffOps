<?php

namespace App\Jobs;

use App\Models\PullRequest;
use App\Models\ReportComment;
use App\Models\Repository;
use App\Models\RiskAssessment;
use App\Services\GitHub\GitHubCommentsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Posts the tactical Recon Report comment on the pull request (F1).
 *
 * Guards, in order:
 *  - dedupe: at most one comment per risk assessment (report_comments unique).
 *  - repository resolved by business key (PullRequest is denormalized, D-U8-2).
 *  - repository.comment_on_pr toggle.
 *  - repository.installation_id present (required for the App installation token).
 *
 * Prerequisite (documented, not blocking tests): the GitHub App needs the
 * "Pull requests: Write" permission to post for real.
 */
class PostReconCommentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public RiskAssessment $assessment) {}

    public function handle(GitHubCommentsService $comments): void
    {
        // Dedupe: a single Recon Report comment per assessment.
        if (ReportComment::where('risk_assessment_id', $this->assessment->id)->exists()) {
            return;
        }

        $pr = $this->assessment->pullRequest;

        if (! $pr instanceof PullRequest) {
            return;
        }

        // PullRequest is denormalized (D-U8-2): match Repository by business key.
        $repository = Repository::where('organization_id', $pr->organization_id)
            ->where('github_repo_id', $pr->github_repo_id)
            ->first();

        if (! $repository instanceof Repository) {
            return;
        }

        if (! $repository->comment_on_pr) {
            return;
        }

        if (! $repository->installation_id) {
            // Cannot mint an installation token without the installation id.
            return;
        }

        $markdown = $comments->buildReconCommentMarkdown($this->assessment, $repository, $pr);

        [$owner, $repoName] = explode('/', $repository->full_name, 2) + [null, null];

        if ($owner === null || $repoName === null) {
            return;
        }

        $response = $comments->postComment(
            (int) $repository->installation_id,
            $owner,
            $repoName,
            (int) $pr->github_pr_number,
            $markdown
        );

        ReportComment::create([
            'risk_assessment_id' => $this->assessment->id,
            'github_comment_id' => (int) ($response['id'] ?? null),
        ]);
    }
}
