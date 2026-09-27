<?php

namespace App\Domain\Imposition;

/**
 * Configuración de imposición de un libro, independiente de su número de páginas.
 */
final readonly class ImpositionSettings
{
    public function __construct(
        public int $sheetsPerSignature = 4,
        public bool $shortLastSignature = false,
        public float $creepMmPerSheet = 0.0,
    ) {}

    public function forPageCount(int $pageCount): ImpositionSpec
    {
        return new ImpositionSpec($pageCount, $this->sheetsPerSignature, $this->shortLastSignature, $this->creepMmPerSheet);
    }
}
