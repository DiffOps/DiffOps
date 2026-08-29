<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeIncursionJob;
use App\Models\ContributorRisk;
use App\Models\PullRequest;
use App\Models\Repository;
use App\Models\RiskAssessment as Analysis;
use Inertia\Inertia;
use Inertia\Response;

class IncursionController extends Controller
{
    /**
     * Display a listing of incursions (paginated).
     */
    public function index(): Response
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            // User without an active organization: show the empty state.
            return Inertia::render('Incursions/Index', [
                'incursions' => [],
            ]);
        }

        $registeredRepoIds = Repository::where('organization_id', $organization->id)
            ->pluck('github_repo_id');

        $incursions = Analysis::with(['pullRequest.author'])
            ->where('verdict', '!=', 'pending')
            ->whereHas('pullRequest', fn ($q) => $q
                ->where('organization_id', $organization->id)
                ->whereIn('github_repo_id', $registeredRepoIds))
            ->latest('created_at')
            ->paginate(20)
            ->through(fn ($analysis) => [
                'id' => $analysis->id,
                'timestamp' => $analysis->created_at->toISOString(),
                'repository' => optional($this->resolveRepository($analysis->pullRequest))->full_name ?? 'unknown',
                'prNumber' => $analysis->pullRequest->github_pr_number,
                'author' => [
                    'username' => $analysis->pullRequest->author->username ?? 'unknown',
                    'avatarUrl' => $analysis->pullRequest->author->avatar_url,
                ],
                'verdict' => $analysis->verdict,
                'threatScore' => $analysis->security_score,
                'defconLevel' => $analysis->defcon_level,
                'executionTimeMs' => $analysis->execution_time_ms,
                'status' => 'completed',
            ]);

        return Inertia::render('Incursions/Index', [
            'incursions' => $incursions,
        ]);
    }

    /**
     * Display the specified incursion (Recon Report detail).
     */
    public function show(Analysis $analysis): Response
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            abort(403);
        }

        // Ensure the analysis belongs to user's organization
        $repository = $this->resolveRepository($analysis->pullRequest);

        if ($repository->organization_id !== $organization->id) {
            abort(403);
        }

        $analysis->load([
            'pullRequest',
            'pullRequest.author',
            'findings',
        ]);

        // Get contributor risk fingerprint
        $riskFingerprint = null;
        if ($analysis->pullRequest->author) {
            $riskFingerprint = ContributorRisk::where('organization_id', $organization->id)
                ->where('author_username', $analysis->pullRequest->author->username)
                ->first();
        }

        return Inertia::render('Incursions/Show', [
            'analysis' => [
                'id' => $analysis->id,
                'timestamp' => $analysis->created_at->toISOString(),
                'repository' => $repository->full_name,
                'prNumber' => $analysis->pullRequest->github_pr_number,
                'headSha' => $analysis->head_sha,
                'author' => [
                    'username' => $analysis->pullRequest->author->username ?? 'unknown',
                    'avatarUrl' => $analysis->pullRequest->author->avatar_url,
                    'riskFingerprint' => $riskFingerprint ? [
                        'score' => $riskFingerprint->score,
                        'totalPrs' => $riskFingerprint->total_prs,
                        'flaggedPrs' => $riskFingerprint->flagged_prs,
                        'hostilePrs' => $riskFingerprint->hostile_prs,
                        'avgFindingsPerPr' => $riskFingerprint->avg_findings_per_pr,
                        'isNewContributor' => $riskFingerprint->is_new_contributor,
                    ] : null,
                ],
                'verdict' => $analysis->verdict,
                'threatScore' => $analysis->security_score,
                'defconLevel' => $analysis->defcon_level,
                'riskLevel' => $analysis->risk_level,
                'summary' => $analysis->summary,
                'executionTimeMs' => $analysis->execution_time_ms,
                'isDegraded' => $analysis->is_degraded,
                'complianceChecks' => $analysis->compliance_checks,
                'findings' => $analysis->findings->map(fn ($f) => [
                    'category' => $f->category,
                    'severity' => $f->severity,
                    'filePath' => $f->file_path,
                    'description' => $f->description,
                ])->toArray(),
            ],
            'repository' => [
                'id' => $repository->id,
                'commentOnPr' => $repository->comment_on_pr,
            ],
        ]);
    }

    /**
     * Re-scan an incursion (dispatch AnalyzeIncursionJob).
     */
    public function rescan(Analysis $analysis)
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            abort(403);
        }

        $repository = $this->resolveRepository($analysis->pullRequest);

        if ($repository->organization_id !== $organization->id) {
            abort(403);
        }

        // Delete existing assessment to force re-analysis
        $analysis->riskAssessment()->delete();
        $analysis->delete();

        // Re-dispatch the job
        AnalyzeIncursionJob::dispatch([
            'repository' => [
                'id' => $repository->github_repo_id,
            ],
            'number' => $analysis->pullRequest->github_pr_number,
            'pull_request' => [
                'head' => [
                    'sha' => $analysis->head_sha,
                ],
            ],
        ], $analysis->id);

        return back()->with('success', 'Re-scan iniciado. A análise será processada em breve.');
    }

    /**
     * Post Recon Report comment on GitHub PR (F1).
     */
    public function commentOnPr(Analysis $analysis)
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            abort(403);
        }

        $repository = $this->resolveRepository($analysis->pullRequest);

        if ($repository->organization_id !== $organization->id) {
            abort(403);
        }

        if (! $repository->comment_on_pr) {
            return back()->withErrors(['comment' => 'Comentários automáticos desativados para este repositório.']);
        }

        // F1: dispatch the Recon Comment job (implemented in a later commit).
        // PostReconCommentJob::dispatch($analysis);

        return back()->with('success', 'Solicitação de comentário enviada (F1 pendente).');
    }

    /**
     * Resolve the Repository for a pull request.
     *
     * PullRequest is denormalized (D-U8-2): there is no FK to repositories,
     * so we match on organization_id + github_repo_id — the same business key
     * used when the repository was registered.
     */
    private function resolveRepository(PullRequest $pullRequest): Repository
    {
        return Repository::where('organization_id', $pullRequest->organization_id)
            ->where('github_repo_id', $pullRequest->github_repo_id)
            ->firstOrFail();
    }
}
