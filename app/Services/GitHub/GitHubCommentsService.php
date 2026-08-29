<?php

namespace App\Services\GitHub;

use App\Enums\Verdict;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\RiskAssessment;

/**
 * Builds and posts the tactical Recon Report comment on a GitHub pull request.
 *
 * Prerequisite (documented, not blocking tests): the GitHub App must have the
 * "Pull requests: Write" permission for the installation token to be allowed to
 * post. Tests use Http::fake, so no real GitHub App is required for the suite.
 */
class GitHubCommentsService
{
    public function __construct(private readonly GitHubApiClient $client) {}

    /**
     * Build the markdown body of the Recon Report comment.
     */
    public function buildReconCommentMarkdown(RiskAssessment $assessment, Repository $repository, PullRequest $pr): string
    {
        $verdict = $assessment->verdict ?? Verdict::Clear;

        $verdictEmoji = match ($verdict) {
            Verdict::Hostile => '🟥',
            Verdict::Flagged => '🟡',
            Verdict::Clear => '🟢',
        };

        $defcon = $assessment->defcon_level;
        $checks = $assessment->compliance_checks ?? [];

        $lines = [];
        $lines[] = '# 🛰️ DiffOps — Recon Report';
        $lines[] = '';
        $lines[] = "**Veredito:** {$verdictEmoji} {$verdict->label()}";
        $lines[] = "**DEFCON:** {$defcon->label()}";
        $lines[] = "**Ameaça / Score:** {$assessment->security_score}";
        $lines[] = '';
        $lines[] = '## Findings';

        if (empty($checks)) {
            $lines[] = '_Nenhum finding automático detectado._';
        } else {
            foreach ($checks as $check) {
                $severity = $check['severity'] ?? 'unknown';
                $category = $check['category'] ?? 'uncategorized';
                $filePath = $check['file_path'] ?? 'unknown';
                $description = $check['description'] ?? '';

                $lines[] = "- [{$severity}] **{$category}** — {$filePath}: {$description}";
            }
        }

        $lines[] = '';
        [$owner, $repoName] = explode('/', $repository->full_name, 2) + [null, null];
        $prUrl = "https://github.com/{$owner}/{$repoName}/pull/{$pr->github_pr_number}";
        $reportUrl = rtrim((string) config('app.url'), '/')."/incursions/{$assessment->id}";
        $lines[] = "🔗 PR: {$prUrl}";
        $lines[] = "📊 Relatório completo: {$reportUrl}";
        $lines[] = '';
        $lines[] = '_Comentário automático gerado por DiffOps._';

        return implode("\n", $lines);
    }

    /**
     * Post the markdown comment on the pull request issue.
     *
     * @return array<mixed> the GitHub API response (contains the comment id)
     */
    public function postComment(int $installationId, string $owner, string $repo, int $issueNumber, string $markdown): array
    {
        $path = "/repos/{$owner}/{$repo}/issues/{$issueNumber}/comments";

        return $this->client->post($path, ['body' => $markdown], $installationId);
    }
}
