<?php

use App\Domain\Book\Folio;
use App\Domain\Book\FolioSettings;
use App\Domain\Book\InvalidPageSequence;
use App\Domain\Book\PageItem;
use App\Domain\Book\PageSequence;

/**
 * @param  array<int, Folio>  $folios
 * @return array<int, string>
 */
function folioTexts(array $folios): array
{
    return array_map(fn (Folio $f): string => $f->text, $folios);
}

it('parte del PDF de origen con todas sus páginas en orden', function () {
    $sequence = PageSequence::fromSource(4);

    expect(array_map(fn (PageItem $i): ?int => $i->sourcePage, $sequence->items))->toBe([1, 2, 3, 4])
        ->and($sequence->removedSourcePages())->toBe([]);
});

it('informa de las páginas del origen que se quitaron', function () {
    $sequence = new PageSequence(5, [
        new PageItem('a', 3),
        new PageItem('b', 1),
        new PageItem('c', null),
        new PageItem('d', 3),
    ]);

    expect($sequence->removedSourcePages())->toBe([2, 4, 5])
        ->and($sequence->count())->toBe(4);
});

it('rechaza secuencias inválidas', function (int $sourceCount, array $items) {
    new PageSequence($sourceCount, $items);
})->throws(InvalidPageSequence::class)->with([
    'vacía' => [3, []],
    'página inexistente' => [3, [new PageItem('a', 4)]],
    'página cero' => [3, [new PageItem('a', 0)]],
    'id repetido' => [3, [new PageItem('a', 1), new PageItem('a', 2)]],
    'id vacío' => [3, [new PageItem('', 1)]],
]);

it('no numera si los folios están desactivados', function () {
    expect(PageSequence::fromSource(10)->folios(new FolioSettings(enabled: false)))->toBe([]);
});

it('numera desde la página indicada con el número indicado', function () {
    $folios = PageSequence::fromSource(8)->folios(new FolioSettings(enabled: true, firstNumberedPage: 5, startNumber: 1));

    expect(folioTexts($folios))->toBe([5 => '1', 6 => '2', 7 => '3', 8 => '4']);
});

it('cuenta los blancos y las páginas sin folio pero no los muestra', function () {
    $sequence = new PageSequence(4, [
        new PageItem('a', 1),
        new PageItem('b', null),
        new PageItem('c', 2, showFolio: false),
        new PageItem('d', 3),
        new PageItem('e', 4),
    ]);

    expect(folioTexts($sequence->folios(new FolioSettings(enabled: true))))->toBe([1 => '1', 4 => '4', 5 => '5']);
});

it('distingue páginas impares (derecha) y pares (izquierda)', function () {
    $folios = PageSequence::fromSource(3)->folios(new FolioSettings(enabled: true));

    expect(array_map(fn (Folio $f): bool => $f->isRecto(), $folios))->toBe([1 => true, 2 => false, 3 => true]);
});

it('valida la configuración de folios', function (array $args) {
    new FolioSettings(...$args);
})->throws(InvalidPageSequence::class)->with([
    'primera página cero' => [['firstNumberedPage' => 0]],
    'número negativo' => [['startNumber' => -1]],
    'letra diminuta' => [['sizePt' => 2]],
    'margen enorme' => [['marginMm' => 100]],
]);
