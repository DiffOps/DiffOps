<?php

namespace App\Enums;

/**
 * Validity state of a single AI model decision attempt.
 */
enum AiDecisionValidity: string
{
    case Valid = 'valid';
    case Repaired = 'repaired';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Válida',
            self::Repaired => 'Reparada',
            self::Failed => 'Falhou',
        };
    }
}
