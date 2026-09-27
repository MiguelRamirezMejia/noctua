<?php

use App\Domain\Imposition\Imposer;
use App\Domain\Imposition\ImpositionPlan;
use App\Domain\Imposition\ImpositionSpec;
use App\Domain\Imposition\InvalidImpositionSpec;
use App\Domain\Imposition\Sheet;
use App\Domain\Imposition\Signature;

function plan(int $pages, int $n = 4, bool $short = false, float $creep = 0.0): ImpositionPlan
{
    return (new Imposer)->plan(new ImpositionSpec($pages, $n, $short, $creep));
}

/**
 * Simula el cuadernillo doblado y anidado (hoja 1 por fuera) y devuelve las
 * páginas en el orden en que se leen. No usa la fórmula del Imposer.
 *
 * @return list<?int>
 */
function readFolded(Signature $signature): array
{
    $order = [];
    foreach ($signature->sheets as $sheet) {
        $order[] = $sheet->front->right->page;
        $order[] = $sheet->back->left->page;
    }
    foreach (array_reverse($signature->sheets) as $sheet) {
        $order[] = $sheet->back->right->page;
        $order[] = $sheet->front->left->page;
    }

    return $order;
}

/**
 * @return list<?int>
 */
function allSlots(ImpositionPlan $plan): array
{
    return array_merge(...array_map(
        fn (Sheet $s): array => [...$s->front->pages(), ...$s->back->pages()],
        $plan->sheets(),
    ));
}

it('impone un pliego de una hoja como 4|1 y 2|3', function () {
    $sheet = plan(4, 1)->sheets()[0];

    expect($sheet->front->pages())->toBe([4, 1])
        ->and($sheet->back->pages())->toBe([2, 3]);
});

it('impone un cuadernillo de cuatro hojas', function () {
    $sides = array_map(
        fn (Sheet $s): array => [$s->front->pages(), $s->back->pages()],
        plan(16, 4)->sheets(),
    );

    expect($sides)->toBe([
        [[16, 1], [2, 15]],
        [[14, 3], [4, 13]],
        [[12, 5], [6, 11]],
        [[10, 7], [8, 9]],
    ]);
});

it('coloca cada página exactamente una vez y rellena el resto con blancos', function (int $pages, int $n, bool $short) {
    $plan = plan($pages, $n, $short);
    $slots = allSlots($plan);
    $real = array_values(array_filter($slots, fn (?int $p): bool => $p !== null));
    sort($real);

    expect($real)->toBe(range(1, $pages))
        ->and(count($slots))->toBe($plan->paddedPageCount())
        ->and($plan->blankPageCount())->toBe(count($slots) - $pages);
})->with('totales');

it('se lee en orden al doblar y anidar las hojas', function (int $pages, int $n, bool $short) {
    $plan = plan($pages, $n, $short);
    $read = array_merge(...array_map(readFolded(...), $plan->signatures));
    $expected = array_map(fn (int $p): ?int => $p <= $pages ? $p : null, range(1, $plan->paddedPageCount()));

    expect($read)->toBe($expected);
})->with('totales');

it('rellena hasta múltiplo de 4n cuando el último cuadernillo no se acorta', function (int $pages, int $n) {
    $plan = plan($pages, $n);

    expect($plan->paddedPageCount() % (4 * $n))->toBe(0)
        ->and($plan->blankPageCount())->toBeLessThan(4 * $n)
        ->and(collect($plan->signatures)->every(fn (Signature $s): bool => count($s->sheets) === $n))->toBeTrue();
})->with([[1, 4], [15, 4], [17, 4], [30, 2], [326, 4], [5, 1]]);

it('acorta el último cuadernillo a las hojas necesarias', function (int $pages, int $n, array $sheetsPerSignature, int $blanks) {
    $plan = plan($pages, $n, short: true);

    expect(array_map(fn (Signature $s): int => count($s->sheets), $plan->signatures))->toBe($sheetsPerSignature)
        ->and($plan->blankPageCount())->toBe($blanks);
})->with([
    'justo 4n' => [16, 4, [4], 0],
    'sobra una hoja' => [20, 4, [4, 1], 0],
    'sobran dos páginas' => [18, 4, [4, 1], 2],
    'sobran 13 páginas' => [29, 4, [4, 4], 3],
    'libro de 326 págs.' => [326, 4, [4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 4, 2], 2],
]);

it('numera los cuadernillos y su primera página', function () {
    $plan = plan(40, 2);

    expect(array_map(fn (Signature $s): array => [$s->number, $s->firstPage, $s->lastPage()], $plan->signatures))
        ->toBe([[1, 1, 8], [2, 9, 16], [3, 17, 24], [4, 25, 32], [5, 33, 40]]);
});

it('acumula el creep hacia el interior del cuadernillo', function () {
    $creeps = array_map(
        fn (Sheet $s): array => [$s->front->left->creepMm, $s->front->right->creepMm, $s->back->left->creepMm, $s->back->right->creepMm],
        plan(16, 4, creep: 0.25)->sheets(),
    );

    expect($creeps)->toBe([
        [0.0, 0.0, 0.0, 0.0],
        [0.25, 0.25, 0.25, 0.25],
        [0.5, 0.5, 0.5, 0.5],
        [0.75, 0.75, 0.75, 0.75],
    ]);
});

it('rechaza especificaciones inválidas', function (int $pages, int $n, float $creep) {
    new ImpositionSpec($pages, $n, creepMmPerSheet: $creep);
})->throws(InvalidImpositionSpec::class)->with([
    'sin páginas' => [0, 4, 0.0],
    'cero hojas' => [16, 0, 0.0],
    'nueve hojas' => [16, 9, 0.0],
    'creep negativo' => [16, 4, -0.1],
]);

dataset('totales', function () {
    foreach ([1, 2, 3, 4, 5, 8] as $n) {
        foreach ([1, 3, 4, 4 * $n - 1, 4 * $n, 4 * $n + 1, 37, 326] as $pages) {
            foreach ([false, true] as $short) {
                yield "{$pages} págs., n={$n}".($short ? ', corto' : '') => [$pages, $n, $short];
            }
        }
    }
});
