<?php

namespace App\Enums;

enum PublisherRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propietario',
            self::Editor => 'Editor',
        };
    }
}
