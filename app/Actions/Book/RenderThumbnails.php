<?php

namespace App\Actions\Book;

use App\Models\Book;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Miniaturas JPEG de cada página del PDF de origen, para el editor de páginas.
 */
final readonly class RenderThumbnails
{
    private const int DPI = 40;

    public function __construct(
        private string $pdftoppm = 'pdftoppm',
        private int $timeoutSeconds = 300,
    ) {}

    public function handle(Book $book): void
    {
        $directory = dirname($book->thumbnailPath(1));

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $prefix = $directory.DIRECTORY_SEPARATOR.'p';
        $process = new Process(
            [$this->pdftoppm, '-r', (string) self::DPI, '-jpeg', '-jpegopt', 'quality=75', $book->sourcePath(), $prefix],
            timeout: $this->timeoutSeconds,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('No se pudieron generar las miniaturas: '.trim($process->getErrorOutput()));
        }

        // pdftoppm numera con ceros a la izquierda según el total (p-01, p-001…).
        foreach (glob($prefix.'-*.jpg') ?: [] as $file) {
            if (preg_match('/-(\d+)\.jpg$/', $file, $m)) {
                rename($file, $directory.DIRECTORY_SEPARATOR.((int) $m[1]).'.jpg');
            }
        }
    }
}
