<?php

namespace App\Domain\Imposition;

/**
 * Parámetros de imposición de un libro en cuadernillos de hojas dobladas por la mitad.
 */
final readonly class ImpositionSpec
{
    public const int MIN_SHEETS_PER_SIGNATURE = 1;

    public const int MAX_SHEETS_PER_SIGNATURE = 8;

    /**
     * @param  int  $pageCount  Páginas del documento fuente.
     * @param  int  $sheetsPerSignature  Hojas por cuadernillo (n); cada cuadernillo tiene 4n páginas.
     * @param  bool  $shortLastSignature  Si el último cuadernillo usa solo las hojas necesarias en lugar de rellenar hasta 4n.
     * @param  float  $creepMmPerSheet  Desplazamiento hacia el lomo acumulado por cada hoja anidada.
     */
    public function __construct(
        public int $pageCount,
        public int $sheetsPerSignature = 4,
        public bool $shortLastSignature = false,
        public float $creepMmPerSheet = 0.0,
    ) {
        if ($pageCount < 1) {
            throw new InvalidImpositionSpec("El documento debe tener al menos una página (recibido: {$pageCount}).");
        }

        if ($sheetsPerSignature < self::MIN_SHEETS_PER_SIGNATURE || $sheetsPerSignature > self::MAX_SHEETS_PER_SIGNATURE) {
            throw new InvalidImpositionSpec(sprintf(
                'Las hojas por cuadernillo deben estar entre %d y %d (recibido: %d).',
                self::MIN_SHEETS_PER_SIGNATURE,
                self::MAX_SHEETS_PER_SIGNATURE,
                $sheetsPerSignature,
            ));
        }

        if ($creepMmPerSheet < 0) {
            throw new InvalidImpositionSpec("La compensación de creep no puede ser negativa (recibido: {$creepMmPerSheet}).");
        }
    }

    public function pagesPerSignature(): int
    {
        return 4 * $this->sheetsPerSignature;
    }
}
