<?php

namespace App\Domain\Printing\Pdf;

use App\Domain\Printing\Offset;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\Side;
use App\Domain\Printing\Size;

/**
 * Hoja de calibración de dos caras para imprimir en dúplex:
 *  - reglas de 100 mm en ambos ejes (detecta escalado del driver),
 *  - escalas de borde de 1 a 10 mm sin offset (miden la zona no imprimible),
 *  - cruces de registro simétricas respecto al doblez: al trasluz, las del frente
 *    y el dorso coinciden si el registro es correcto,
 *  - retícula milimetrada en la cruz central del dorso para leer la desviación.
 * Aplica los offsets del perfil, así sirve también para verificar una calibración.
 */
final class CalibrationSheet
{
    private const float CROSS_INSET_MM = 15.0;

    public function render(string $outputPath, PrinterProfile $printer = new PrinterProfile, ?Size $sheet = null): void
    {
        $sheet ??= Size::letterLandscape();
        $pdf = new DeterministicFpdi($sheet);

        foreach ([Side::Front, Side::Back] as $side) {
            $pdf->AddPage();
            $offset = $printer->offsetFor($side);
            $pdf->SetDrawColor(0);
            $pdf->SetTextColor(0);

            $this->registrationCrosses($pdf, $sheet, $offset);
            $this->edgeScales($pdf, $sheet);

            if ($side === Side::Front) {
                $this->rulers($pdf, $sheet, $offset);
                $this->label($pdf, $offset, 'FRENTE', [
                    'Imprime a tamaño real (100 %), dúplex con volteo por el borde corto.',
                    'Las reglas deben medir exactamente 100 mm; si no, desactiva "Ajustar a página".',
                    'En cada borde, la primera marca visible completa indica tu zona no imprimible.',
                ]);
            } else {
                $this->vernierGrid($pdf, $sheet, $offset);
                $this->label($pdf, $offset, 'DORSO', [
                    'Si este texto sale invertido, cambia el tipo de volteo del dúplex.',
                    'Mira la hoja al trasluz: la cruz central del frente debe caer sobre la de esta cara.',
                    'La retícula está en mm: anota la desviación en X y en Y y úsala como offset del dorso.',
                ]);
            }
        }

        $pdf->Output('F', $outputPath);
    }

    private function registrationCrosses(DeterministicFpdi $pdf, Size $sheet, Offset $o): void
    {
        $i = self::CROSS_INSET_MM;
        $points = [
            [$i, $i], [$sheet->widthMm - $i, $i],
            [$i, $sheet->heightMm - $i], [$sheet->widthMm - $i, $sheet->heightMm - $i],
            [$sheet->widthMm / 2, $sheet->heightMm / 2],
        ];

        $pdf->SetLineWidth(0.1);
        foreach ($points as [$x, $y]) {
            $x += $o->xMm;
            $y += $o->yMm;
            $pdf->Line($x - 5, $y, $x + 5, $y);
            $pdf->Line($x, $y - 5, $x, $y + 5);
        }
    }

    /**
     * Medidas desde el borde físico: no se les aplica offset.
     */
    private function edgeScales(DeterministicFpdi $pdf, Size $sheet): void
    {
        $pdf->SetLineWidth(0.15);
        $pdf->SetFont('Helvetica', '', 5);
        $midX = $sheet->widthMm / 2 + 30;
        $midY = $sheet->heightMm / 2 + 30;

        for ($mm = 1; $mm <= 10; $mm++) {
            $len = $mm % 5 === 0 ? 5.0 : 3.0;
            $pdf->Line($mm, $midY, $mm, $midY + $len);
            $pdf->Line($sheet->widthMm - $mm, $midY, $sheet->widthMm - $mm, $midY + $len);
            $pdf->Line($midX, $mm, $midX + $len, $mm);
            $pdf->Line($midX, $sheet->heightMm - $mm, $midX + $len, $sheet->heightMm - $mm);

            if ($mm % 5 === 0) {
                $pdf->Text($mm - 0.8, $midY + $len + 2.2, (string) $mm);
                $pdf->Text($sheet->widthMm - $mm - 0.8, $midY + $len + 2.2, (string) $mm);
                $pdf->Text($midX + $len + 0.8, $mm + 0.8, (string) $mm);
                $pdf->Text($midX + $len + 0.8, $sheet->heightMm - $mm + 0.8, (string) $mm);
            }
        }
    }

    private function rulers(DeterministicFpdi $pdf, Size $sheet, Offset $o): void
    {
        $pdf->SetLineWidth(0.1);
        $pdf->SetFont('Helvetica', '', 5);

        $x0 = ($sheet->widthMm - 100) / 2 + $o->xMm;
        $y0 = 32 + $o->yMm;
        $pdf->Line($x0, $y0, $x0 + 100, $y0);
        for ($mm = 0; $mm <= 100; $mm++) {
            $pdf->Line($x0 + $mm, $y0, $x0 + $mm, $y0 + $this->tick($mm));
            if ($mm % 10 === 0) {
                $pdf->Text($x0 + $mm - 1, $y0 + 6.5, (string) $mm);
            }
        }

        $vx = $sheet->widthMm - 45 + $o->xMm;
        $vy = ($sheet->heightMm - 100) / 2 + $o->yMm;
        $pdf->Line($vx, $vy, $vx, $vy + 100);
        for ($mm = 0; $mm <= 100; $mm++) {
            $pdf->Line($vx, $vy + $mm, $vx + $this->tick($mm), $vy + $mm);
            if ($mm % 10 === 0) {
                $pdf->Text($vx + 5, $vy + $mm + 0.8, (string) $mm);
            }
        }
    }

    private function vernierGrid(DeterministicFpdi $pdf, Size $sheet, Offset $o): void
    {
        $cx = $sheet->widthMm / 2 + $o->xMm;
        $cy = $sheet->heightMm / 2 + $o->yMm;
        $pdf->SetFont('Helvetica', '', 4);

        for ($d = -5; $d <= 5; $d++) {
            $pdf->SetLineWidth($d === 0 ? 0.15 : 0.05);
            $pdf->Line($cx + $d, $cy - 5, $cx + $d, $cy + 5);
            $pdf->Line($cx - 5, $cy + $d, $cx + 5, $cy + $d);
        }

        foreach ([-5, 5] as $d) {
            $pdf->Text($cx + $d - 1, $cy - 6, sprintf('%+d', $d));
            $pdf->Text($cx + 6, $cy + $d + 0.6, sprintf('%+d', $d));
        }
    }

    /**
     * @param  list<string>  $lines
     */
    private function label(DeterministicFpdi $pdf, Offset $o, string $title, array $lines): void
    {
        $x = 40 + $o->xMm;
        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->Text($x, 70 + $o->yMm, DeterministicFpdi::latin1($title));
        $pdf->SetFont('Helvetica', '', 8);

        foreach ($lines as $n => $line) {
            $pdf->Text($x, 80 + $o->yMm + 5 * $n, DeterministicFpdi::latin1($line));
        }
    }

    private function tick(int $mm): float
    {
        return match (true) {
            $mm % 10 === 0 => 4.0,
            $mm % 5 === 0 => 2.5,
            default => 1.5,
        };
    }
}
