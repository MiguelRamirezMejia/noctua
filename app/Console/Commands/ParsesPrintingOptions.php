<?php

namespace App\Console\Commands;

use App\Domain\Printing\Offset;
use InvalidArgumentException;

/**
 * Lectura tipada de argumentos y opciones de consola.
 */
trait ParsesPrintingOptions
{
    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : throw new InvalidArgumentException("Falta el argumento {$name}.");
    }

    private function intOption(string $name): int
    {
        // Desde la consola llega como string; desde Artisan::call() puede llegar como int.
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT);

        return is_int($value) ? $value : throw new InvalidArgumentException("--{$name} debe ser un número entero.");
    }

    private function floatOption(string $name): float
    {
        $value = $this->option($name);

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("--{$name} debe ser un número.");
        }

        return (float) $value;
    }

    private function offsetOption(string $name): Offset
    {
        $value = $this->option($name);
        $parts = is_string($value) ? explode(',', $value) : [];

        if (count($parts) !== 2 || ! is_numeric(trim($parts[0])) || ! is_numeric(trim($parts[1]))) {
            throw new InvalidArgumentException("--{$name} debe tener la forma X,Y en mm (por ejemplo 0.5,-0.3).");
        }

        return new Offset((float) trim($parts[0]), (float) trim($parts[1]));
    }
}
