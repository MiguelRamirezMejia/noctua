<?php

use App\Domain\Book\Design\TextAlign;
use App\Domain\Book\Design\TextBox;
use App\Domain\Book\Extraction\FontMatcher;
use App\Domain\Book\Extraction\TextBlockBuilder;
use App\Domain\Book\Extraction\TextLine;
use App\Domain\Book\Extraction\TextRun;

/**
 * Página 7 de TIMO tal como la devuelve Poppler (puntos, carta 612 × 792).
 *
 * @return list<TextLine>
 */
function timoPage7(): array
{
    $body = fn (string $text, float $x, float $y, float $X) => new TextLine($text, $x, $y, $X, $y + 19.49, [new TextRun($text.' ')], 16.0, 'Verdana');

    return [
        new TextLine('El dragón más rápido', 177.0, 83.3, 439.7, 110.08, [new TextRun('El dragón más rápido ', bold: true)], 22.0, 'Verdana'),
        $body('Timo era un dragón pequeño.', 183.99, 156.23, 423.69),
        $body('Y era muy, muy curioso.', 204.12, 186.23, 402.21),
        $body('Pero tenía un problema:', 205.16, 216.23, 401.18),
        $body('¡Todo lo hacía demasiado', 203.15, 246.23, 408.84),
        $body('rápido!', 274.73, 276.23, 331.62),
    ];
}

function builder(array $fonts = ['Libertinus Serif', 'Nunito', 'Open Sans']): TextBlockBuilder
{
    return new TextBlockBuilder(new FontMatcher($fonts));
}

it('agrupa el título y el párrafo de una página real de Canva', function () {
    $boxes = builder()->build(timoPage7(), 612, 792);

    expect($boxes)->toHaveCount(2)
        ->and($boxes[0]->plainText())->toBe('El dragón más rápido')
        ->and($boxes[0]->spans[0]->bold)->toBeTrue()
        ->and($boxes[0]->sizePt)->toBe(22.0)
        ->and($boxes[0]->align)->toBe(TextAlign::Center)
        ->and($boxes[1]->plainText())->toBe("Timo era un dragón pequeño.\nY era muy, muy curioso.\nPero tenía un problema:\n¡Todo lo hacía demasiado\nrápido!")
        ->and($boxes[1]->align)->toBe(TextAlign::Center)
        ->and($boxes[1]->lineHeight)->toBe(1.88)
        ->and($boxes[1]->sizePt)->toBe(16.0);
});

it('coloca el cuadro donde estaba el texto, con holgura para la fuente sustituta', function () {
    $box = builder()->build(timoPage7(), 612, 792)[1];
    $mm = fn (float $pt) => $pt * 25.4 / 72;

    // El párrafo original va de x=184 a x=424 pt; el cuadro lo contiene con holgura a ambos lados.
    expect($box->box->x)->toBeLessThan($mm(184.0))
        ->and($box->box->x + $box->box->width)->toBeGreaterThan($mm(423.7))
        ->and($box->box->y)->toBeLessThan($mm(156.23))
        ->and($box->box->y + $box->box->height)->toBeGreaterThan($mm(295.7));
});

it('usa la fuente original si está disponible y si no una parecida', function () {
    expect(builder()->build(timoPage7(), 612, 792)[0]->font)->toBe('Open Sans')
        ->and(builder(['Libertinus Serif', 'Verdana'])->build(timoPage7(), 612, 792)[0]->font)->toBe('Verdana');
});

it('separa párrafos con distinto espaciado o tamaño', function () {
    $lines = [
        new TextLine('Uno', 72, 100, 200, 112, sizePt: 10),
        new TextLine('Dos', 72, 112, 200, 124, sizePt: 10),
        new TextLine('Lejos', 72, 300, 200, 312, sizePt: 10),
        new TextLine('Grande', 72, 330, 300, 360, sizePt: 24),
    ];

    expect(array_map(fn (TextBox $b): string => $b->plainText(), builder()->build($lines, 612, 792)))
        ->toBe(["Uno\nDos", 'Lejos', 'Grande']);
});

it('detecta alineación izquierda, derecha y justificada', function () {
    $left = [new TextLine('aaaa', 72, 100, 300, 112, sizePt: 10), new TextLine('bb', 72, 114, 150, 126, sizePt: 10)];
    $right = [new TextLine('aaaa', 72, 100, 300, 112, sizePt: 10), new TextLine('bb', 222, 114, 300, 126, sizePt: 10)];
    $justified = [
        new TextLine('aaaa aaaa', 72, 100, 300, 112, sizePt: 10),
        new TextLine('bbbb bbbb', 72, 114, 300.5, 126, sizePt: 10),
        new TextLine('fin', 72, 128, 120, 140, sizePt: 10),
    ];

    expect(builder()->build($left, 612, 792)[0]->align)->toBe(TextAlign::Left)
        ->and(builder()->build($right, 612, 792)[0]->align)->toBe(TextAlign::Right)
        ->and(builder()->build($justified, 612, 792)[0]->align)->toBe(TextAlign::Justify);
});

it('conserva palabras en negrita o de color dentro de la línea', function () {
    $line = new TextLine('Había una vez un dragón', 72, 100, 300, 112, [
        new TextRun('Había una vez un ', color: '#000000'),
        new TextRun('dragón', bold: true, color: '#e11d48'),
    ], 12, 'Verdana');

    $box = builder()->build([$line], 612, 792)[0];

    expect($box->color->hex)->toBe('#000000')
        ->and($box->spans)->toHaveCount(2)
        ->and($box->spans[1]->text)->toBe('dragón')
        ->and($box->spans[1]->bold)->toBeTrue()
        ->and($box->spans[1]->color?->hex)->toBe('#e11d48');
});

it('normaliza nombres de fuente de PDF', function (string $pdf, string $base) {
    expect(FontMatcher::baseName($pdf))->toBe($base);
})->with([
    ['AAAAAA+Verdana', 'Verdana'],
    ['BCDEFG+Verdana-Bold', 'Verdana'],
    ['TimesNewRomanPS-BoldMT', 'Times New Roman'],
    ['Arial,Italic', 'Arial'],
]);
