<?php

namespace App\Actions\Book;

use App\Models\Book;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Página del PDF de origen sin su texto (Ghostscript -dFILTERTEXT): imágenes y
 * dibujos intactos, para reescribir el texto como cuadros editables. Se genera
 * una vez por página y queda en caché.
 */
final readonly class TextlessPage
{
    public function __construct(
        private string $ghostscript = 'gs',
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

        $tmp = $path.'.tmp';
        $process = new Process([
            $this->ghostscript, '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=pdfwrite', '-dFILTERTEXT',
            // PDF 1.4 sin flujos de objetos: el parser gratuito de FPDI no lee esa compresión.
            '-dCompatibilityLevel=1.4', '-dWriteObjStms=false', '-dWriteXRefStm=false',
            "-dFirstPage={$page}", "-dLastPage={$page}",
            // Sin recomprimir ni reducir imágenes: la página debe quedar idéntica salvo el texto.
            '-dPassThroughJPEGImages=true', '-dAutoFilterColorImages=false', '-dColorImageFilter=/FlateEncode',
            '-dDownsampleColorImages=false', '-dDownsampleGrayImages=false', '-dDownsampleMonoImages=false',
            "-sOutputFile={$tmp}", $book->sourcePath(),
        ],
            // Ghostscript necesita una carpeta temporal y el servidor web no
            // siempre pasa TEMP/TMPDIR: se le da la de la caché, que es escribible.
            env: ['TEMP' => $directory, 'TMP' => $directory, 'TMPDIR' => $directory],
            timeout: $this->timeoutSeconds,
        );
        $process->run();

        if (! $process->isSuccessful() || ! is_file($tmp)) {
            if (is_file($tmp)) {
                unlink($tmp);
            }

            throw new RuntimeException('No se pudo quitar el texto de la página: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        rename($tmp, $path);

        return $path;
    }
}
