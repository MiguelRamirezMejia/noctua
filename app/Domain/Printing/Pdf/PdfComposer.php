<?php

namespace App\Domain\Printing\Pdf;

use App\Domain\Book\ContentFrame;
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
    /**
     * @param  list<int>|null  $only  Posiciones del libro a incluir (p. ej. un pliego para la vista previa).
     * @param  array<int, string>  $textless  Página de origen => PDF de esa página sin su texto.
     * @param  array<int, ContentFrame>  $frames  Posición => dónde va la página original (márgenes del libro).
     */
    public function compose(string $sourcePath, PageSequence $sequence, string $outputPath, ?string $overPath = null, ?string $underPath = null, ?array $only = null, array $textless = [], array $frames = []): void
    {
        $page = $this->pageSize($sourcePath);
        $pdf = new DeterministicFpdi($page);

        try {
            $pdf->setSourceFile($sourcePath);
            $templates = [];
            foreach ($sequence->items as $index => $item) {
                if ($item->sourcePage !== null && ($only === null || in_array($index + 1, $only, true))) {
                    $templates[$item->sourcePage] ??= $pdf->importPage($item->sourcePage);
                }
            }

            $withoutText = [];
            foreach ($sequence->items as $index => $item) {
                $source = $item->sourcePage;
                if ($source !== null && $item->design->hideSourceText && isset($textless[$source]) && ($only === null || in_array($index + 1, $only, true))) {
                    if (! isset($withoutText[$source])) {
                        $pdf->setSourceFile($textless[$source]);
                        $withoutText[$source] = $pdf->importPage(1);
                    }
                }
            }

            $under = $underPath === null ? [] : $this->importLayer($pdf, $underPath, $sequence);
            $over = $overPath === null ? [] : $this->importLayer($pdf, $overPath, $sequence);

            foreach ($sequence->items as $index => $item) {
                $position = $index + 1;
                if ($only !== null && ! in_array($position, $only, true)) {
                    continue;
                }

                $pdf->AddPage();

                if (isset($under[$position])) {
                    $pdf->useTemplate($under[$position], 0, 0, $page->widthMm, $page->heightMm);
                }

                if ($item->sourcePage !== null) {
                    $template = $item->design->hideSourceText && isset($withoutText[$item->sourcePage])
                        ? $withoutText[$item->sourcePage]
                        : $templates[$item->sourcePage];
                    // Se encaja según la página original: la versión sin texto mide lo mismo.
                    [$x, $y, $width, $height] = self::fitInto(
                        $this->templateSize($pdf, $templates[$item->sourcePage]),
                        $page,
                        $frames[$position] ?? new ContentFrame(1.0, 0.0, 0.0),
                    );
                    $pdf->useTemplate($template, $x, $y, $width, $height);
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

    /**
     * Tamaño de página del libro: el más frecuente entre sus páginas. La
     * primera no sirve siempre: muchos PDF empiezan con una portada o una
     * miniatura de otro tamaño.
     */
    public function pageSize(string $sourcePath): Size
    {
        $pdf = new DeterministicFpdi(Size::letterLandscape());
        $counts = [];
        $sizes = [];

        try {
            $pages = $pdf->setSourceFile($sourcePath);
            for ($p = 1; $p <= $pages; $p++) {
                $size = $this->templateSize($pdf, $pdf->importPage($p));
                $key = sprintf('%.0fx%.0f', $size->widthMm * 2, $size->heightMm * 2);
                $sizes[$key] ??= $size;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        } finally {
            $pdf->cleanUp();
        }

        if ($counts === []) {
            throw new PageDoesNotFit('No se pudo leer el tamaño de página del PDF.');
        }

        // arsort conserva el orden de aparición en los empates: gana el primero.
        arsort($counts);

        return $sizes[array_key_first($counts)];
    }

    private function templateSize(DeterministicFpdi $pdf, string $template): Size
    {
        $size = $pdf->getTemplateSize($template);

        if (! is_array($size) || ! is_numeric($size['width'] ?? null) || ! is_numeric($size['height'] ?? null)) {
            throw new PageDoesNotFit('No se pudo leer el tamaño de página del PDF.');
        }

        return new Size((float) $size['width'], (float) $size['height']);
    }

    /**
     * Encaja una página de origen en la del libro sin deformarla: si su
     * tamaño es otro, se escala para caber y se centra.
     *
     * @return array{float, float, float, float} x, y, ancho y alto en mm
     */
    public static function fitInto(Size $source, Size $page, ContentFrame $frame): array
    {
        $width = $page->widthMm * $frame->scale;
        $height = $page->heightMm * $frame->scale;

        if ($source->equals($page)) {
            return [$frame->xMm, $frame->yMm, $width, $height];
        }

        $scale = min($width / $source->widthMm, $height / $source->heightMm);

        return [
            $frame->xMm + ($width - $source->widthMm * $scale) / 2,
            $frame->yMm + ($height - $source->heightMm * $scale) / 2,
            $source->widthMm * $scale,
            $source->heightMm * $scale,
        ];
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
