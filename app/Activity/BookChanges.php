<?php

namespace App\Activity;

use App\Domain\Book\BookLayout;
use App\Domain\Book\FolioSettings;
use App\Domain\Book\PageItem;
use App\Domain\Book\PageSequence;
use App\Models\Book;

/**
 * Resumen legible de lo que cambia al guardar un libro, para el registro.
 */
final class BookChanges
{
    /**
     * @return array{summary: string, properties: array<string, mixed>}
     */
    public static function describe(Book $book, string $title, PageSequence $pages, FolioSettings $folios, BookLayout $layout): array
    {
        $before = $book->sequence();
        $changes = [];
        $properties = [];

        if ($title !== $book->title) {
            $changes[] = "título «{$book->title}» → «{$title}»";
        }

        $order = fn (PageSequence $s): array => array_map(fn (PageItem $i): string => $i->id, $s->items);
        if ($order($before) !== $order($pages)) {
            $changes[] = 'páginas: '.$before->count().' → '.$pages->count();
            $properties['paginas'] = ['antes' => $before->count(), 'después' => $pages->count()];
            $properties['blancos'] = count(array_filter($pages->items, fn (PageItem $i): bool => $i->isBlank()));
            $properties['páginas_quitadas'] = $pages->removedSourcePages();
        }

        $elements = fn (PageSequence $s): int => array_sum(array_map(fn (PageItem $i): int => count($i->design->elements), $s->items));
        $designed = fn (PageSequence $s): array => array_map(fn (PageItem $i): array => $i->design->toArray(), $s->items);
        if ($designed($before) !== $designed($pages)) {
            $changes[] = 'diseño de páginas (elementos: '.$elements($before).' → '.$elements($pages).')';
            $properties['elementos'] = ['antes' => $elements($before), 'después' => $elements($pages)];
            $properties['páginas_con_texto_editable'] = count(array_filter($pages->items, fn (PageItem $i): bool => $i->design->hideSourceText));
        }

        $oldFolios = $book->folioSettings();
        if ($oldFolios != $folios) {
            $changes[] = $folios->enabled ? "numeración ({$folios->font}, {$folios->sizePt} pt, desde la página {$folios->firstNumberedPage})" : 'numeración desactivada';
            $properties['numeración'] = $folios->enabled;
        }

        $oldLayout = $book->bookLayout();
        $margins = fn (BookLayout $l): array => array_diff_key($l->toArray(), ['border' => true]);
        if ($margins($oldLayout) !== $margins($layout)) {
            $changes[] = 'márgenes';
            $properties['márgenes_mm'] = $margins($layout);
        }

        if ($oldLayout->border?->toArray() !== $layout->border?->toArray()) {
            $changes[] = $layout->border !== null ? 'marco «'.$layout->border->style->label().'»' : 'marco quitado';
            $properties['marco'] = $layout->border?->toArray();
        }

        return [
            'summary' => $changes === [] ? 'sin cambios' : implode(', ', $changes),
            'properties' => $properties,
        ];
    }
}
