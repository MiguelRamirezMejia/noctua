<?php

namespace App\Domain\Book\Design;

/**
 * Posición y tamaño de un elemento en mm.
 */
final readonly class Box
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {
        if ($width <= 0 || $height <= 0 || $width > 2000 || $height > 2000) {
            throw new InvalidPageDesign('El tamaño de un elemento debe ser positivo y razonable.');
        }

        if (abs($x) > 2000 || abs($y) > 2000) {
            throw new InvalidPageDesign('El elemento está demasiado lejos de la página.');
        }
    }
}
