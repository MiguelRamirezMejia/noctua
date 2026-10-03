<?php

namespace App\Domain\Book\Design;

/**
 * Fragmento de texto con estilo propio dentro de un cuadro. Los saltos de
 * línea se representan con "\n" dentro del texto.
 */
final readonly class TextSpan
{
    public function __construct(
        public string $text,
        public bool $bold = false,
        public bool $italic = false,
        /** null = color del cuadro. */
        public ?Color $color = null,
    ) {}
}
