<?php

namespace App\Actions\Book;

use App\Actions\Imposition\ImposeUploadedPdf;
use App\Actions\Imposition\StoredImposition;
use App\Domain\Imposition\ImpositionSettings;
use App\Domain\Printing\Pdf\PdfComposer;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use App\Domain\Printing\SheetLayout;
use App\Domain\Printing\Size;
use App\Domain\Typesetting\Typst\FolioOverlay;
use App\Models\Book;
use App\Models\User;

/**
 * Compone el libro editado (orden, blancos, folios) e impone el resultado.
 */
final readonly class PrintBook
{
    public function __construct(
        private PdfComposer $composer,
        private FolioOverlay $folios,
        private ImposeUploadedPdf $impose,
    ) {}

    public function handle(User $user, Book $book, ImpositionSettings $settings, PrinterProfile $printer, PrintOptions $options): StoredImposition
    {
        $sequence = $book->sequence();
        $folioSettings = $book->folioSettings();
        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'noctua-libro-'.bin2hex(random_bytes(6));
        $overlay = null;

        try {
            if ($sequence->folios($folioSettings) !== []) {
                // Escala con la que la página llegará al papel, para que el folio salga al tamaño pedido.
                $scale = (new SheetLayout(Size::letterLandscape(), $book->pageSize(), $printer, $options->pageFit))->scale;
                $overlay = "{$tmp}-folios.pdf";
                $this->folios->render($sequence, $folioSettings, $book->pageSize(), $scale, $overlay);
            }

            $this->composer->compose($book->sourcePath(), $sequence, "{$tmp}.pdf", $overlay);

            return $this->impose->handle($user, "{$tmp}.pdf", $settings, $printer, $options, "{$book->title}.pdf");
        } finally {
            foreach (["{$tmp}.pdf", "{$tmp}-folios.pdf"] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
