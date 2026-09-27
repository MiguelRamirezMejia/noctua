<?php

namespace App\Domain\Printing;

final readonly class PrintOptions
{
    public function __construct(
        public bool $foldMarks = true,
    ) {}
}
