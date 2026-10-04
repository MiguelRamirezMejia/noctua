<?php

namespace App\Domain\Book\Extraction;

final readonly class FontMatch
{
    public function __construct(
        public string $family,
        /** true si es la misma fuente del PDF; false si es una sustituta. */
        public bool $exact,
    ) {}
}
