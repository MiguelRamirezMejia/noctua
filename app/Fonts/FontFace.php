<?php

namespace App\Fonts;

/**
 * Datos de una variante de fuente leídos del propio archivo.
 */
final readonly class FontFace
{
    public function __construct(
        public string $family,
        public int $weight,
        public bool $italic,
        public string $extension,
    ) {}
}
