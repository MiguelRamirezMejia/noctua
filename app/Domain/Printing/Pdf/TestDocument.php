<?php

namespace App\Domain\Printing\Pdf;

use App\Domain\Printing\Size;

/**
 * Documento de prueba: páginas numeradas en grande, con la cabeza marcada y el
 * lado del lomo indicado, para verificar el orden y la orientación tras doblar.
 */
final class TestDocument
{
    public function render(string $outputPath, int $pages = 16, ?Size $page = null): void
    {
        $page ??= Size::halfLetter();
        $pdf = new DeterministicFpdi($page);

        for ($n = 1; $n <= $pages; $n++) {
            $pdf->AddPage();
            $pdf->SetDrawColor(160);
            $pdf->SetLineWidth(0.2);
            $pdf->Rect(12, 12, $page->widthMm - 24, $page->heightMm - 24);

            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetXY(0, 15);
            $pdf->Cell($page->widthMm, 5, 'CABEZA', 0, 0, 'C');

            // Páginas impares a la derecha del pliego: el lomo queda a su izquierda.
            $spine = $n % 2 === 1 ? 'L' : 'R';
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetXY(14, $page->heightMm / 2);
            $pdf->Cell($page->widthMm - 28, 5, 'lomo', 0, 0, $spine);

            $pdf->SetFont('Helvetica', 'B', 96);
            $pdf->SetXY(0, $page->heightMm / 2 - 25);
            $pdf->Cell($page->widthMm, 40, (string) $n, 0, 0, 'C');

            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetXY(0, $page->heightMm - 22);
            $pdf->Cell($page->widthMm, 5, DeterministicFpdi::latin1("Página {$n}"), 0, 0, 'C');
        }

        $pdf->Output('F', $outputPath);
    }
}
