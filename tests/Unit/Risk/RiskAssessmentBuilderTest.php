<?php

declare(strict_types=1);

use App\Enums\AiDecisionValidity;
use App\Enums\DefconLevel;
use App\Enums\RiskLevel;
use App\Enums\Verdict;
use App\Services\Analysis\HeuristicReport;
use App\Services\OpenRouter\AiCallResult;
use App\Services\Risk\RiskAssessmentBuilder;

function heuristicReport(array $findings = [], int $score = 0): HeuristicReport
{
    return new HeuristicReport($findings, [], $score);
}

function aiResultValid(array $parsed): AiCallResult
{
    return new AiCallResult('deepseek/deepseek-chat:free', 1, AiDecisionValidity::Valid, '{}', $parsed, null, 10);
}

function aiResultRepaired(array $parsed): AiCallResult
{
    return new AiCallResult('qwen/qwen-2.5-72b-instruct:free', 2, AiDecisionValidity::Repaired, '{}', $parsed, null, 12);
}

function aiResultFailed(): AiCallResult
{
    return new AiCallResult('deepseek/deepseek-chat:free', 1, AiDecisionValidity::Failed, 'boom', null, null, 5, false, 1, 1, 500);
}

function aiParsed(string $verdict, int $threatScore, int $defcon, array $findings = []): array
{
    return [
        'verdict' => $verdict,
        'threat_score' => $threatScore,
        'defcon_level' => $defcon,
        'flags' => [],
        'findings' => $findings,
    ];
}

function buildAssessment(HeuristicReport $report, array $aiResults = []): array
{
    return app(RiskAssessmentBuilder::class)->build($report, $aiResults);
}

it('keeps the heuristic score and marks degraded without valid ai', function (): void {
    $assessment = buildAssessment(heuristicReport(score: 40));

    expect($assessment['security_score'])->toBe(40)
        ->and($assessment['is_degraded'])->toBeTrue()
        ->and($assessment['verdict'])->toBe(Verdict::Flagged);
});

it('merges to the maximum score when the ai scores higher', function (): void {
    $assessment = buildAssessment(heuristicReport(score: 30), [aiResultValid(aiParsed('flagged', 85, 2))]);

    expect($assessment['security_score'])->toBe(85)
        ->and($assessment['is_degraded'])->toBeFalse()
        ->and($assessment['verdict'])->toBe(Verdict::Hostile);
});

it('never reduces the score below the heuristic', function (): void {
    $assessment = buildAssessment(heuristicReport(score: 60), [aiResultValid(aiParsed('clear', 10, 5))]);

    expect($assessment['security_score'])->toBe(60)
        ->and($assessment['verdict'])->toBe(Verdict::Flagged);
});

it('flags hostile when the merged score reaches the hostile threshold', function (): void {
    $assessment = buildAssessment(heuristicReport(score: 0), [aiResultValid(aiParsed('hostile', 70, 2))]);

    expect($assessment['verdict'])->toBe(Verdict::Hostile)
        ->and($assessment['risk_level'])->toBe(RiskLevel::High);
});

it('flags hostile when any finding is critical', function (): void {
    $critical = ['category' => 'secret', 'severity' => 'critical', 'file_path' => '.env', 'description' => 'credencial'];

    $assessment = buildAssessment(heuristicReport(score: 10), [aiResultValid(aiParsed('clear', 5, 5, [$critical]))]);

    expect($assessment['verdict'])->toBe(Verdict::Hostile);
});

it('flags flagged when the merged score reaches the flagged threshold', function (): void {
    $assessment = buildAssessment(heuristicReport(score: 0), [aiResultValid(aiParsed('flagged', 35, 4))]);

    expect($assessment['verdict'])->toBe(Verdict::Flagged)
        ->and($assessment['risk_level'])->toBe(RiskLevel::Medium);
});

it('flags flagged when any finding is high', function (): void {
    $high = ['category' => 'eval', 'severity' => 'high', 'file_path' => 'app/A.php', 'description' => 'eval novo'];

    $assessment = buildAssessment(heuristicReport(score: 0), [aiResultValid(aiParsed('clear', 5, 5, [$high]))]);

    expect($assessment['verdict'])->toBe(Verdict::Flagged);
});

it('stays clear for low scores', function (): void {
    $assessment = buildAssessment(heuristicReport(score: 5), [aiResultValid(aiParsed('clear', 10, 5))]);

    expect($assessment['verdict'])->toBe(Verdict::Clear)
        ->and($assessment['risk_level'])->toBe(RiskLevel::Low);
});

it('maps the defcon bands from ninety down to five', function (): void {
    expect(buildAssessment(heuristicReport(score: 95))['defcon_level'])->toBe(DefconLevel::One)
        ->and(buildAssessment(heuristicReport(score: 85))['defcon_level'])->toBe(DefconLevel::Two)
        ->and(buildAssessment(heuristicReport(score: 60))['defcon_level'])->toBe(DefconLevel::Three)
        ->and(buildAssessment(heuristicReport(score: 40))['defcon_level'])->toBe(DefconLevel::Four)
        ->and(buildAssessment(heuristicReport(score: 20))['defcon_level'])->toBe(DefconLevel::Five);
});

it('caps the defcon to two when hostile with a critical finding', function (): void {
    $critical = ['category' => 'secret', 'severity' => 'critical', 'file_path' => '.env', 'description' => 'credencial'];

    $assessment = buildAssessment(heuristicReport(score: 60, findings: [$critical]));

    expect($assessment['verdict'])->toBe(Verdict::Hostile)
        ->and($assessment['defcon_level'])->toBe(DefconLevel::Two);
});

it('maps the risk level bands', function (): void {
    expect(buildAssessment(heuristicReport(score: 80))['risk_level'])->toBe(RiskLevel::High)
        ->and(buildAssessment(heuristicReport(score: 50))['risk_level'])->toBe(RiskLevel::Medium)
        ->and(buildAssessment(heuristicReport(score: 20))['risk_level'])->toBe(RiskLevel::Low);
});

it('dedupes the compliance checks by category and file path', function (): void {
    $heuristicFinding = ['category' => 'secret', 'severity' => 'critical', 'file_path' => '.env', 'description' => 'achado heurístico'];
    $aiFinding = ['category' => 'secret', 'severity' => 'critical', 'file_path' => '.env', 'description' => 'achado da IA'];
    $otherFinding = ['category' => 'eval', 'severity' => 'high', 'file_path' => 'app/B.php', 'description' => 'eval'];

    $assessment = buildAssessment(
        heuristicReport(findings: [$heuristicFinding], score: 40),
        [aiResultValid(aiParsed('hostile', 90, 1, [$aiFinding, $otherFinding]))],
    );

    expect($assessment['compliance_checks'])->toHaveCount(2)
        ->and($assessment['compliance_checks'][0])->toBe($heuristicFinding)
        ->and($assessment['compliance_checks'][1])->toBe($otherFinding);
});

it('adds a degradation note to the summary when no valid ai exists', function (): void {
    $assessment = buildAssessment(heuristicReport(score: 40), [aiResultFailed()]);

    expect($assessment['summary'])->toContain('degradada');
});

it('merges repaired ai decisions as valid evidence', function (): void {
    $assessment = buildAssessment(heuristicReport(score: 10), [aiResultRepaired(aiParsed('flagged', 55, 3))]);

    expect($assessment['is_degraded'])->toBeFalse()
        ->and($assessment['security_score'])->toBe(55)
        ->and($assessment['verdict'])->toBe(Verdict::Flagged);
});
