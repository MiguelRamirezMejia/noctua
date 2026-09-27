<?php

namespace App\Domain\Printing;

/**
 * Corrección de registro en mm, en coordenadas de la cara impresa (x a la derecha, y hacia abajo).
 */
final readonly class Offset
{
    public function __construct(
        public float $xMm = 0.0,
        public float $yMm = 0.0,
    ) {}
}
