<?php

namespace App\Domain\Printing;

/**
 * Calibración de una impresora concreta. Pertenece a la impresora, no al libro.
 */
final readonly class PrinterProfile
{
    public function __construct(
        public Offset $frontOffset = new Offset,
        public Offset $backOffset = new Offset,
        public float $unprintableMarginMm = 5.0,
    ) {}

    public function offsetFor(Side $side): Offset
    {
        return $side === Side::Front ? $this->frontOffset : $this->backOffset;
    }
}
