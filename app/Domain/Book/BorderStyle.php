<?php

namespace App\Domain\Book;

/**
 * Diseños de marco decorativo. Cada uno se dibuja en resources/typst/marcos.typ.
 */
enum BorderStyle: string
{
    case Line = 'linea';
    case Double = 'doble';
    case Classic = 'clasico';
    case Corners = 'esquinas';
    case Deco = 'deco';
    case Dots = 'puntos';
    case Waves = 'ondas';
    case Scallop = 'feston';
    case GreekKey = 'greca';
    case Stars = 'estrellas';

    public function label(): string
    {
        return match ($this) {
            self::Line => 'Línea fina',
            self::Double => 'Doble línea',
            self::Classic => 'Clásico',
            self::Corners => 'Esquinas',
            self::Deco => 'Art déco',
            self::Dots => 'Puntos',
            self::Waves => 'Ondas',
            self::Scallop => 'Festón',
            self::GreekKey => 'Greca griega',
            self::Stars => 'Estrellas',
        };
    }
}
