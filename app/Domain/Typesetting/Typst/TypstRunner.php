<?php

namespace App\Domain\Typesetting\Typst;

use App\Domain\Typesetting\TypesettingFailed;
use Symfony\Component\Process\Process;

/**
 * Ejecuta el binario de Typst de forma aislada: raíz limitada al directorio de
 * trabajo, sin fuentes del sistema, fecha fija (salida determinista) y timeout.
 */
final readonly class TypstRunner
{
    public function __construct(
        private string $binary = 'typst',
        private int $timeoutSeconds = 120,
    ) {}

    /**
     * Familias que Typst reconoce en un directorio, sin contar las incluidas en Typst.
     *
     * @return list<string>
     */
    public function fontFamilies(string $directory): array
    {
        $process = new Process([$this->binary, 'fonts', '--ignore-system-fonts', '--ignore-embedded-fonts', '--font-path', $directory], timeout: $this->timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new TypesettingFailed('Typst no pudo leer las fuentes: '.trim($process->getErrorOutput()));
        }

        return array_values(array_filter(array_map(trim(...), explode("\n", $process->getOutput()))));
    }

    /**
     * @param  string  $root  Directorio raíz; Typst no puede leer fuera de él.
     * @param  string  $entry  Archivo .typ relativo a la raíz.
     * @param  array<string, string>  $inputs  Valores accesibles con sys.inputs.
     * @param  list<string>  $fontPaths
     */
    public function compile(string $root, string $entry, string $output, array $inputs = [], array $fontPaths = []): void
    {
        $command = [$this->binary, 'compile', '--root', $root, '--ignore-system-fonts', '--creation-timestamp', '0'];

        foreach ($fontPaths as $path) {
            array_push($command, '--font-path', $path);
        }

        foreach ($inputs as $key => $value) {
            array_push($command, '--input', "{$key}={$value}");
        }

        array_push($command, $root.DIRECTORY_SEPARATOR.$entry, $output);

        $process = new Process($command, $root, timeout: $this->timeoutSeconds);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new TypesettingFailed('Typst no pudo componer el documento: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
