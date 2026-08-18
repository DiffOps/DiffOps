<?php

namespace App\Enums;

/**
 * Change status of a single file inside a pull request diff.
 */
enum PrFileStatus: string
{
    case Added = 'added';
    case Modified = 'modified';
    case Removed = 'removed';
    case Renamed = 'renamed';

    public function label(): string
    {
        return match ($this) {
            self::Added => 'Adicionado',
            self::Modified => 'Modificado',
            self::Removed => 'Removido',
            self::Renamed => 'Renomeado',
        };
    }
}
