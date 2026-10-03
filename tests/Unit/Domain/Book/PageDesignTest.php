<?php

use App\Domain\Book\Design\BackgroundMode;
use App\Domain\Book\Design\InvalidPageDesign;
use App\Domain\Book\Design\PageDesign;
use App\Domain\Book\Design\RectangleShape;
use App\Domain\Book\Design\TextBox;

function designData(array $overrides = []): array
{
    return [
        'background' => ['color' => '#000000', 'mode' => 'behind'],
        'elements' => [
            [
                'id' => 't1', 'type' => 'text', 'x' => 10, 'y' => 20.5, 'width' => 100, 'height' => 40,
                'font' => 'Nunito', 'size_pt' => 18, 'line_height' => 1.4, 'color' => '#FFFFFF',
                'align' => 'center', 'valign' => 'middle', 'fill' => null,
                'spans' => [
                    ['text' => 'Había una vez un ', 'bold' => false, 'italic' => false, 'color' => null],
                    ['text' => 'dragón', 'bold' => true, 'italic' => false, 'color' => '#e11d48'],
                ],
            ],
            ['id' => 'r1', 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 50, 'height' => 10, 'fill' => '#facc15', 'opacity' => 0.5],
        ],
        ...$overrides,
    ];
}

it('lee y vuelve a escribir un diseño completo', function () {
    $design = PageDesign::fromArray(designData());

    expect($design->background?->mode)->toBe(BackgroundMode::Behind)
        ->and($design->elements[0])->toBeInstanceOf(TextBox::class)
        ->and($design->elements[1])->toBeInstanceOf(RectangleShape::class)
        ->and($design->textBoxes()[0]->plainText())->toBe('Había una vez un dragón')
        ->and($design->textBoxes()[0]->spans[1]->bold)->toBeTrue();

    $again = PageDesign::fromArray($design->toArray());

    expect($again->toArray())->toBe($design->toArray())
        ->and($design->toArray()['elements'][0]['color'])->toBe('#ffffff');
});

it('trata la ausencia de diseño como página sin cambios', function () {
    expect(PageDesign::fromArray(null)->isEmpty())->toBeTrue()
        ->and(PageDesign::fromArray(['background' => null, 'elements' => []])->isEmpty())->toBeTrue();
});

it('rechaza diseños inválidos', function (array $data) {
    PageDesign::fromArray($data);
})->throws(InvalidPageDesign::class)->with([
    'color mal escrito' => [designData(['background' => ['color' => 'negro', 'mode' => 'behind']])],
    'modo de fondo desconocido' => [designData(['background' => ['color' => '#000000', 'mode' => 'brillo']])],
    'tipo desconocido' => [['elements' => [['id' => 'x', 'type' => 'círculo', 'x' => 0, 'y' => 0, 'width' => 1, 'height' => 1]]]],
    'tamaño negativo' => [['elements' => [['id' => 'x', 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => -5, 'height' => 1, 'fill' => '#000000']]]],
    'opacidad fuera de rango' => [['elements' => [['id' => 'x', 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 5, 'height' => 1, 'fill' => '#000000', 'opacity' => 3]]]],
    'letra gigante' => [['elements' => [['id' => 'x', 'type' => 'text', 'x' => 0, 'y' => 0, 'width' => 5, 'height' => 5, 'size_pt' => 900, 'spans' => []]]]],
    'ids repetidos' => [['elements' => [
        ['id' => 'x', 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 5, 'height' => 5, 'fill' => '#000000'],
        ['id' => 'x', 'type' => 'rect', 'x' => 0, 'y' => 0, 'width' => 5, 'height' => 5, 'fill' => '#000000'],
    ]]],
    'número como texto' => [['elements' => [['id' => 'x', 'type' => 'rect', 'x' => '0', 'y' => 0, 'width' => 5, 'height' => 5, 'fill' => '#000000']]]],
]);
