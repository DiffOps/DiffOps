<?php

namespace App\Services\OpenRouter;

use App\Enums\AiDecisionValidity;

/**
 * Validates and repairs the strict JSON contract of the model responses:
 *
 *  {"verdict": "clear|flagged|hostile", "threat_score": 0-100,
 *   "defcon_level": 1-5, "flags": ["string"], "findings": [...]}
 *
 * Repair order: code fences, markdown preamble, balanced-brace scan over
 * noisy text. Any unrecoverable payload is marked Failed.
 */
class AiJsonParser
{
    /**
     * @return array{validity: AiDecisionValidity, data: ?array<string, mixed>, repaired: bool}
     */
    public function parse(string $raw): array
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return ['validity' => AiDecisionValidity::Failed, 'data' => null, 'repaired' => false];
        }

        $data = json_decode($trimmed, true);
        $repaired = false;

        if (! is_array($data)) {
            $extracted = $this->extractJson($trimmed);

            if ($extracted === null) {
                return ['validity' => AiDecisionValidity::Failed, 'data' => null, 'repaired' => false];
            }

            $data = json_decode($extracted, true);

            if (! is_array($data)) {
                return ['validity' => AiDecisionValidity::Failed, 'data' => null, 'repaired' => false];
            }

            $repaired = true;
        }

        if (! $this->isValid($data)) {
            return ['validity' => AiDecisionValidity::Failed, 'data' => null, 'repaired' => $repaired];
        }

        $data['flags'] ??= [];
        $data['findings'] ??= [];

        return [
            'validity' => $repaired ? AiDecisionValidity::Repaired : AiDecisionValidity::Valid,
            'data' => $data,
            'repaired' => $repaired,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isValid(array $data): bool
    {
        if (! isset($data['verdict']) || ! in_array($data['verdict'], ['clear', 'flagged', 'hostile'], true)) {
            return false;
        }

        if (! isset($data['threat_score']) || ! is_numeric($data['threat_score'])) {
            return false;
        }

        $threatScore = (float) $data['threat_score'];

        if ($threatScore < 0 || $threatScore > 100) {
            return false;
        }

        if (! isset($data['defcon_level']) || ! is_numeric($data['defcon_level'])) {
            return false;
        }

        $defcon = (int) $data['defcon_level'];

        return $defcon >= 1 && $defcon <= 5;
    }

    private function extractJson(string $text): ?string
    {
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches) === 1) {
            return trim($matches[1]);
        }

        return $this->extractBalancedBraces($text);
    }

    private function extractBalancedBraces(string $text): ?string
    {
        $start = strpos($text, '{');

        if ($start === false) {
            return null;
        }

        $depth = 0;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($char === '{') {
                $depth++;
            }

            if ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
