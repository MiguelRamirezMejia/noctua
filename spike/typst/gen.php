<?php
// Genera un libro sintético en español: N capítulos del AST normalizado.
declare(strict_types=1);

$chapters = (int) ($argv[1] ?? 16);
$paragraphsPerChapter = (int) ($argv[2] ?? 70);
mt_srand(20260927);

$sentences = [
    'La lluvia había vuelto a los tejados de la ciudad antigua con una obstinación casi administrativa.',
    'Nadie en la imprenta recordaba cuándo se había encendido por última vez la vieja prensa de platina.',
    '—No es el papel lo que envejece —dijo la encuadernadora—, somos nosotros los que dejamos de leerlo.',
    'Las hojas, dobladas en cuadernillos de dieciséis páginas, esperaban sobre la mesa como pájaros dormidos.',
    'Desarrollaron entonces un procedimiento extraordinariamente meticuloso para coser los pliegos a mano.',
    'El aprendiz contempló la tipografía, la desproporción de los márgenes y la irregularidad del interlineado.',
    'Había en aquella casa una biblioteca desordenada, llena de libros anotados por generaciones de lectores.',
    'Cuando llegó el invierno, la editorial publicó tres títulos, todos ellos compuestos, impresos y cosidos allí.',
    'Nunca supo explicar por qué la palabra «guillotina» le producía esa mezcla de respeto y melancolía.',
    'Los caracteres móviles, ordenados en cajas de madera, conservaban todavía el olor de la tinta grasa.',
    'Ella subrayaba con lápiz las frases incomprensibles, convencida de que algún día le revelarían su sentido.',
    'La responsabilidad de la corrección ortotipográfica recaía, como siempre, en la persona menos descansada.',
];

$inline = function (string $s) : array {
    $r = mt_rand(1, 100);
    if ($r <= 8) {
        $w = explode(' ', $s);
        $cut = mt_rand(1, count($w) - 1);
        return [
            ['type' => 'text', 'text' => implode(' ', array_slice($w, 0, $cut)).' '],
            ['type' => 'text', 'text' => implode(' ', array_slice($w, $cut)), 'marks' => ['italic']],
        ];
    }
    if ($r <= 11) {
        return [['type' => 'text', 'text' => 'CAPÍTULO de la memoria: ', 'marks' => ['smallcaps']], ['type' => 'text', 'text' => $s]];
    }
    return [['type' => 'text', 'text' => $s]];
};

@mkdir(__DIR__.'/../data', 0777, true);
$files = [];
$id = 0;
for ($c = 1; $c <= $chapters; $c++) {
    $blocks = [];
    for ($p = 0; $p < $paragraphsPerChapter; $p++) {
        if ($p > 0 && $p % 23 === 0) { $blocks[] = ['type' => 'scene_break', 'id' => 'b'.(++$id)]; continue; }
        $content = [];
        $n = mt_rand(2, 7);
        for ($k = 0; $k < $n; $k++) {
            $content = array_merge($content, $inline($sentences[mt_rand(0, count($sentences) - 1)]));
            $content[] = ['type' => 'text', 'text' => ' '];
        }
        if (mt_rand(1, 100) <= 6) {
            $content[] = ['type' => 'footnote', 'content' => [['type' => 'text', 'text' => 'Nota del editor: '.$sentences[mt_rand(0, 11)]]]];
        }
        $block = ['type' => 'paragraph', 'id' => 'b'.(++$id), 'content' => $content];
        if (mt_rand(1, 100) <= 4) {
            $block = ['type' => 'quote', 'id' => 'b'.(++$id), 'blocks' => [$block]];
        }
        $blocks[] = $block;
    }
    $name = sprintf('ch-%02d.json', $c);
    file_put_contents(__DIR__."/../data/$name", json_encode(['title' => "Capítulo $c", 'blocks' => $blocks], JSON_UNESCAPED_UNICODE));
    $files[] = "/data/$name";
}
file_put_contents(__DIR__.'/../data/book.json', json_encode(['chapters' => $files]));
file_put_contents(__DIR__.'/../data/meta.json', json_encode(['title' => 'El oficio de la tinta', 'author' => ['Autora de prueba']], JSON_UNESCAPED_UNICODE));
echo "ok: $chapters capítulos\n";
