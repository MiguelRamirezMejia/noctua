<?php

namespace App\Domain\Printing;

/**
 * Rectángulo de recorte en mm desde la esquina superior izquierda de la hoja.
 */
final readonly class Clip
{
    public function __construct(
        public float $xMm,
        public float $yMm,
        public float $widthMm,
        public float $heightMm,
    ) {}
}
