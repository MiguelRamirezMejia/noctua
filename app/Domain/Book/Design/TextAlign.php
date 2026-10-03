<?php

namespace App\Domain\Book\Design;

enum TextAlign: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';
    case Justify = 'justify';
}
