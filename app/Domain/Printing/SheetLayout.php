<?php

namespace App\Domain\Printing;

use App\Domain\Imposition\SheetSide;
use App\Domain\Imposition\Slot;

/**
 * Geometría 2-up: dos páginas a cada lado del doblez central, con el lomo de
 * cada página pegado al doblez y centradas en vertical. Cada página se recorta
 * a su media hoja para que nunca invada la contigua.
 */
final readonly class SheetLayout
{
    /** Tamaño de la página ya escalada sobre la hoja. */
    public Size $placed;

    public float $scale;

    public function __construct(
        public Size $sheet,
        public Size $page,
        public PrinterProfile $printer = new PrinterProfile,
        public PageFit $fit = PageFit::Actual,
    ) {
        $half = $sheet->widthMm / 2;

        $this->scale = match ($fit) {
            PageFit::Actual => 1.0,
            // El lomo va al doblez; la zona no imprimible solo afecta al corte, la cabeza y el pie.
            PageFit::Fit => min(
                ($half - $printer->unprintableMarginMm) / $page->widthMm,
                ($sheet->heightMm - 2 * $printer->unprintableMarginMm) / $page->heightMm,
            ),
            PageFit::Fill => max($half / $page->widthMm, $sheet->heightMm / $page->heightMm),
        };

        $this->placed = new Size($page->widthMm * $this->scale, $page->heightMm * $this->scale);

        $tolerance = 0.01;
        if ($fit === PageFit::Actual && ($page->widthMm > $half + $tolerance || $page->heightMm > $sheet->heightMm + $tolerance)) {
            throw new PageDoesNotFit(sprintf(
                'La página de %.1f × %.1f mm no cabe dos veces en la hoja de %.1f × %.1f mm. Usa «Ajustar» o «Llenar» para escalarla.',
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
        $offset = $this->printer->offsetFor($side);
        $y = ($this->sheet->heightMm - $this->placed->heightMm) / 2 + $offset->yMm;
        $w = $this->placed->widthMm;

        // El creep desplaza el contenido hacia el lomo: a la derecha en la página izquierda y viceversa.
        $placements = [
            $this->placement($sheetSide->left, $fold - $w + $sheetSide->left->creepMm, $y,
                new Clip($offset->xMm, $offset->yMm, $fold - $offset->xMm, $this->sheet->heightMm)),
            $this->placement($sheetSide->right, $fold - $sheetSide->right->creepMm, $y,
                new Clip($fold, $offset->yMm, $this->sheet->widthMm + $offset->xMm - $fold, $this->sheet->heightMm)),
        ];

        return array_values(array_filter($placements));
    }

    private function placement(Slot $slot, float $x, float $y, Clip $clip): ?Placement
    {
        if ($slot->page === null) {
            return null;
        }

        return new Placement($slot->page, $x, $y, $this->placed->widthMm, $this->placed->heightMm, $clip);
    }
}
