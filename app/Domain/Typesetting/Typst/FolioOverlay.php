<?php

namespace App\Domain\Typesetting\Typst;

use App\Domain\Book\FolioSettings;
use App\Domain\Book\PageSequence;
use App\Domain\Printing\Size;

/**
 * Genera con Typst un PDF transparente con los folios, una página por página
 * del libro, para estamparlo encima de las páginas de origen.
 */
final readonly class FolioOverlay
{
    /**
     * @param  string  $template  Ruta a folios.typ.
     * @param  list<string>  $fontPaths
     */
    public function __construct(
        private TypstRunner $typst,
        private string $template,
        private array $fontPaths = [],
    ) {}

    /**
     * @param  float  $scale  Escala con la que se imprimirá la página: el tamaño y el
     *                        margen del folio se compensan para que salgan tal cual.
     */
    public function render(PageSequence $sequence, FolioSettings $settings, Size $page, float $scale, string $outputPath): void
    {
        $workdir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'noctua-folios-'.bin2hex(random_bytes(6));
        mkdir($workdir);

        try {
            copy($this->template, $workdir.DIRECTORY_SEPARATOR.'folios.typ');
            file_put_contents($workdir.DIRECTORY_SEPARATOR.'data.json', json_encode([
                'width_mm' => $page->widthMm,
                'height_mm' => $page->heightMm,
                'count' => $sequence->count(),
                'font' => $settings->font,
                'size_pt' => $settings->sizePt / $scale,
                'margin_mm' => $settings->marginMm / $scale,
                'position' => $settings->position->value,
                'folios' => (object) array_map(fn ($folio): string => $folio->text, $sequence->folios($settings)),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            $this->typst->compile($workdir, 'folios.typ', $outputPath, ['data' => '/data.json'], $this->fontPaths);
        } finally {
            array_map(unlink(...), glob($workdir.DIRECTORY_SEPARATOR.'*') ?: []);
            rmdir($workdir);
        }
    }
}
