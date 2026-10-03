<?php

namespace App\Domain\Book\Design;

/**
 * Color RGB en formato #rrggbb.
 */
final readonly class Color
{
    public string $hex;

    public function __construct(string $hex)
    {
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
            throw new InvalidPageDesign("Color no válido: «{$hex}». Usa el formato #rrggbb.");
        }

        $this->hex = strtolower($hex);
    }

    /**
     * @return array{int, int, int}
     */
    public function rgb(): array
    {
        return [(int) hexdec(substr($this->hex, 1, 2)), (int) hexdec(substr($this->hex, 3, 2)), (int) hexdec(substr($this->hex, 5, 2))];
    }
}
