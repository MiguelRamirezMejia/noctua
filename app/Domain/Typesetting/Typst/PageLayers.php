<?php

namespace App\Domain\Typesetting\Typst;

use App\Domain\Book\ContentFrame;
use App\Domain\Book\Design\BackgroundMode;
use App\Domain\Book\FolioSettings;
use App\Domain\Book\PageItem;
use App\Domain\Book\PageSequence;
use App\Domain\Printing\Size;

/**
 * Genera con Typst las capas transparentes de cada página del libro:
 * «debajo» (fondos) y «encima» (textos, rectángulos y folios). Cada capa es un
 * PDF con una página por página del libro.
 */
final readonly class PageLayers
{
    public function __construct(
        private TypstRunner $typst,
        private string $template,
    ) {}

    public function needsUnder(PageSequence $sequence): bool
    {
        foreach ($sequence->items as $item) {
            if ($item->design->background?->mode === BackgroundMode::Behind) {
                return true;
            }
        }

        return false;
    }

    public function needsOver(PageSequence $sequence, FolioSettings $folios): bool
    {
        foreach ($sequence->items as $item) {
            if ($item->design->elements !== []) {
                return true;
            }
        }

        return $sequence->folios($folios) !== [];
    }

    /**
     * @param  'debajo'|'encima'  $layer
     * @param  float  $printScale  Escala de impresión: el folio se compensa para salir al tamaño pedido.
     * @param  list<string>  $fontPaths
     * @param  array<int, ContentFrame>  $frames  Posición => dónde va el contenido (márgenes del libro).
     */
    public function render(string $layer, PageSequence $sequence, FolioSettings $folios, Size $page, float $printScale, array $fontPaths, string $outputPath, array $frames = []): void
    {
        $workdir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'noctua-capas-'.bin2hex(random_bytes(6));
        mkdir($workdir);

        try {
            copy($this->template, $workdir.DIRECTORY_SEPARATOR.'capas.typ');
            file_put_contents($workdir.DIRECTORY_SEPARATOR.'data.json', json_encode(
                $this->data($sequence, $folios, $page, $printScale, $frames),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            ));

            $this->typst->compile($workdir, 'capas.typ', $outputPath, ['data' => '/data.json', 'capa' => $layer], $fontPaths);
        } finally {
            array_map(unlink(...), glob($workdir.DIRECTORY_SEPARATOR.'*') ?: []);
            rmdir($workdir);
        }
    }

    /**
     * @param  array<int, ContentFrame>  $frames
     * @return array<string, mixed>
     */
    private function data(PageSequence $sequence, FolioSettings $folios, Size $page, float $printScale, array $frames): array
    {
        $texts = $sequence->folios($folios);

        return [
            'width_mm' => $page->widthMm,
            'height_mm' => $page->heightMm,
            'folios' => [
                'font' => $folios->font,
                'size_pt' => $folios->sizePt / $printScale,
                'margin_mm' => $folios->marginMm / $printScale,
                'position' => $folios->position->value,
            ],
            'pages' => array_map(fn (PageItem $item, int $index): array => [
                ...$item->design->toArray(),
                'folio' => isset($texts[$index + 1]) ? $texts[$index + 1]->text : null,
                'frame' => self::frame($frames[$index + 1] ?? new ContentFrame(1.0, 0.0, 0.0)),
            ], $sequence->items, array_keys($sequence->items)),
        ];
    }

    /**
     * @return array{scale: float, x_mm: float, y_mm: float}
     */
    private static function frame(ContentFrame $frame): array
    {
        return ['scale' => $frame->scale, 'x_mm' => $frame->xMm, 'y_mm' => $frame->yMm];
    }
}
