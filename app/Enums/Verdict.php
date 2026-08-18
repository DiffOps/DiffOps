<?php

namespace App\Enums;

/**
 * Tactical verdict of a risk assessment.
 */
enum Verdict: string
{
    case Clear = 'clear';
    case Flagged = 'flagged';
    case Hostile = 'hostile';

    public function label(): string
    {
        return match ($this) {
            self::Clear => 'CLEAR',
            self::Flagged => 'FLAGGED',
            self::Hostile => 'HOSTILE',
        };
    }
}
