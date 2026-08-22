<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Jobs\AnalyzeIncursionJob;
use Inertia\Inertia;
use Inertia\Response;

class IncursionController extends Controller
{
    /**
     * Display a listing of incursions (paginated).
     */
    public function index(): Response
    {
        $user = auth()->user();
        $organization = $user->currentOrganization;

        $incursions = Analysis::with(['pullRequest.repository', 'pullRequest.author'])
            ->whereHas('pullRequest.repository', fn ($q) => $q->where('organization_id', $organization->id))
            ->where('verdict', '!=', 'pending')
            ->latest('created_at')
            ->paginate(20)
            ->through(fn ($analysis) => [
                'id' => $analysis->id,
                'timestamp' => $analysis->created_at->toISOString(),
                'repository' => $analysis->pullRequest->repository->full_name,
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
        $user = auth()->user();
        $organization = $user->currentOrganization;

        // Ensure the analysis belongs to user's organization
        if ($analysis->pullRequest->repository->organization_id !== $organization->id) {
            abort(403);
        }

        $analysis->load([
            'pullRequest.repository',
            'pullRequest.author',
            'findings',
        ]);

        // Get contributor risk fingerprint
        $riskFingerprint = null;
        if ($analysis->pullRequest->author) {
            $riskFingerprint = \App\Models\ContributorRisk::where('organization_id', $organization->id)
                ->where('author_username', $analysis->pullRequest->author->username)
                ->first();
        }

        return Inertia::render('Incursions/Show', [
            'analysis' => [
                'id' => $analysis->id,
                'timestamp' => $analysis->created_at->toISOString(),
                'repository' => $analysis->pullRequest->repository->full_name,
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
                'id' => $analysis->pullRequest->repository->id,
                'commentOnPr' => $analysis->pullRequest->repository->comment_on_pr,
            ],
        ]);
    }

    /**
     * Re-scan an incursion (dispatch AnalyzeIncursionJob).
     */
    public function rescan(Analysis $analysis)
    {
        $user = auth()->user();
        $organization = $user->currentOrganization;

        if ($analysis->pullRequest->repository->organization_id !== $organization->id) {
            abort(403);
        }

        // Delete existing assessment to force re-analysis
        $analysis->riskAssessment()->delete();
        $analysis->delete();

        // Re-dispatch the job
        AnalyzeIncursionJob::dispatch([
            'repository' => [
                'id' => $analysis->pullRequest->repository->github_repo_id,
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
        $user = auth()->user();
        $organization = $user->currentOrganization;

        if ($analysis->pullRequest->repository->organization_id !== $organization->id) {
            abort(403);
        }

        if (!$analysis->pullRequest->repository->comment_on_pr) {
            return back()->withErrors(['comment' => 'Comentários automáticos desativados para este repositório.']);
        }

        // Dispatch job to post comment (F1 - to be implemented)
        // PostReconCommentJob::dispatch($analysis);

        return back()->with('success', 'Solicitação de comentário enviada (F1 pendente).');
    }
}