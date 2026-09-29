<?php

namespace App\Domain\Printing\Pdf;

use App\Domain\Book\PageSequence;
use App\Domain\Printing\PageDoesNotFit;
use App\Domain\Printing\Size;

/**
 * Arma el PDF de páginas del libro a partir del PDF de origen: nuevo orden,
 * páginas quitadas o duplicadas, blancos y, opcionalmente, una capa encima
 * (folios) con una página por página del libro.
 */
final class PdfComposer
{
    public function compose(string $sourcePath, PageSequence $sequence, string $outputPath, ?string $overlayPath = null): void
    {
        $page = $this->pageSize($sourcePath);
        $pdf = new DeterministicFpdi($page);

        try {
            $pdf->setSourceFile($sourcePath);
            $templates = [];
            foreach ($sequence->items as $item) {
                if ($item->sourcePage !== null) {
                    $templates[$item->sourcePage] ??= $pdf->importPage($item->sourcePage);
                }
            }

            $overlays = [];
            if ($overlayPath !== null) {
                $overlayPages = $pdf->setSourceFile($overlayPath);

                if ($overlayPages !== $sequence->count()) {
                    throw new PageDoesNotFit("La capa de folios tiene {$overlayPages} páginas y el libro {$sequence->count()}.");
                }

                for ($p = 1; $p <= $overlayPages; $p++) {
                    $overlays[$p] = $pdf->importPage($p);
                }
            }

            foreach ($sequence->items as $index => $item) {
                $pdf->AddPage();

                if ($item->sourcePage !== null) {
                    $pdf->useTemplate($templates[$item->sourcePage], 0, 0, $page->widthMm, $page->heightMm);
                }

                if (isset($overlays[$index + 1])) {
                    $pdf->useTemplate($overlays[$index + 1], 0, 0, $page->widthMm, $page->heightMm);
                }
            }

            $pdf->Output('F', $outputPath);
        } finally {
            $pdf->cleanUp();
        }
    }

    public function pageSize(string $sourcePath): Size
    {
        $pdf = new DeterministicFpdi(Size::letterLandscape());

        try {
            $pdf->setSourceFile($sourcePath);
            $size = $pdf->getTemplateSize($pdf->importPage(1));
        } finally {
            $pdf->cleanUp();
        }

        if (! is_array($size) || ! is_numeric($size['width'] ?? null) || ! is_numeric($size['height'] ?? null)) {
            throw new PageDoesNotFit('No se pudo leer el tamaño de página del PDF.');
        }

        return new Size((float) $size['width'], (float) $size['height']);
    }

    public function pageCount(string $sourcePath): int
    {
        $pdf = new DeterministicFpdi(Size::letterLandscape());

        try {
            return $pdf->setSourceFile($sourcePath);
        } finally {
            $pdf->cleanUp();
        }
    }
}
