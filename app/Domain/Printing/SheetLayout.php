<?php

namespace App\Domain\Printing;

use App\Domain\Imposition\SheetSide;
use App\Domain\Imposition\Slot;

/**
 * Geometría 2-up: dos páginas a cada lado del doblez central, con el lomo de
 * cada página pegado al doblez y centradas en vertical.
 */
final readonly class SheetLayout
{
    public function __construct(
        public Size $sheet,
        public Size $page,
        public PrinterProfile $printer = new PrinterProfile,
    ) {
        $tolerance = 0.01;

        if ($page->widthMm > $sheet->widthMm / 2 + $tolerance || $page->heightMm > $sheet->heightMm + $tolerance) {
            throw new PageDoesNotFit(sprintf(
                'La página de %.1f × %.1f mm no cabe dos veces en la hoja de %.1f × %.1f mm.',
                $page->widthMm,
                $page->heightMm,
                $sheet->widthMm,
                $sheet->heightMm,
            ));
        }
    }

    public function foldXMm(Side $side): float
    {
        return $this->sheet->widthMm / 2 + $this->printer->offsetFor($side)->xMm;
    }

    /**
     * @return list<Placement>
     */
    public function place(SheetSide $sheetSide, Side $side): array
    {
        $fold = $this->foldXMm($side);
        $y = ($this->sheet->heightMm - $this->page->heightMm) / 2 + $this->printer->offsetFor($side)->yMm;

        // El creep desplaza el contenido hacia el lomo: a la derecha en la página izquierda y viceversa.
        $placements = [
            $this->placement($sheetSide->left, $fold - $this->page->widthMm + $sheetSide->left->creepMm, $y),
            $this->placement($sheetSide->right, $fold - $sheetSide->right->creepMm, $y),
        ];

        return array_values(array_filter($placements));
    }

    private function placement(Slot $slot, float $x, float $y): ?Placement
    {
        if ($slot->page === null) {
            return null;
        }

        return new Placement($slot->page, $x, $y, $this->page->widthMm, $this->page->heightMm);
    }
}
