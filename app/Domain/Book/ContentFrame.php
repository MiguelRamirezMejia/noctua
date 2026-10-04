<?php

namespace App\Domain\Book;

/**
 * Transformación del contenido de una página: se escala y se coloca en
 * (x, y) mm desde la esquina superior izquierda de la página.
 */
final readonly class ContentFrame
{
    public function __construct(
        public float $scale,
        public float $xMm,
        public float $yMm,
    ) {}

    public function isIdentity(): bool
    {
        return $this->scale === 1.0 && $this->xMm === 0.0 && $this->yMm === 0.0;
    }
}
