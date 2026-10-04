<?php

namespace App\Actions\Book;

use App\Models\Book;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Imagen de una página del PDF de origen en resolución de pantalla, para el
 * editor de pliegos. Se genera bajo demanda y queda en caché.
 */
final readonly class RenderPageImage
{
    public const int DPI = 110;

    public function __construct(
        private TextlessPage $textless,
        private string $pdftoppm = 'pdftoppm',
        private int $timeoutSeconds = 120,
    ) {}

    /**
     * @param  bool  $textless  Versión de la página sin su texto original.
     */
    public function handle(Book $book, int $page, bool $textless = false): string
    {
        $path = $book->pageImagePath($page, $textless);
        [$pdf, $pdfPage] = $textless ? [$this->textless->handle($book, $page), 1] : [$book->sourcePath(), $page];

        if (is_file($path)) {
            return $path;
        }

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $process = new Process([
            $this->pdftoppm, '-r', (string) self::DPI, '-f', (string) $pdfPage, '-l', (string) $pdfPage,
            '-jpeg', '-jpegopt', 'quality=85', '-singlefile', $pdf, substr($path, 0, -4),
        ], timeout: $this->timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($path)) {
            throw new RuntimeException('No se pudo generar la imagen de la página: '.trim($process->getErrorOutput()));
        }

        return $path;
    }
}
