<?php

namespace App\Console\Commands;

use App\Domain\Imposition\ImpositionSettings;
use App\Domain\Printing\Pdf\PdfImposer;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImposeCommand extends Command
{
    use ParsesPrintingOptions;

    protected $signature = 'noctua:impose
        {source : PDF de páginas del libro}
        {output? : PDF impuesto (por defecto <source>-impuesto.pdf)}
        {--sheets=4 : Hojas por cuadernillo (1–8)}
        {--short-last : Acortar el último cuadernillo en lugar de rellenar con blancos}
        {--creep=0 : Compensación de creep en mm por hoja}
        {--front-offset=0,0 : Offset X,Y del frente en mm}
        {--back-offset=0,0 : Offset X,Y del dorso en mm}
        {--unprintable=5 : Zona no imprimible de la impresora en mm}
        {--no-fold-marks : No dibujar marcas de doblez}';

    protected $description = 'Impone un PDF en hojas carta horizontales 2-up por cuadernillos';

    public function handle(PdfImposer $imposer): int
    {
        $source = $this->stringArgument('source');

        if (! is_file($source)) {
            $this->components->error("No existe el archivo {$source}.");

            return self::FAILURE;
        }

        $output = $this->argument('output');
        $output = is_string($output) ? $output : preg_replace('/\.pdf$/i', '', $source).'-impuesto.pdf';

        try {
            $plan = $imposer->impose(
                $source,
                $output,
                new ImpositionSettings(
                    sheetsPerSignature: $this->intOption('sheets'),
                    shortLastSignature: (bool) $this->option('short-last'),
                    creepMmPerSheet: $this->floatOption('creep'),
                ),
                new PrinterProfile(
                    frontOffset: $this->offsetOption('front-offset'),
                    backOffset: $this->offsetOption('back-offset'),
                    unprintableMarginMm: $this->floatOption('unprintable'),
                ),
                new PrintOptions(foldMarks: ! $this->option('no-fold-marks')),
            );
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("PDF impuesto: {$output}");
        $this->components->twoColumnDetail('Páginas', (string) $plan->spec->pageCount);
        $this->components->twoColumnDetail('Blancos de relleno', (string) $plan->blankPageCount());
        $this->components->twoColumnDetail('Cuadernillos', (string) count($plan->signatures));
        $this->components->twoColumnDetail('Hojas carta', (string) $plan->sheetCount());
        $this->components->twoColumnDetail('Caras a imprimir', (string) (2 * $plan->sheetCount()));

        $rows = [];
        $face = 1;
        foreach ($plan->signatures as $signature) {
            $faces = 2 * count($signature->sheets);
            $rows[] = [$signature->number, "{$signature->firstPage}–{$signature->lastPage()}", count($signature->sheets), $face.'–'.($face + $faces - 1)];
            $face += $faces;
        }

        $this->newLine();
        $this->table(['Cuadernillo', 'Páginas', 'Hojas', 'Caras del PDF'], $rows);

        $this->components->bulletList([
            'Imprime a tamaño real (100 %), sin "Ajustar a página".',
            'Dúplex con volteo por el borde corto.',
            'Dobla cada hoja por las marcas centrales y anida las hojas de cada cuadernillo en orden.',
        ]);

        return self::SUCCESS;
    }
}
