<?php

namespace App\Domain\Imposition;

/**
 * Hoja física de un cuadernillo. `index` 1 es la hoja exterior.
 */
final readonly class Sheet
{
    public function __construct(
        public int $signature,
        public int $index,
        public SheetSide $front,
        public SheetSide $back,
    ) {}
}
