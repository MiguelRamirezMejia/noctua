<?php

namespace App\Domain\Imposition;

final readonly class ImpositionPlan
{
    /**
     * @param  list<Signature>  $signatures
     */
    public function __construct(
        public ImpositionSpec $spec,
        public array $signatures,
    ) {}

    /**
     * @return list<Sheet>
     */
    public function sheets(): array
    {
        return array_merge(...array_map(fn (Signature $s): array => $s->sheets, $this->signatures));
    }

    public function sheetCount(): int
    {
        return count($this->sheets());
    }

    /**
     * Páginas totales tras el relleno con blancos.
     */
    public function paddedPageCount(): int
    {
        return array_sum(array_map(fn (Signature $s): int => $s->pageCount(), $this->signatures));
    }

    public function blankPageCount(): int
    {
        return $this->paddedPageCount() - $this->spec->pageCount;
    }
}
