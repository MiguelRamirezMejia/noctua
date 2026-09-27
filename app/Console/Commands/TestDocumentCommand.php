<?php

namespace App\Console\Commands;

use App\Domain\Printing\Pdf\TestDocument;
use Illuminate\Console\Command;
use InvalidArgumentException;

class TestDocumentCommand extends Command
{
    use ParsesPrintingOptions;

    protected $signature = 'noctua:test-document
        {output=prueba.pdf : PDF de salida}
        {--pages=16 : Número de páginas}';

    protected $description = 'Genera un PDF media carta de páginas numeradas para probar la imposición';

    public function handle(TestDocument $document): int
    {
        try {
            $pages = $this->intOption('pages');

            if ($pages < 1) {
                throw new InvalidArgumentException('--pages debe ser al menos 1.');
            }

            $output = $this->stringArgument('output');
            $document->render($output, $pages);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Documento de prueba de {$pages} páginas: {$output}");

        return self::SUCCESS;
    }
}
