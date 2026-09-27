<?php

namespace App\Console\Commands;

use App\Domain\Printing\Pdf\CalibrationSheet;
use App\Domain\Printing\PrinterProfile;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CalibrationCommand extends Command
{
    use ParsesPrintingOptions;

    protected $signature = 'noctua:calibration
        {output=calibracion.pdf : PDF de salida}
        {--front-offset=0,0 : Offset X,Y del frente en mm}
        {--back-offset=0,0 : Offset X,Y del dorso en mm}';

    protected $description = 'Genera la hoja de calibración de la impresora (frente y dorso)';

    public function handle(CalibrationSheet $sheet): int
    {
        $output = $this->stringArgument('output');

        try {
            $sheet->render($output, new PrinterProfile(
                frontOffset: $this->offsetOption('front-offset'),
                backOffset: $this->offsetOption('back-offset'),
            ));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Hoja de calibración: {$output}");
        $this->components->bulletList([
            'Imprime a tamaño real (100 %), dúplex con volteo por el borde corto.',
            'Mide las reglas de 100 mm y cuenta las marcas de borde visibles.',
            'Al trasluz, lee en la retícula del dorso la desviación de la cruz central del frente.',
            'Vuelve a generar la hoja con --back-offset=X,Y para verificar la corrección.',
        ]);

        return self::SUCCESS;
    }
}
