<?php

namespace App\Domain\Book;

/**
 * Numeración de páginas estampada sobre el libro. Tamaño y margen se expresan
 * tal como quedan impresos, después de escalar la página a la hoja.
 */
final readonly class FolioSettings
{
    /**
     * @param  int  $firstNumberedPage  Posición en el libro (1 = primera página) desde la que se numera.
     * @param  int  $startNumber  Número que lleva esa primera página numerada.
     */
    public function __construct(
        public bool $enabled = false,
        public int $firstNumberedPage = 1,
        public int $startNumber = 1,
        public FolioPosition $position = FolioPosition::BottomCenter,
        public string $font = 'Libertinus Serif',
        public float $sizePt = 9.0,
        public float $marginMm = 8.0,
    ) {
        if ($firstNumberedPage < 1 || $startNumber < 0) {
            throw new InvalidPageSequence('La numeración debe empezar en una página existente y con un número no negativo.');
        }

        if ($sizePt < 4 || $sizePt > 48 || $marginMm < 0 || $marginMm > 60) {
            throw new InvalidPageSequence('El tamaño del folio debe estar entre 4 y 48 pt y el margen entre 0 y 60 mm.');
        }
    }
}
