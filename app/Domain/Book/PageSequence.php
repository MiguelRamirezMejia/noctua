<?php

namespace App\Domain\Book;

/**
 * Orden final de las páginas del libro a partir de un PDF de origen:
 * reordenadas, con páginas quitadas, duplicadas o blancos insertados.
 */
final readonly class PageSequence
{
    /**
     * @param  list<PageItem>  $items
     */
    public function __construct(
        public int $sourcePageCount,
        public array $items,
    ) {
        if ($items === []) {
            throw new InvalidPageSequence('El libro debe tener al menos una página.');
        }

        $ids = [];
        foreach ($items as $item) {
            if ($item->id === '' || isset($ids[$item->id])) {
                throw new InvalidPageSequence("Identificador de página vacío o repetido: «{$item->id}».");
            }
            $ids[$item->id] = true;

            if ($item->sourcePage !== null && ($item->sourcePage < 1 || $item->sourcePage > $sourcePageCount)) {
                throw new InvalidPageSequence("La página {$item->sourcePage} no existe en el PDF de origen ({$sourcePageCount} páginas).");
            }
        }
    }

    /**
     * El PDF de origen tal cual: todas sus páginas en orden.
     */
    public static function fromSource(int $sourcePageCount): self
    {
        return new self($sourcePageCount, array_map(
            fn (int $page): PageItem => new PageItem("p{$page}", $page),
            range(1, max(1, $sourcePageCount)),
        ));
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Páginas del origen que no aparecen en el libro.
     *
     * @return list<int>
     */
    public function removedSourcePages(): array
    {
        $used = array_flip(array_filter(array_map(fn (PageItem $i): ?int => $i->sourcePage, $this->items)));

        return array_values(array_filter(range(1, $this->sourcePageCount), fn (int $p): bool => ! isset($used[$p])));
    }

    /**
     * Folios a estampar. La numeración cuenta todas las páginas desde la primera
     * numerada, incluidos los blancos, pero los blancos y las páginas marcadas
     * sin folio no lo muestran.
     *
     * @return array<int, Folio> indexado por posición (1 = primera página)
     */
    public function folios(FolioSettings $settings): array
    {
        if (! $settings->enabled) {
            return [];
        }

        $folios = [];
        foreach ($this->items as $index => $item) {
            $position = $index + 1;

            if ($position < $settings->firstNumberedPage || $item->isBlank() || ! $item->showFolio) {
                continue;
            }

            $folios[$position] = new Folio($position, (string) ($settings->startNumber + $position - $settings->firstNumberedPage));
        }

        return $folios;
    }
}
