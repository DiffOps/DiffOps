<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\PullRequest;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with HUD stats and realtime incursion feed.
     */
    public function index(): Response
    {
        $user = auth('supabase')->user();
        $organization = $user->currentOrganization;

        if ($organization === null) {
            // Usuário sem organização ainda: dashboard em estado vazio.
            return Inertia::render('Dashboard', [
                'stats' => [
                    'totalOpenPRs' => 0,
                    'avgThreatScore' => 0,
                    'currentDefcon' => 5,
                    'avgExecutionTimeMs' => 0,
                ],
                'incursions' => [],
                'realtime' => ['channel' => 'org:0:analyses'],
            ]);
        }

        // HUD Stats
        $totalOpenPRs = PullRequest::whereHas('repository', fn ($q) => $q->where('organization_id', $organization->id))
            ->where('state', 'open')
            ->count();

        $avgThreatScore = Analysis::whereHas('pullRequest.repository', fn ($q) => $q->where('organization_id', $organization->id))
            ->where('verdict', '!=', 'pending')
            ->avg('security_score');

        $currentDefcon = Analysis::whereHas('pullRequest.repository', fn ($q) => $q->where('organization_id', $organization->id))
            ->where('verdict', '!=', 'pending')
            ->max('defcon_level');

        $avgExecutionTimeMs = Analysis::whereHas('pullRequest.repository', fn ($q) => $q->where('organization_id', $organization->id))
            ->where('verdict', '!=', 'pending')
            ->avg('execution_time_ms');

        // Recent incursions for feed (latest 20)
        $recentIncursions = Analysis::with(['pullRequest.repository', 'pullRequest.author'])
            ->whereHas('pullRequest.repository', fn ($q) => $q->where('organization_id', $organization->id))
            ->where('verdict', '!=', 'pending')
            ->latest('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($analysis) => [
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

        return Inertia::render('Dashboard', [
            'stats' => [
                'totalOpenPRs' => $totalOpenPRs,
                'avgThreatScore' => $avgThreatScore ? round($avgThreatScore) : 0,
                'currentDefcon' => $currentDefcon ?? 5,
                'avgExecutionTimeMs' => $avgExecutionTimeMs ? round($avgExecutionTimeMs) : 0,
            ],
            'incursions' => $recentIncursions,
            'realtime' => [
                'channel' => "org:{$organization->id}:analyses",
            ],
        ]);
    }
}
