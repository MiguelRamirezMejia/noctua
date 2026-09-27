<?php
// Spike Fase 0 — imposición 2-up con FPDI+FPDF y hoja de calibración.
// Uso: php spike.php impose <src.pdf> <out.pdf> [hojas_por_cuadernillo=4]
//      php spike.php calibrate <out.pdf>
declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use setasign\Fpdi\Fpdi;

const SHEET_W = 279.4;
const SHEET_H = 215.9;

/**
 * Plan puro: por cuadernillo de 4n páginas, hoja i ∈ [1..n].
 * Frente: izq = 4n−2i+2, der = 2i−1 · Dorso: izq = 2i, der = 4n−2i+1.
 * null = página en blanco de relleno.
 *
 * @return list<array{sig:int, sheet:int, front:array{?int,?int}, back:array{?int,?int}}>
 */
function plan(int $pages, int $n): array
{
    $per = 4 * $n;
    $sigs = intdiv($pages + $per - 1, $per);
    $out = [];
    for ($s = 0; $s < $sigs; $s++) {
        $o = $s * $per;
        $p = fn (int $k): ?int => $o + $k <= $pages ? $o + $k : null;
        for ($i = 1; $i <= $n; $i++) {
            $out[] = [
                'sig' => $s + 1, 'sheet' => $i,
                'front' => [$p($per - 2 * $i + 2), $p(2 * $i - 1)],
                'back' => [$p(2 * $i), $p($per - 2 * $i + 1)],
            ];
        }
    }

    return $out;
}

final class DeterministicFpdi extends Fpdi
{
    public function __construct()
    {
        parent::__construct('L', 'mm', [SHEET_H, SHEET_W]);
        $this->SetAutoPageBreak(false);
        $this->SetMargins(0, 0, 0);
        $this->SetCreator('Noctua');
    }

    // FPDF fuerza CreationDate = time() en _enddoc(); fijamos la fecha (UTC) para salida determinista.
    protected function _putinfo(): void
    {
        $this->metadata['CreationDate'] = 'D:'.gmdate('YmdHis', 0).'Z';
        foreach ($this->metadata as $key => $value) {
            $this->_put('/'.$key.' '.$this->_textstring($value));
        }
    }
}

function foldMarks(Fpdi $pdf): void
{
    // Ticks cortos en el eje del doblez, fuera de la zona no imprimible (~5 mm).
    $pdf->SetLineWidth(0.15);
    $x = SHEET_W / 2;
    $pdf->Line($x, 5.5, $x, 9.5);
    $pdf->Line($x, SHEET_H - 9.5, $x, SHEET_H - 5.5);
}

function impose(string $src, string $dst, int $n): void
{
    $pdf = new DeterministicFpdi();
    $count = $pdf->setSourceFile($src);
    $tpl = [];
    $side = function (array $pair) use ($pdf, &$tpl): void {
        $pdf->AddPage();
        foreach ($pair as $slot => $page) {
            if ($page === null) {
                continue;
            }
            $tpl[$page] ??= $pdf->importPage($page);
            $size = $pdf->getTemplateSize($tpl[$page]);
            $pdf->useTemplate($tpl[$page], $slot * SHEET_W / 2, 0, $size['width'], $size['height']);
        }
        foldMarks($pdf);
    };
    foreach (plan($count, $n) as $sheet) {
        $side($sheet['front']);
        $side($sheet['back']);
    }
    $pdf->Output('F', $dst);
}

function calibrate(string $dst): void
{
    $pdf = new DeterministicFpdi();
    $pdf->AddPage();
    $pdf->SetLineWidth(0.1);
    // Cruces de registro a 10 mm de cada esquina y en el centro.
    foreach ([[10, 10], [SHEET_W - 10, 10], [10, SHEET_H - 10], [SHEET_W - 10, SHEET_H - 10], [SHEET_W / 2, SHEET_H / 2]] as [$x, $y]) {
        $pdf->Line($x - 4, $y, $x + 4, $y);
        $pdf->Line($x, $y - 4, $x, $y + 4);
    }
    // Regla de 100 mm para detectar escalado del driver.
    $x0 = (SHEET_W - 100) / 2;
    $y0 = 40;
    $pdf->Line($x0, $y0, $x0 + 100, $y0);
    for ($mm = 0; $mm <= 100; $mm++) {
        $len = $mm % 10 === 0 ? 4 : ($mm % 5 === 0 ? 2.5 : 1.5);
        $pdf->Line($x0 + $mm, $y0, $x0 + $mm, $y0 + $len);
    }
    // Escalas de borde: marcas cada 1 mm desde el borde (0–10 mm) para medir la zona no imprimible.
    for ($mm = 1; $mm <= 10; $mm++) {
        $pdf->Line($mm, SHEET_H / 2 - 20, $mm, SHEET_H / 2 - 15);
        $pdf->Line(SHEET_W - $mm, SHEET_H / 2 - 20, SHEET_W - $mm, SHEET_H / 2 - 15);
        $pdf->Line(SHEET_W / 2 + 20, $mm, SHEET_W / 2 + 25, $mm);
        $pdf->Line(SHEET_W / 2 + 20, SHEET_H - $mm, SHEET_W / 2 + 25, SHEET_H - $mm);
    }
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetXY($x0, $y0 + 6);
    $pdf->Cell(100, 5, mb_convert_encoding('Regla de 100 mm: mídela. Si no mide 100 mm, desactiva "Ajustar a página".', 'Windows-1252', 'UTF-8'), 0, 0, 'C');
    $pdf->SetFont('Helvetica', 'B', 28);
    $pdf->SetXY(0, 80);
    $pdf->Cell(SHEET_W, 12, mb_convert_encoding('FRENTE  ->  1', 'Windows-1252', 'UTF-8'), 0, 0, 'C');
    // Dorso: prueba de volteo. Con borde corto correcto, "DORSO" queda derecho detrás de "FRENTE".
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', 'B', 28);
    $pdf->SetXY(0, 80);
    $pdf->Cell(SHEET_W, 12, mb_convert_encoding('2  <-  DORSO', 'Windows-1252', 'UTF-8'), 0, 0, 'C');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetXY(0, 100);
    $pdf->Cell(SHEET_W, 6, mb_convert_encoding('Si este texto está invertido, cambia el volteo a "borde corto" (o viceversa).', 'Windows-1252', 'UTF-8'), 0, 0, 'C');
    $pdf->Output('F', $dst);
}

match ($argv[1] ?? '') {
    'impose' => impose($argv[2], $argv[3], (int) ($argv[4] ?? 4)),
    'calibrate' => calibrate($argv[2]),
    'plan' => print(json_encode(plan((int) $argv[2], (int) $argv[3]))."\n"),
    default => fwrite(STDERR, "uso: impose|calibrate|plan\n"),
};
