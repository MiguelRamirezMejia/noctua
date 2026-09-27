<?php

namespace App\Domain\Imposition;

/**
 * Una cara de la hoja vista de frente con el doblez en vertical al centro.
 */
final readonly class SheetSide
{
    public function __construct(
        public Slot $left,
        public Slot $right,
    ) {}

    /**
     * @return array{?int, ?int}
     */
    public function pages(): array
    {
        return [$this->left->page, $this->right->page];
    }
}
