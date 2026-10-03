<?php

namespace App\Domain\Book\Design;

enum BackgroundMode: string
{
    /** Debajo de la página: solo se ve donde la página no pinta nada. */
    case Behind = 'behind';

    /** Teñido (multiplicar) sobre la página: lo blanco toma el color, lo oscuro se mantiene. */
    case Tint = 'tint';
}
