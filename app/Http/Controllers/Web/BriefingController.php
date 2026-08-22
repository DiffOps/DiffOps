<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\AnalysisFinding;
use App\Models\PullRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BriefingController extends Controller
{
    /**
     * Display analytics briefing.
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $organization = $user->currentOrganization;

        $days = $request->integer('days', 30);
        $since = Carbon::now()->subDays($days);

        $analyses = Analysis::whereHas('pullRequest.repository', fn ($q) => $q->where('organization_id', $organization->id))
            ->where('verdict', '!=', 'pending')
            ->where('created_at', '>=', $since);

        // Verdict distribution
        $verdictDistribution = (clone $analyses)
            ->selectRaw('verdict, count(*) as count')
            ->groupBy('verdict')
            ->pluck('count', 'verdict')
            ->toArray();

        // Threat score histogram (buckets of 10)
        $threatHistogram = [];
        for ($i = 0; $i <= 100; $i += 10) {
            $min = $i;
            $max = $i + 9;
            $count = (clone $analyses)
                ->whereBetween('security_score', [$min, $max])
                ->count();
            $threatHistogram[] = ['range' => "{$min}-{$max}", 'count' => $count];
        }

        // DEFCON trend (daily average over period)
        $defconTrend = (clone $analyses)
            ->selectRaw('DATE(created_at) as date, AVG(defcon_level) as avg_defcon, AVG(execution_time_ms) as avg_time')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'avg_defcon' => round($row->avg_defcon, 1),
                'avg_execution_time_ms' => round($row->avg_time),
            ])
            ->toArray();

        // Findings by category
        $findingsByCategory = AnalysisFinding::whereHas('analysis.pullRequest.repository', fn ($q) => $q->where('organization_id', $organization->id))
            ->whereHas('analysis', fn ($q) => $q->where('created_at', '>=', $since))
            ->selectRaw('category, severity, count(*) as count')
            ->groupBy('category', 'severity')
            ->get()
            ->groupBy('category')
            ->map(fn ($group) => $group->groupBy('severity')->map(fn ($s) => $s->sum('count'))->toArray())
            ->toArray();

        // Top repos by incursion count
        $topRepos = PullRequest::whereHas('repository', fn ($q) => $q->where('organization_id', $organization->id))
            ->whereHas('analyses', fn ($q) => $q->where('created_at', '>=', $since))
            ->with('repository')
            ->get()
            ->groupBy('repository.full_name')
            ->map(fn ($group) => [
                'repo' => $group->first()->repository->full_name,
                'count' => $group->count(),
                'hostile' => $group->where('analyses.0.verdict', 'hostile')->count(),
                'flagged' => $group->where('analyses.0.verdict', 'flagged')->count(),
            ])
            ->sortByDesc('count')
            ->take(10)
            ->values()
            ->toArray();

        return Inertia::render('Briefing/Index', [
            'period' => ['days' => $days, 'since' => $since->toISOString()],
            'verdictDistribution' => $verdictDistribution,
            'threatHistogram' => $threatHistogram,
            'defconTrend' => $defconTrend,
            'findingsByCategory' => $findingsByCategory,
            'topRepos' => $topRepos,
        ]);
    }
}
