<?php

namespace App\Enums;

/**
 * Lifecycle state of a pull request.
 */
enum PrState: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Merged = 'merged';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberta',
            self::Closed => 'Fechada',
            self::Merged => 'Mergeada',
        };
    }
}
