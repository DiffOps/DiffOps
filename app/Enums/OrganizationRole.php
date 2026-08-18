<?php

namespace App\Enums;

/**
 * Tactical role of a user inside an organization.
 */
enum OrganizationRole: string
{
    case Commander = 'commander';
    case Operator = 'operator';

    public function label(): string
    {
        return match ($this) {
            self::Commander => 'Comandante',
            self::Operator => 'Operador',
        };
    }
}
