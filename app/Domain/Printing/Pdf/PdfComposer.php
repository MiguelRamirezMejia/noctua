<?php

namespace App\Domain\Printing\Pdf;

use App\Domain\Book\Design\BackgroundMode;
use App\Domain\Book\PageSequence;
use App\Domain\Printing\PageDoesNotFit;
use App\Domain\Printing\Size;

/**
 * Arma el PDF de páginas del libro a partir del PDF de origen: nuevo orden,
 * páginas quitadas o duplicadas y blancos. Por cada página apila:
 * capa «debajo» (fondos) → página de origen → tinte → capa «encima».
 */
final class PdfComposer
{
    public function compose(string $sourcePath, PageSequence $sequence, string $outputPath, ?string $overPath = null, ?string $underPath = null): void
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

            $under = $underPath === null ? [] : $this->importLayer($pdf, $underPath, $sequence);
            $over = $overPath === null ? [] : $this->importLayer($pdf, $overPath, $sequence);

            foreach ($sequence->items as $index => $item) {
                $pdf->AddPage();
                $position = $index + 1;

                if (isset($under[$position])) {
                    $pdf->useTemplate($under[$position], 0, 0, $page->widthMm, $page->heightMm);
                }

                if ($item->sourcePage !== null) {
                    $pdf->useTemplate($templates[$item->sourcePage], 0, 0, $page->widthMm, $page->heightMm);
                }

                $background = $item->design->background;
                if ($background !== null && $background->mode === BackgroundMode::Tint) {
                    $pdf->multiplyRect(0, 0, $page->widthMm, $page->heightMm, $background->color->rgb());
                }

                if (isset($over[$position])) {
                    $pdf->useTemplate($over[$position], 0, 0, $page->widthMm, $page->heightMm);
                }
            }

            $pdf->Output('F', $outputPath);
        } finally {
            $pdf->cleanUp();
        }
    }

    /**
     * @return array<int, string> posición en el libro => plantilla importada
     */
    private function importLayer(DeterministicFpdi $pdf, string $path, PageSequence $sequence): array
    {
        $pages = $pdf->setSourceFile($path);

        if ($pages !== $sequence->count()) {
            throw new PageDoesNotFit("La capa tiene {$pages} páginas y el libro {$sequence->count()}.");
        }

        $templates = [];
        for ($p = 1; $p <= $pages; $p++) {
            $templates[$p] = $pdf->importPage($p);
        }

        return $templates;
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
