<?php

namespace App\Domain\Printing;

/**
 * Cómo se adapta la página del documento a la media hoja.
 */
enum PageFit: string
{
    /** Tamaño real: la página debe caber en la media hoja. */
    case Actual = 'actual';

    /** Escala para que la página entera quepa en la zona imprimible de la media hoja, sin perder nada. */
    case Fit = 'fit';

    /** Escala para cubrir toda la media hoja; lo que sobra se recorta. */
    case Fill = 'fill';

    public function label(): string
    {
        return match ($this) {
            self::Actual => 'Tamaño real',
            self::Fit => 'Ajustar (sin recortes)',
            self::Fill => 'Llenar la media hoja (recorta bordes)',
        };
    }
}
