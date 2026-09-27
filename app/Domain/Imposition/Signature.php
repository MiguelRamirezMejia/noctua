<?php

namespace App\Domain\Imposition;

final readonly class Signature
{
    /**
     * @param  list<Sheet>  $sheets  De la hoja exterior a la interior.
     */
    public function __construct(
        public int $number,
        public int $firstPage,
        public array $sheets,
    ) {}

    public function pageCount(): int
    {
        return 4 * count($this->sheets);
    }

    public function lastPage(): int
    {
        return $this->firstPage + $this->pageCount() - 1;
    }
}
