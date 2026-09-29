<?php

namespace App\Domain\Printing;

/**
 * Página del documento fuente colocada en la hoja, en mm desde la esquina superior
 * izquierda, recortada a su media hoja.
 */
final readonly class Placement
{
    public function __construct(
        public int $page,
        public float $xMm,
        public float $yMm,
        public float $widthMm,
        public float $heightMm,
        public Clip $clip,
    ) {}
}
