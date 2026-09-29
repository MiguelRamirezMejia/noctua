<?php

namespace App\Domain\Book;

enum FolioPosition: string
{
    case BottomCenter = 'bottom-center';
    case BottomOuter = 'bottom-outer';
    case TopOuter = 'top-outer';

    public function label(): string
    {
        return match ($this) {
            self::BottomCenter => 'Abajo, centrado',
            self::BottomOuter => 'Abajo, esquina exterior',
            self::TopOuter => 'Arriba, esquina exterior',
        };
    }
}
