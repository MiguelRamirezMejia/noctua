<?php

namespace App\Domain\Book\Design;

final readonly class PageBackground
{
    public function __construct(
        public Color $color,
        public BackgroundMode $mode = BackgroundMode::Behind,
    ) {}
}
