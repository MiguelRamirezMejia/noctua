<?php

namespace App\Domain\Book;

/**
 * Número de página a estampar en una posición del libro.
 */
final readonly class Folio
{
    public function __construct(
        public int $position,
        public string $text,
    ) {}

    /** Las páginas impares del libro caen a la derecha del pliego. */
    public function isRecto(): bool
    {
        return $this->position % 2 === 1;
    }
}
