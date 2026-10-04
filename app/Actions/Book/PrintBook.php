<?php

namespace App\Actions\Book;

use App\Actions\Imposition\ImposeUploadedPdf;
use App\Actions\Imposition\StoredImposition;
use App\Domain\Book\FolioSettings;
use App\Domain\Book\PageSequence;
use App\Domain\Imposition\ImpositionSettings;
use App\Domain\Printing\Pdf\PdfComposer;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use App\Domain\Printing\SheetLayout;
use App\Domain\Printing\Size;
use App\Domain\Typesetting\Typst\PageLayers;
use App\Fonts\FontLibrary;
use App\Models\Book;
use App\Models\User;

/**
 * Compone el libro editado (orden, blancos, fondos, textos, folios) e impone el resultado.
 */
final readonly class PrintBook
{
    public function __construct(
        private PdfComposer $composer,
        private PageLayers $layers,
        private FontLibrary $fonts,
        private TextlessPage $textless,
        private ImposeUploadedPdf $impose,
    ) {}

    public function handle(User $user, Book $book, ImpositionSettings $settings, PrinterProfile $printer, PrintOptions $options): StoredImposition
    {
        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'noctua-libro-'.bin2hex(random_bytes(6));

        try {
            $this->compose($user, $book, $printer, $options, "{$tmp}.pdf");

            return $this->impose->handle($book->publisher, "{$tmp}.pdf", $settings, $printer, $options, "{$book->title}.pdf");
        } finally {
            if (is_file("{$tmp}.pdf")) {
                unlink("{$tmp}.pdf");
            }
        }
    }

    /**
     * PDF de páginas del libro, ya editado, sin imponer.
     */
    /**
     * @param  PageSequence|null  $sequence  Páginas a componer; por defecto, las guardadas.
     * @param  list<int>|null  $only  Posiciones a incluir (vista previa de un pliego).
     */
    public function compose(User $user, Book $book, PrinterProfile $printer, PrintOptions $options, string $outputPath, ?PageSequence $sequence = null, ?FolioSettings $folios = null, ?array $only = null): void
    {
        $sequence ??= $book->sequence();
        $folios ??= $book->folioSettings();
        $page = $book->pageSize();
        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'noctua-capas-'.bin2hex(random_bytes(6));
        $under = $this->layers->needsUnder($sequence) ? "{$tmp}-debajo.pdf" : null;
        $over = $this->layers->needsOver($sequence, $folios) ? "{$tmp}-encima.pdf" : null;

        try {
            // Escala con la que la página llegará al papel, para que el folio salga al tamaño pedido.
            $scale = (new SheetLayout(Size::letterLandscape(), $page, $printer, $options->pageFit))->scale;
            $fontPaths = $this->fonts->typstPaths($book->publisher);

            if ($under !== null) {
                $this->layers->render('debajo', $sequence, $folios, $page, $scale, $fontPaths, $under);
            }

            if ($over !== null) {
                $this->layers->render('encima', $sequence, $folios, $page, $scale, $fontPaths, $over);
            }

            $textless = [];
            foreach ($sequence->items as $index => $item) {
                if ($item->sourcePage !== null && $item->design->hideSourceText && ($only === null || in_array($index + 1, $only, true))) {
                    $textless[$item->sourcePage] ??= $this->textless->handle($book, $item->sourcePage);
                }
            }

            $this->composer->compose($book->sourcePath(), $sequence, $outputPath, $over, $under, $only, $textless);
        } finally {
            foreach ([$under, $over] as $file) {
                if ($file !== null && is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
