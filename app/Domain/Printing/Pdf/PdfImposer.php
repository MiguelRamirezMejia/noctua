<?php

namespace App\Domain\Printing\Pdf;

use App\Domain\Imposition\Imposer;
use App\Domain\Imposition\ImpositionPlan;
use App\Domain\Imposition\ImpositionSettings;
use App\Domain\Printing\PageDoesNotFit;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use App\Domain\Printing\SheetLayout;
use App\Domain\Printing\Side;
use App\Domain\Printing\Size;

/**
 * Genera el PDF impuesto 2-up: por cada hoja, frente y dorso consecutivos
 * (dúplex automático, volteo por el borde corto).
 */
final class PdfImposer
{
    private const float FOLD_MARK_LENGTH_MM = 4.0;

    public function __construct(
        private readonly Imposer $imposer = new Imposer,
    ) {}

    public function impose(
        string $sourcePath,
        string $outputPath,
        ImpositionSettings $settings = new ImpositionSettings,
        PrinterProfile $printer = new PrinterProfile,
        PrintOptions $options = new PrintOptions,
        ?Size $sheet = null,
    ): ImpositionPlan {
        $sheet ??= Size::letterLandscape();
        $pdf = new DeterministicFpdi($sheet);

        try {
            $pageCount = $pdf->setSourceFile($sourcePath);

            $templates = [];
            for ($page = 1; $page <= $pageCount; $page++) {
                $templates[$page] = $pdf->importPage($page);
            }

            $layout = new SheetLayout($sheet, $this->uniformPageSize($pdf, $templates), $printer);
            $plan = $this->imposer->plan($settings->forPageCount($pageCount));

            foreach ($plan->sheets() as $physicalSheet) {
                foreach ([Side::Front, Side::Back] as $side) {
                    $pdf->AddPage();
                    $sheetSide = $side === Side::Front ? $physicalSheet->front : $physicalSheet->back;

                    foreach ($layout->place($sheetSide, $side) as $placement) {
                        $pdf->useTemplate($templates[$placement->page], $placement->xMm, $placement->yMm, $placement->widthMm, $placement->heightMm);
                    }

                    if ($options->foldMarks) {
                        $this->foldMarks($pdf, $layout, $side);
                    }
                }
            }

            $pdf->Output('F', $outputPath);
        } finally {
            // Los lectores de FPDI tienen referencias cíclicas: sin esto el PDF fuente
            // queda abierto hasta el siguiente ciclo del GC (y bloqueado en Windows).
            $pdf->cleanUp();
        }

        return $plan;
    }

    /**
     * @param  array<int, string>  $templates
     */
    private function uniformPageSize(DeterministicFpdi $pdf, array $templates): Size
    {
        $first = null;

        foreach ($templates as $page => $template) {
            $current = $this->templateSize($pdf, $template, $page);
            $first ??= $current;

            if (! $current->equals($first)) {
                throw new PageDoesNotFit(sprintf(
                    'Todas las páginas deben tener el mismo tamaño: la página %d mide %.1f × %.1f mm y la primera %.1f × %.1f mm.',
                    $page,
                    $current->widthMm,
                    $current->heightMm,
                    $first->widthMm,
                    $first->heightMm,
                ));
            }
        }

        return $first ?? throw new PageDoesNotFit('El documento no tiene páginas.');
    }

    private function templateSize(DeterministicFpdi $pdf, string $template, int $page): Size
    {
        $size = $pdf->getTemplateSize($template);

        if (! is_array($size) || ! is_numeric($size['width'] ?? null) || ! is_numeric($size['height'] ?? null)) {
            throw new PageDoesNotFit("No se pudo leer el tamaño de la página {$page}.");
        }

        return new Size((float) $size['width'], (float) $size['height']);
    }

    /**
     * Marcas cortas sobre el eje del doblez, justo dentro de la zona imprimible.
     */
    private function foldMarks(DeterministicFpdi $pdf, SheetLayout $layout, Side $side): void
    {
        $x = $layout->foldXMm($side);
        $top = $layout->printer->unprintableMarginMm + 0.5;
        $bottom = $layout->sheet->heightMm - $top;

        $pdf->SetDrawColor(0);
        $pdf->SetLineWidth(0.15);
        $pdf->Line($x, $top, $x, $top + self::FOLD_MARK_LENGTH_MM);
        $pdf->Line($x, $bottom - self::FOLD_MARK_LENGTH_MM, $x, $bottom);
    }
}
