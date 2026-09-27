<?php

namespace App\Domain\Printing;

final readonly class Size
{
    public function __construct(
        public float $widthMm,
        public float $heightMm,
    ) {}

    public static function letterLandscape(): self
    {
        return new self(279.4, 215.9);
    }

    public static function halfLetter(): self
    {
        return new self(139.7, 215.9);
    }

    public function isLandscape(): bool
    {
        return $this->widthMm > $this->heightMm;
    }

    public function equals(self $other, float $toleranceMm = 0.1): bool
    {
        return abs($this->widthMm - $other->widthMm) <= $toleranceMm
            && abs($this->heightMm - $other->heightMm) <= $toleranceMm;
    }
}
