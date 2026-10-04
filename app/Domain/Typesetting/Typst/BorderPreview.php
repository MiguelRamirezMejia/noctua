<?php

namespace App\Domain\Typesetting\Typst;

use App\Domain\Book\PageBorder;
use App\Domain\Printing\Size;

/**
 * SVG de un marco decorativo para una página, dibujado con la misma plantilla
 * que al imprimir. Se guarda en caché por sus parámetros: el mismo marco no se
 * vuelve a compilar.
 */
final readonly class BorderPreview
{
    public function __construct(
        private TypstRunner $typst,
        private string $template,
        private string $cacheDirectory,
    ) {}

    public function svg(PageBorder $border, Size $page, float $printScale): string
    {
        $data = [
            'width_mm' => round($page->widthMm, 2),
            'height_mm' => round($page->heightMm, 2),
            'border' => $border->forPage($printScale),
        ];
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $path = $this->cacheDirectory.DIRECTORY_SEPARATOR.hash('sha256', $json.hash_file('sha256', $this->marcos())).'.svg';

        if (is_file($path)) {
            return $path;
        }

        if (! is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0775, true);
        }

        $workdir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'noctua-marco-'.bin2hex(random_bytes(6));
        mkdir($workdir);

        try {
            copy($this->template, $workdir.DIRECTORY_SEPARATOR.'marco-vista.typ');
            copy($this->marcos(), $workdir.DIRECTORY_SEPARATOR.'marcos.typ');
            file_put_contents($workdir.DIRECTORY_SEPARATOR.'data.json', $json);

            $this->typst->compile($workdir, 'marco-vista.typ', $path.'.tmp.svg', ['data' => '/data.json']);
            rename($path.'.tmp.svg', $path);
        } finally {
            array_map(unlink(...), glob($workdir.DIRECTORY_SEPARATOR.'*') ?: []);
            rmdir($workdir);
        }

        return $path;
    }

    private function marcos(): string
    {
        return dirname($this->template).DIRECTORY_SEPARATOR.'marcos.typ';
    }
}
