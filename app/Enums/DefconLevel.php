<?php

namespace App\Enums;

/**
 * DEFCON alert level (1 = most severe, 5 = peacetime).
 */
enum DefconLevel: int
{
    case One = 1;
    case Two = 2;
    case Three = 3;
    case Four = 4;
    case Five = 5;

    public function label(): string
    {
        return "DEFCON {$this->value}";
    }
}
