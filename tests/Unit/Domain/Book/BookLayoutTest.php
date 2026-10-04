<?php

use App\Domain\Book\BookLayout;
use App\Domain\Book\InvalidPageSequence;
use App\Domain\Printing\Size;

it('no transforma el contenido sin márgenes', function () {
    expect((new BookLayout)->frame(Size::halfLetter(), 1.0, 1)->isIdentity())->toBeTrue();
});

it('reduce y centra el contenido dentro de márgenes en espejo', function () {
    $layout = new BookLayout(marginTopMm: 10, marginBottomMm: 10, marginInsideMm: 20, marginOutsideMm: 10);
    $page = new Size(140, 200);

    $recto = $layout->frame($page, 1.0, 1);
    $verso = $layout->frame($page, 1.0, 2);

    // Ancho disponible 110 mm de 140 → escala 110/140; alto disponible 180 de 200 (holgado).
    expect($recto->scale)->toEqualWithDelta(110 / 140, 1e-9)
        // Página impar: el lomo (interior) está a la izquierda.
        ->and($recto->xMm)->toEqualWithDelta(20.0, 1e-9)
        // Página par: el lomo está a la derecha, así que a la izquierda va el exterior.
        ->and($verso->xMm)->toEqualWithDelta(10.0, 1e-9)
        // Centrado en vertical dentro de 180 mm.
        ->and($recto->yMm)->toEqualWithDelta(10 + (180 - 200 * 110 / 140) / 2, 1e-9);
});

it('expresa los márgenes tal como quedan impresos', function () {
    $layout = new BookLayout(marginTopMm: 10, marginBottomMm: 10, marginInsideMm: 10, marginOutsideMm: 10);

    // Con la página impresa a la mitad, 10 mm impresos son 20 mm de la página de origen.
    $frame = $layout->frame(new Size(200, 200), 0.5, 1);

    expect($frame->scale)->toEqualWithDelta(160 / 200, 1e-9)
        ->and($frame->xMm)->toEqualWithDelta(20.0, 1e-9);
});

it('rechaza márgenes negativos o desmesurados', function (array $args) {
    new BookLayout(...$args);
})->throws(InvalidPageSequence::class)->with([
    [['marginTopMm' => -1]],
    [['marginOutsideMm' => 100]],
]);

it('se guarda y se lee', function () {
    $layout = new BookLayout(5, 6, 7, 8);

    expect(BookLayout::fromArray($layout->toArray()))->toEqual($layout)
        ->and(BookLayout::fromArray(null))->toEqual(new BookLayout);
});
