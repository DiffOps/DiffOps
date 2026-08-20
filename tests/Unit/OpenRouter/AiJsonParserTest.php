<?php

declare(strict_types=1);

use App\Enums\AiDecisionValidity;
use App\Services\OpenRouter\AiJsonParser;

function parseAiJson(string $raw): array
{
    return app(AiJsonParser::class)->parse($raw);
}

function validAiJson(array $overrides = []): array
{
    return array_merge([
        'verdict' => 'clear',
        'threat_score' => 10,
        'defcon_level' => 5,
        'flags' => [],
        'findings' => [],
    ], $overrides);
}

it('parses a strict valid json payload', function (): void {
    $result = parseAiJson(json_encode(validAiJson()));

    expect($result['validity'])->toBe(AiDecisionValidity::Valid)
        ->and($result['repaired'])->toBeFalse()
        ->and($result['data']['verdict'])->toBe('clear')
        ->and($result['data']['threat_score'])->toBe(10)
        ->and($result['data']['defcon_level'])->toBe(5);
});

it('rejects an invalid verdict', function (): void {
    $result = parseAiJson(json_encode(validAiJson(['verdict' => 'unknown'])));

    expect($result['validity'])->toBe(AiDecisionValidity::Failed)
        ->and($result['data'])->toBeNull();
});

it('rejects a threat score out of range', function (): void {
    expect(parseAiJson(json_encode(validAiJson(['threat_score' => 150])))['validity'])->toBe(AiDecisionValidity::Failed)
        ->and(parseAiJson(json_encode(validAiJson(['threat_score' => -5])))['validity'])->toBe(AiDecisionValidity::Failed)
        ->and(parseAiJson(json_encode(validAiJson(['threat_score' => 'abc'])))['validity'])->toBe(AiDecisionValidity::Failed);
});

it('rejects a defcon level out of range', function (): void {
    expect(parseAiJson(json_encode(validAiJson(['defcon_level' => 0])))['validity'])->toBe(AiDecisionValidity::Failed)
        ->and(parseAiJson(json_encode(validAiJson(['defcon_level' => 6])))['validity'])->toBe(AiDecisionValidity::Failed);
});

it('defaults missing flags to an empty list', function (): void {
    $payload = validAiJson();
    unset($payload['flags']);

    $result = parseAiJson(json_encode($payload));

    expect($result['validity'])->toBe(AiDecisionValidity::Valid)
        ->and($result['data']['flags'])->toBe([]);
});

it('defaults missing findings to an empty list', function (): void {
    $payload = validAiJson();
    unset($payload['findings']);

    $result = parseAiJson(json_encode($payload));

    expect($result['validity'])->toBe(AiDecisionValidity::Valid)
        ->and($result['data']['findings'])->toBe([]);
});

it('repairs json wrapped in code fences', function (): void {
    $raw = "```json\n".json_encode(validAiJson())."\n```";

    $result = parseAiJson($raw);

    expect($result['validity'])->toBe(AiDecisionValidity::Repaired)
        ->and($result['repaired'])->toBeTrue()
        ->and($result['data']['verdict'])->toBe('clear');
});

it('repairs json preceded by a markdown preamble', function (): void {
    $raw = "Aqui está a análise:\n".json_encode(validAiJson(['verdict' => 'flagged']));

    $result = parseAiJson($raw);

    expect($result['validity'])->toBe(AiDecisionValidity::Repaired)
        ->and($result['data']['verdict'])->toBe('flagged');
});

it('repairs json embedded in noisy text via balanced braces', function (): void {
    $raw = 'texto aleatório antes { "verdict": "hostile", "threat_score": 90, "defcon_level": 1, "flags": ["x"] } e depois';

    $result = parseAiJson($raw);

    expect($result['validity'])->toBe(AiDecisionValidity::Repaired)
        ->and($result['data']['verdict'])->toBe('hostile')
        ->and($result['data']['threat_score'])->toBe(90);
});

it('fails on plain non-json text', function (): void {
    $result = parseAiJson('o modelo alucinou e respondeu texto solto');

    expect($result['validity'])->toBe(AiDecisionValidity::Failed)
        ->and($result['data'])->toBeNull();
});

it('fails on an empty payload', function (): void {
    $result = parseAiJson('   ');

    expect($result['validity'])->toBe(AiDecisionValidity::Failed)
        ->and($result['data'])->toBeNull();
});

it('fails on broken json', function (): void {
    $result = parseAiJson('{"verdict":"clear","threat_score":10');

    expect($result['validity'])->toBe(AiDecisionValidity::Failed)
        ->and($result['data'])->toBeNull();
});
