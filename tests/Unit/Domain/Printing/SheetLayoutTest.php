<?php

use App\Domain\Imposition\SheetSide;
use App\Domain\Imposition\Slot;
use App\Domain\Printing\Offset;
use App\Domain\Printing\PageDoesNotFit;
use App\Domain\Printing\Placement;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\SheetLayout;
use App\Domain\Printing\Side;
use App\Domain\Printing\Size;

/**
 * @param  list<Placement>  $placements
 * @return list<array{int, float, float}>
 */
function coords(array $placements): array
{
    return array_map(fn (Placement $p): array => [$p->page, round($p->xMm, 3), round($p->yMm, 3)], $placements);
}

it('coloca media carta a sangre a ambos lados del doblez', function () {
    $layout = new SheetLayout(Size::letterLandscape(), Size::halfLetter());

    expect(coords($layout->place(new SheetSide(new Slot(16), new Slot(1)), Side::Front)))
        ->toBe([[16, 0.0, 0.0], [1, 139.7, 0.0]]);
});

it('pega el lomo de páginas más pequeñas al doblez y las centra en vertical', function () {
    $layout = new SheetLayout(Size::letterLandscape(), new Size(120, 190));

    expect(coords($layout->place(new SheetSide(new Slot(4), new Slot(1)), Side::Front)))
        ->toBe([[4, 19.7, 12.95], [1, 139.7, 12.95]]);
});

it('omite las posiciones en blanco', function () {
    $layout = new SheetLayout(Size::letterLandscape(), Size::halfLetter());

    expect(coords($layout->place(new SheetSide(new Slot(null), new Slot(17)), Side::Front)))
        ->toBe([[17, 139.7, 0.0]]);
});

it('desplaza el contenido hacia el lomo según el creep', function () {
    $layout = new SheetLayout(Size::letterLandscape(), Size::halfLetter());

    expect(coords($layout->place(new SheetSide(new Slot(10, 0.5), new Slot(7, 0.5)), Side::Front)))
        ->toBe([[10, 0.5, 0.0], [7, 139.2, 0.0]]);
});

it('aplica el offset de la cara correspondiente', function () {
    $printer = new PrinterProfile(frontOffset: new Offset(1.0, -0.5), backOffset: new Offset(-0.8, 0.3));
    $layout = new SheetLayout(Size::letterLandscape(), Size::halfLetter(), $printer);
    $side = new SheetSide(new Slot(2), new Slot(3));

    expect(coords($layout->place($side, Side::Front)))->toBe([[2, 1.0, -0.5], [3, 140.7, -0.5]])
        ->and(coords($layout->place($side, Side::Back)))->toBe([[2, -0.8, 0.3], [3, 138.9, 0.3]])
        ->and($layout->foldXMm(Side::Back))->toEqualWithDelta(138.9, 1e-9);
});

it('rechaza páginas que no caben dos veces en la hoja', function (Size $page) {
    new SheetLayout(Size::letterLandscape(), $page);
})->throws(PageDoesNotFit::class)->with([
    'A5, demasiado ancha' => [new Size(148, 210)],
    'demasiado alta' => [new Size(130, 220)],
]);
