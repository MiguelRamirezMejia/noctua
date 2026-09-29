<?php

namespace App\Actions\Book;

use App\Domain\Book\FolioSettings;
use App\Domain\Book\PageSequence;
use App\Domain\Printing\Pdf\PdfComposer;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final readonly class CreateBookFromPdf
{
    public function __construct(
        private PdfComposer $composer,
        private RenderThumbnails $thumbnails,
    ) {}

    public function handle(User $user, UploadedFile $pdf, ?string $title = null): Book
    {
        $path = $pdf->getRealPath() ?: throw new \RuntimeException('No se pudo leer el archivo subido.');
        $pageCount = $this->composer->pageCount($path);
        $size = $this->composer->pageSize($path);
        $filename = $pdf->getClientOriginalName();

        $book = new Book([
            'title' => $title ?: pathinfo(preg_replace('/(\.pdf)+$/i', '', $filename) ?? $filename, PATHINFO_FILENAME),
            'source_filename' => $filename,
            'source_page_count' => $pageCount,
            'source_width_mm' => round($size->widthMm, 2),
            'source_height_mm' => round($size->heightMm, 2),
        ]);
        $book->setSequence(PageSequence::fromSource($pageCount));
        $book->setFolioSettings(new FolioSettings);
        $book->user()->associate($user);

        return DB::transaction(function () use ($book, $pdf): Book {
            $book->save();
            $disk = Storage::disk('local');

            try {
                $disk->putFileAs($book->directory(), $pdf, 'origen.pdf');
                $this->thumbnails->handle($book);
            } catch (Throwable $e) {
                $disk->deleteDirectory($book->directory());

                throw $e;
            }

            return $book;
        });
    }
}
