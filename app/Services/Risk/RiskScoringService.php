<?php

declare(strict_types=1);

namespace App\Services\Risk;

use App\Enums\Verdict;
use App\Models\ContributorRisk;
use App\Models\Organization;
use App\Models\PullRequest;
use App\Models\RiskAssessment;

/**
 * Updates the tactical risk fingerprint of an author inside an organization.
 *
 * The fingerprint is a single row per (organization, author) holding the
 * cumulative PR count, the flagged/hostile counters, the running average of
 * findings per PR and a deterministic score bounded to 0-100.
 */
class RiskScoringService
{
    /**
     * Deterministic contributor score (0-100):
     * 60% hostile ratio + 30% flagged ratio + 10% findings density.
     */
    public function updateFingerprint(Organization $organization, PullRequest $pullRequest, RiskAssessment $assessment): void
    {
        $row = ContributorRisk::firstOrNew([
            'organization_id' => $organization->id,
            'author_username' => $pullRequest->author_username ?: 'unknown',
        ]);

        $previousTotal = $row->exists ? (int) $row->total_prs : 0;
        $newTotal = $previousTotal + 1;

        $findingsCount = count($assessment->compliance_checks ?? []);
        $previousAverage = $row->exists ? (float) $row->avg_findings_per_pr : 0.0;
        $newAverage = $previousTotal === 0
            ? (float) $findingsCount
            : (($previousAverage * $previousTotal) + $findingsCount) / $newTotal;

        $flagged = ($row->exists ? (int) $row->flagged_prs : 0) + ($assessment->verdict === Verdict::Flagged ? 1 : 0);
        $hostile = ($row->exists ? (int) $row->hostile_prs : 0) + ($assessment->verdict === Verdict::Hostile ? 1 : 0);

        $newAverage = round($newAverage, 2);

        $row->fill([
            'total_prs' => $newTotal,
            'flagged_prs' => $flagged,
            'hostile_prs' => $hostile,
            'avg_findings_per_pr' => $newAverage,
            'is_new_contributor' => $newTotal < 2,
            'score' => $this->score($newTotal, $flagged, $hostile, $newAverage),
        ]);

        $row->save();
    }

    private function score(int $total, int $flagged, int $hostile, float $averageFindings): int
    {
        $score = 60 * ($hostile / $total)
            + 30 * ($flagged / $total)
            + 10 * min($averageFindings / 10, 1);

        return (int) round($score);
    }
}
