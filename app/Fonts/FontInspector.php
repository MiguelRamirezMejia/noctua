<?php

namespace App\Fonts;

use FontLib\Font;
use FontLib\TrueType\File;
use Throwable;

/**
 * Lee familia, peso y estilo de un archivo TTF/OTF y comprueba que se pueda
 * incrustar en un PDF.
 */
final class FontInspector
{
    /** OS/2 fsType: incrustación prohibida (Restricted License). */
    private const int FS_TYPE_RESTRICTED = 0x0002;

    /** OS/2 fsSelection: cursiva. */
    private const int FS_SELECTION_ITALIC = 0x0001;

    public function inspect(string $path): FontFace
    {
        $extension = $this->extension($path);

        try {
            $font = Font::load($path);
        } catch (Throwable) {
            $font = null;
        }

        if (! $font instanceof File) {
            throw new InvalidFont('No es un archivo de fuente válido.');
        }

        try {
            $font->parse();
            $family = $this->name($font, 16) ?? $this->name($font, 1);
            $weight = $font->getData('OS/2', 'usWeightClass');
            $selection = $font->getData('OS/2', 'fsSelection');
            $fsType = $font->getData('OS/2', 'fsType');
        } catch (Throwable) {
            throw new InvalidFont('No se pudo leer la información de la fuente.');
        } finally {
            $font->close();
        }

        if ($family === null) {
            throw new InvalidFont('La fuente no indica el nombre de su familia.');
        }

        if (is_int($fsType) && ($fsType & 0x000F) === self::FS_TYPE_RESTRICTED) {
            throw new InvalidFont("La licencia de «{$family}» no permite incrustarla en un PDF.");
        }

        return new FontFace(
            family: $family,
            weight: is_int($weight) && $weight >= 1 && $weight <= 1000 ? $weight : 400,
            italic: is_int($selection) && ($selection & self::FS_SELECTION_ITALIC) !== 0,
            extension: $extension,
        );
    }

    private function extension(string $path): string
    {
        $magic = (string) file_get_contents($path, length: 4);

        return match ($magic) {
            "\x00\x01\x00\x00", 'true' => 'ttf',
            'OTTO' => 'otf',
            'wOFF', 'wOF2' => throw new InvalidFont('Las fuentes web (WOFF/WOFF2) no sirven para imprimir: sube el archivo TTF u OTF.'),
            default => throw new InvalidFont('Solo se admiten fuentes TTF u OTF.'),
        };
    }

    private function name(File $font, int $id): ?string
    {
        $value = $font->getNameTableString($id);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
