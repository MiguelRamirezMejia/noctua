<?php

namespace App\Actions\Book;

use App\Domain\Printing\Pdf\DeterministicFpdi;
use App\Domain\Printing\Size;
use App\Models\Book;
use DOMDocument;
use DOMElement;
use GdImage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Página del PDF de origen sin su texto, para reescribirlo como cuadros
 * editables. Se genera una vez por página y queda en caché.
 *
 * Ghostscript (-dFILTERTEXT) quita el texto vectorial y deja imágenes y
 * dibujos intactos. En un libro escaneado el texto visible está dentro de la
 * imagen y el texto del PDF es una capa invisible de OCR: quitarla no cambia
 * nada. Para esas palabras se rasteriza la página y se borran de la imagen
 * pintándolas con el color del papel que las rodea.
 */
final readonly class TextlessPage
{
    /** Resolución de la página rasterizada cuando hay que borrar texto escaneado. */
    public const int RASTER_DPI = 300;

    /** Resolución para comparar la página antes y después de quitar el texto. */
    private const int PROBE_DPI = 72;

    /**
     * Cambio de tinta media (0–255) por debajo del cual una palabra no
     * desapareció. Medido: el texto vectorial quitado cambia 8 o más; las
     * palabras escaneadas, menos de 4.
     */
    private const float UNCHANGED_THRESHOLD = 6.0;

    public function __construct(
        private string $ghostscript = 'gs',
        private string $pdftotext = 'pdftotext',
        private string $pdftoppm = 'pdftoppm',
        private int $timeoutSeconds = 120,
    ) {}

    public function handle(Book $book, int $page): string
    {
        $path = $book->textlessPagePath($page);

        if (is_file($path)) {
            return $path;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $vector = $path.'.vector.tmp';

        try {
            $this->filterText($book->sourcePath(), $page, $vector, $directory);

            [$lines, $width, $height] = $this->lines($book->sourcePath(), $page);
            $baked = $lines === [] ? [] : $this->bakedBoxes($book->sourcePath(), $page, $vector, $lines, $width, $height, $directory);

            if ($baked === []) {
                rename($vector, $path);
            } else {
                $this->eraseFromRaster($vector, $baked, $width, $height, $path, $directory);
            }
        } finally {
            foreach (glob($path.'.*.tmp*') ?: [] as $leftover) {
                unlink($leftover);
            }
        }

        return $path;
    }

    private function filterText(string $pdf, int $page, string $out, string $directory): void
    {
        $this->run([
            $this->ghostscript, '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=pdfwrite', '-dFILTERTEXT',
            // PDF 1.4 sin flujos de objetos: el parser gratuito de FPDI no lee esa compresión.
            '-dCompatibilityLevel=1.4', '-dWriteObjStms=false', '-dWriteXRefStm=false',
            "-dFirstPage={$page}", "-dLastPage={$page}",
            // Sin recomprimir ni reducir imágenes: la página debe quedar idéntica salvo el texto.
            '-dPassThroughJPEGImages=true', '-dAutoFilterColorImages=false', '-dColorImageFilter=/FlateEncode',
            '-dDownsampleColorImages=false', '-dDownsampleGrayImages=false', '-dDownsampleMonoImages=false',
            "-sOutputFile={$out}", $pdf,
        ], 'No se pudo quitar el texto de la página', $directory);

        if (! is_file($out)) {
            throw new RuntimeException('No se pudo quitar el texto de la página.');
        }
    }

    /**
     * Cajas de las palabras de cada línea, en puntos desde la esquina superior izquierda.
     *
     * @return array{list<non-empty-list<array{float, float, float, float}>>, float, float}
     */
    private function lines(string $pdf, int $page): array
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($this->run([$this->pdftotext, '-bbox-layout', '-f', (string) $page, '-l', (string) $page, $pdf, '-'], 'No se pudo leer el texto de la página'))) {
            throw new RuntimeException('No se pudo leer el texto de la página.');
        }

        $pageNode = $dom->getElementsByTagName('page')->item(0);
        $width = $pageNode instanceof DOMElement ? (float) $pageNode->getAttribute('width') : 612.0;
        $height = $pageNode instanceof DOMElement ? (float) $pageNode->getAttribute('height') : 792.0;

        $lines = [];
        foreach ($dom->getElementsByTagName('line') as $line) {
            $words = [];
            foreach ($line->getElementsByTagName('word') as $word) {
                $words[] = [
                    (float) $word->getAttribute('xMin'),
                    (float) $word->getAttribute('yMin'),
                    (float) $word->getAttribute('xMax'),
                    (float) $word->getAttribute('yMax'),
                ];
            }

            if ($words !== []) {
                $lines[] = $words;
            }
        }

        return [$lines, $width, $height];
    }

    /**
     * Cajas a borrar de la imagen: palabras que siguen viéndose igual tras
     * quitar el texto, porque están dibujadas dentro de una imagen (escaneo) o
     * tapadas por ella. Si lo está la línea entera se borra de punta a punta,
     * huecos incluidos, para no dejar restos entre letras espaciadas.
     *
     * @param  list<non-empty-list<array{float, float, float, float}>>  $lines
     * @return list<array{float, float, float, float}>
     */
    private function bakedBoxes(string $pdf, int $page, string $vector, array $lines, float $width, float $height, string $directory): array
    {
        $before = $this->render($pdf, $page, self::PROBE_DPI, $directory, true);
        $after = $this->render($vector, 1, self::PROBE_DPI, $directory, true);

        $boxes = [];
        foreach ($lines as $words) {
            $baked = [];
            foreach ($words as $word) {
                // Con margen alrededor, un desplazamiento de la imagen apenas cambia la tinta de la caja.
                $pad = ($word[3] - $word[1]) * 0.5;
                $box = $this->pixels([$word[0] - $pad, $word[1] - $pad, $word[2] + $pad, $word[3] + $pad], $before, $width, $height);

                if ($this->meanDifference($before, $after, $box) < self::UNCHANGED_THRESHOLD) {
                    $baked[] = $word;
                }
            }

            if (count($baked) === count($words)) {
                $boxes[] = [
                    min(array_column($words, 0)),
                    min(array_column($words, 1)),
                    max(array_column($words, 2)),
                    max(array_column($words, 3)),
                ];
            } else {
                array_push($boxes, ...$baked);
            }
        }

        return $boxes;
    }

    /**
     * Rasteriza la página ya sin texto vectorial, borra el texto escaneado
     * y la guarda como PDF de una página del mismo tamaño.
     *
     * @param  list<array{float, float, float, float}>  $boxes
     */
    private function eraseFromRaster(string $vector, array $boxes, float $width, float $height, string $out, string $directory): void
    {
        $image = $this->render($vector, 1, self::RASTER_DPI, $directory, false);

        foreach ($boxes as [$xMin, $yMin, $xMax, $yMax]) {
            // Las cajas del OCR se ajustan a la línea: se amplían para cubrir
            // acentos, descendentes y cursivas sin llegar a la línea vecina.
            $padY = ($yMax - $yMin) * 0.25;
            $padX = $padY;
            [$x1, $y1, $x2, $y2] = $this->pixels([$xMin - $padX, $yMin - $padY, $xMax + $padX, $yMax + $padY], $image, $width, $height);

            imagefilledrectangle($image, $x1, $y1, $x2, $y2, $this->surroundingColor($image, $x1, $y1, $x2, $y2));
        }

        $grayscale = $this->isGrayscale($image);
        $raster = $out.'.raster.tmp'.($grayscale ? '.png' : '.jpg');

        if ($grayscale) {
            // Paleta de 256 grises: exacta para un escaneo y mucho más ligera.
            imagetruecolortopalette($image, false, 256);
            imagepng($image, $raster, 9);
        } else {
            imagejpeg($image, $raster, 92);
        }

        $pdf = new DeterministicFpdi(new Size($width * 25.4 / 72, $height * 25.4 / 72));
        $pdf->AddPage();
        $pdf->Image($raster, 0, 0, $width * 25.4 / 72, $height * 25.4 / 72);
        $pdf->Output('F', $out.'.pdf.tmp');
        rename($out.'.pdf.tmp', $out);
    }

    private function render(string $pdf, int $page, int $dpi, string $directory, bool $gray): GdImage
    {
        $prefix = $directory.DIRECTORY_SEPARATOR.'render-'.bin2hex(random_bytes(6));

        try {
            $this->run(array_values(array_filter([
                $this->pdftoppm, '-r', (string) $dpi, '-f', (string) $page, '-l', (string) $page,
                $gray ? '-gray' : null, '-png', '-singlefile', $pdf, $prefix,
            ])), 'No se pudo generar la imagen de la página');

            $image = @imagecreatefrompng($prefix.'.png');
            if (! $image instanceof GdImage) {
                throw new RuntimeException('No se pudo leer la imagen de la página.');
            }

            if (! imageistruecolor($image)) {
                imagepalettetotruecolor($image);
            }

            return $image;
        } finally {
            if (is_file($prefix.'.png')) {
                unlink($prefix.'.png');
            }
        }
    }

    /**
     * @param  array{float, float, float, float}  $box  Caja en puntos.
     * @return array{int, int, int, int} Caja en píxeles, dentro de la imagen.
     */
    private function pixels(array $box, GdImage $image, float $width, float $height): array
    {
        $sx = imagesx($image) / $width;
        $sy = imagesy($image) / $height;
        $clamp = fn (float $v, int $max): int => max(0, min($max, (int) round($v)));

        return [
            $clamp($box[0] * $sx, imagesx($image) - 1),
            $clamp($box[1] * $sy, imagesy($image) - 1),
            $clamp($box[2] * $sx, imagesx($image) - 1),
            $clamp($box[3] * $sy, imagesy($image) - 1),
        ];
    }

    /**
     * Diferencia entre la tinta media de una caja en las dos imágenes. Se
     * compara la media y no píxel a píxel porque Ghostscript recodifica las
     * imágenes y las desplaza fracciones de píxel: la tinta se conserva.
     *
     * @param  array{int, int, int, int}  $box
     */
    private function meanDifference(GdImage $a, GdImage $b, array $box): float
    {
        [$x1, $y1, $x2, $y2] = $box;
        $total = 0;
        $count = 0;

        for ($y = $y1; $y <= $y2; $y++) {
            for ($x = $x1; $x <= $x2; $x++) {
                $total += (imagecolorat($a, $x, $y) & 0xFF) - (imagecolorat($b, $x, $y) & 0xFF);
                $count++;
            }
        }

        return $count === 0 ? 0.0 : abs($total / $count);
    }

    /**
     * Color del papel alrededor de una caja: mediana de su borde exterior.
     */
    private function surroundingColor(GdImage $image, int $x1, int $y1, int $x2, int $y2): int
    {
        $samples = [[], [], []];
        $add = function (int $x, int $y) use ($image, &$samples): void {
            if ($x < 0 || $y < 0 || $x >= imagesx($image) || $y >= imagesy($image)) {
                return;
            }
            $rgb = imagecolorat($image, $x, $y);
            $samples[0][] = ($rgb >> 16) & 0xFF;
            $samples[1][] = ($rgb >> 8) & 0xFF;
            $samples[2][] = $rgb & 0xFF;
        };

        for ($x = $x1 - 1; $x <= $x2 + 1; $x++) {
            $add($x, $y1 - 1);
            $add($x, $y2 + 1);
        }
        for ($y = $y1; $y <= $y2; $y++) {
            $add($x1 - 1, $y);
            $add($x2 + 1, $y);
        }

        if ($samples[0] === []) {
            return 0xFFFFFF;
        }

        return ($this->median($samples[0]) << 16) | ($this->median($samples[1]) << 8) | $this->median($samples[2]);
    }

    /**
     * Mediana de un canal; blanco si no hay muestras.
     *
     * @param  list<int>  $values
     */
    private function median(array $values): int
    {
        sort($values);

        return $values[intdiv(count($values), 2)] ?? 0xFF;
    }

    private function isGrayscale(GdImage $image): bool
    {
        $stepX = max(1, intdiv(imagesx($image), 64));
        $stepY = max(1, intdiv(imagesy($image), 64));

        for ($y = 0; $y < imagesy($image); $y += $stepY) {
            for ($x = 0; $x < imagesx($image); $x += $stepX) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                if (max($r, $g, $b) - min($r, $g, $b) > 8) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command, string $error, ?string $tempDirectory = null): string
    {
        $process = new Process(
            $command,
            // Ghostscript necesita una carpeta temporal y el servidor web no
            // siempre pasa TEMP/TMPDIR: se le da la de la caché, que es escribible.
            env: $tempDirectory === null ? null : ['TEMP' => $tempDirectory, 'TMP' => $tempDirectory, 'TMPDIR' => $tempDirectory],
            timeout: $this->timeoutSeconds,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($error.': '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return $process->getOutput();
    }
}
