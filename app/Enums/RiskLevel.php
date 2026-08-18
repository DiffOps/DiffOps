<?php

namespace App\Enums;

/**
 * Risk level band of a risk assessment.
 */
enum RiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baixo',
            self::Medium => 'Médio',
            self::High => 'Alto',
        };
    }
}
