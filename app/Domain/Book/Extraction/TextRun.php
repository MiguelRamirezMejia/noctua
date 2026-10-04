<?php

namespace App\Domain\Book\Extraction;

/**
 * Trozo de una línea con un estilo uniforme, tal como viene del PDF.
 */
final readonly class TextRun
{
    public function __construct(
        public string $text,
        public bool $bold = false,
        public bool $italic = false,
        /** #rrggbb o null si se desconoce. */
        public ?string $color = null,
    ) {}
}
