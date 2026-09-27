<?php

namespace App\Domain\Imposition;

/**
 * Posición de media hoja. `page` es null cuando es una página de relleno en blanco.
 */
final readonly class Slot
{
    /**
     * @param  float  $creepMm  Desplazamiento del contenido hacia el lomo para compensar el creep.
     */
    public function __construct(
        public ?int $page,
        public float $creepMm = 0.0,
    ) {}

    public function isBlank(): bool
    {
        return $this->page === null;
    }
}
