<?php

use App\Domain\Book\ContentFrame;
use App\Domain\Imposition\SheetSide;
use App\Domain\Imposition\Slot;
use App\Domain\Printing\Offset;
use App\Domain\Printing\PageDoesNotFit;
use App\Domain\Printing\PageFit;
use App\Domain\Printing\Pdf\PdfComposer;
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

it('ajusta una página carta a la zona imprimible de la media hoja sin recortarla', function () {
    $layout = new SheetLayout(Size::letterLandscape(), new Size(215.9, 279.4), fit: PageFit::Fit);
    [$left, $right] = $layout->place(new SheetSide(new Slot(2), new Slot(3)), Side::Front);

    // Limita el ancho: (139.7 − 5) / 215.9.
    expect($layout->scale)->toEqualWithDelta(134.7 / 215.9, 1e-9)
        ->and(round($layout->placed->widthMm, 2))->toBe(134.7)
        ->and(round($layout->placed->heightMm, 2))->toBe(174.32)
        ->and(round($left->xMm, 2))->toBe(5.0)
        ->and(round($right->xMm, 2))->toBe(139.7)
        ->and(round($left->yMm, 2))->toBe(20.79);
});

it('llena la media hoja escalando por el lado que la cubre entera', function () {
    $layout = new SheetLayout(Size::letterLandscape(), new Size(215.9, 279.4), fit: PageFit::Fill);

    expect($layout->scale)->toEqualWithDelta(215.9 / 279.4, 1e-9)
        ->and(round($layout->placed->heightMm, 2))->toBe(215.9)
        ->and($layout->placed->widthMm)->toBeGreaterThan(139.7);
});

it('recorta cada página a su media hoja, desplazada con el offset', function () {
    $printer = new PrinterProfile(frontOffset: new Offset(1.0, 0.5));
    $layout = new SheetLayout(Size::letterLandscape(), Size::halfLetter(), $printer);
    [$left, $right] = $layout->place(new SheetSide(new Slot(4), new Slot(1)), Side::Front);

    expect([$left->clip->xMm, $left->clip->yMm, round($left->clip->widthMm, 2)])->toBe([1.0, 0.5, 139.7])
        ->and([round($right->clip->xMm, 2), round($right->clip->widthMm, 2), $right->clip->heightMm])->toBe([140.7, 139.7, 215.9]);
});

it('rechaza páginas que no caben dos veces en la hoja', function (Size $page) {
    new SheetLayout(Size::letterLandscape(), $page);
})->throws(PageDoesNotFit::class)->with([
    'A5, demasiado ancha' => [new Size(148, 210)],
    'demasiado alta' => [new Size(130, 220)],
]);

it('encaja una página de otro tamaño sin deformarla y centrada', function () {
    $page = new Size(148, 210);

    expect(PdfComposer::fitInto(new Size(148, 210), $page, new ContentFrame(1.0, 0.0, 0.0)))->toBe([0.0, 0.0, 148.0, 210.0]);

    [$x, $y, $width, $height] = PdfComposer::fitInto(new Size(74, 74), $page, new ContentFrame(1.0, 0.0, 0.0));
    expect($width)->toBe(148.0)->and($height)->toBe(148.0)->and($x)->toBe(0.0)->and($y)->toBe(31.0);
});
