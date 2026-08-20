<?php

namespace App\Services\Risk;

use App\Enums\AiDecisionValidity;
use App\Enums\DefconLevel;
use App\Enums\RiskLevel;
use App\Enums\Verdict;
use App\Services\Analysis\HeuristicReport;
use App\Services\OpenRouter\AiCallResult;

/**
 * Merges the heuristic report and the AI decisions into the final risk
 * assessment array (persisted by the AnalyzeIncursionJob):
 *
 *  - security_score = max(heuristic, valid AI threat scores) — AI never
 *    reduces the local anchor;
 *  - verdict: hostile (score >= 70 OR any critical finding), flagged
 *    (score >= 35 OR any high finding), otherwise clear;
 *  - defcon bands [90, 70, 50, 30] -> 1..5, capped to 2 when hostile
 *    with a critical finding;
 *  - risk_level: high >= 70, medium >= 35, else low;
 *  - compliance_checks: heuristic + AI findings deduped by category+file.
 */
class RiskAssessmentBuilder
{
    /**
     * @param  list<AiCallResult>  $aiResults
     * @return array<string, mixed>
     */
    public function build(HeuristicReport $report, array $aiResults): array
    {
        $validAi = array_values(array_filter(
            $aiResults,
            static fn (AiCallResult $result): bool => in_array(
                $result->validity,
                [AiDecisionValidity::Valid, AiDecisionValidity::Repaired],
                true,
            ) && is_array($result->parsed),
        ));

        $aiScore = 0;

        foreach ($validAi as $result) {
            $score = (int) ($result->parsed['threat_score'] ?? 0);
            $aiScore = max($aiScore, $score);
        }

        $securityScore = max($report->score, $aiScore);
        $isDegraded = $validAi === [];

        $findings = $report->findings;

        foreach ($validAi as $result) {
            foreach (($result->parsed['findings'] ?? []) as $finding) {
                if (is_array($finding)) {
                    $findings[] = $finding;
                }
            }
        }

        $hasCritical = $this->hasSeverity($findings, 'critical');
        $hasHigh = $this->hasSeverity($findings, 'high');

        $verdict = $this->verdict($securityScore, $hasCritical, $hasHigh);
        $defcon = $this->defcon($securityScore, $verdict, $hasCritical);
        $riskLevel = $this->riskLevel($securityScore);

        return [
            'verdict' => $verdict,
            'defcon_level' => $defcon,
            'security_score' => $securityScore,
            'risk_level' => $riskLevel,
            'summary' => $this->summary($securityScore, $verdict, $defcon, $isDegraded),
            'compliance_checks' => $this->dedupe($findings),
            'execution_time_ms' => null,
            'is_degraded' => $isDegraded,
        ];
    }

    private function verdict(int $score, bool $hasCritical, bool $hasHigh): Verdict
    {
        if ($score >= (int) config('analysis.verdict.hostile', 70) || $hasCritical) {
            return Verdict::Hostile;
        }

        if ($score >= (int) config('analysis.verdict.flagged', 35) || $hasHigh) {
            return Verdict::Flagged;
        }

        return Verdict::Clear;
    }

    private function defcon(int $score, Verdict $verdict, bool $hasCritical): DefconLevel
    {
        $level = DefconLevel::Five;

        foreach (config('analysis.defcon.bands', [90, 70, 50, 30]) as $index => $band) {
            if ($score >= (int) $band) {
                $level = DefconLevel::from($index + 1);

                break;
            }
        }

        if ($verdict === Verdict::Hostile && $hasCritical && $level->value > 2) {
            return DefconLevel::Two;
        }

        return $level;
    }

    private function riskLevel(int $score): RiskLevel
    {
        if ($score >= (int) config('analysis.risk.high', 70)) {
            return RiskLevel::High;
        }

        if ($score >= (int) config('analysis.risk.medium', 35)) {
            return RiskLevel::Medium;
        }

        return RiskLevel::Low;
    }

    private function summary(int $score, Verdict $verdict, DefconLevel $defcon, bool $isDegraded): string
    {
        $summary = sprintf(
            'Análise concluída: score %d, veredito %s, %s.',
            $score,
            $verdict->label(),
            $defcon->label(),
        );

        if ($isDegraded) {
            $summary .= ' Análise degradada: nenhuma resposta válida da IA foi obtida (score heurístico).';
        }

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     * @return list<array<string, mixed>>
     */
    private function dedupe(array $findings): array
    {
        $seen = [];

        return array_values(array_filter(
            $findings,
            static function (array $finding) use (&$seen): bool {
                if (! isset($finding['category'], $finding['file_path'])) {
                    return false;
                }

                $key = $finding['category'].'|'.$finding['file_path'];

                if (isset($seen[$key])) {
                    return false;
                }

                $seen[$key] = true;

                return true;
            },
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function hasSeverity(array $findings, string $severity): bool
    {
        foreach ($findings as $finding) {
            if (($finding['severity'] ?? null) === $severity) {
                return true;
            }
        }

        return false;
    }
}
