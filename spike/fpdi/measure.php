<?php
// Localiza la línea de referencia vertical en cada mitad de una hoja renderizada (PGM P5).
[$w, $h, $px] = (function (string $f) {
    $d = file_get_contents($f);
    preg_match('/^P5\s+(\d+)\s+(\d+)\s+255\s/', $d, $m);
    return [(int) $m[1], (int) $m[2], substr($d, strlen($m[0]))];
})($argv[1]);
$dpi = (int) $argv[2];
$toMm = fn (float $p) => $p * 25.4 / $dpi;
$row = (int) ($h / 2 + 30 * $dpi / 25.4); // fila a media altura + 30 mm (evita el número grande)
$cols = [];
for ($x = 0; $x < $w; $x++) { if (ord($px[$row * $w + $x]) < 128) { $cols[] = $x; } }
// Agrupa píxeles contiguos y reporta el centro de cada línea.
$groups = []; foreach ($cols as $c) { if ($groups && end($groups)[1] === $c - 1) { $groups[array_key_last($groups)][1] = $c; } else { $groups[] = [$c, $c]; } }
$col = (int) (($w / 2) * 0.1);
$lineY = null; for ($y = 0; $y < $h / 3; $y++) { if (ord($px[$y * $w + (int)($w * 0.25)]) < 128) { $lineY = $y; break; } }
foreach ($groups as [$a, $b]) { printf("línea vertical x = %.3f mm\n", $toMm(($a + $b) / 2 + 0.5)); }
printf("línea horizontal y = %.3f mm (esperado 10.000)\n", $toMm($lineY + 0.5));
