<?php

namespace App\Http\Controllers;

use App\Actions\Book\CreateBookFromPdf;
use App\Actions\Book\PrintBook;
use App\Domain\Book\FolioPosition;
use App\Domain\Book\PageItem;
use App\Domain\Printing\PageFit;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\SheetLayout;
use App\Domain\Printing\Size;
use App\Domain\Typesetting\TypesettingFailed;
use App\Fonts\FontLibrary;
use App\Http\PrintDefaults;
use App\Http\Requests\BookContentRequest;
use App\Http\Requests\PrintSettingsRequest;
use App\Models\Book;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        return Inertia::render('books/Index', [
            'books' => Book::query()
                ->whereBelongsTo($user)
                ->latest()
                ->get()
                ->map(fn (Book $book): array => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'page_count' => count($book->pages),
                    'source_page_count' => $book->source_page_count,
                    'size' => sprintf('%.0f × %.0f mm', (float) $book->source_width_mm, (float) $book->source_height_mm),
                    'cover_url' => route('books.thumbnail', [$book, $book->sequence()->items[0]->sourcePage ?? 1]),
                    'updated_at' => $book->updated_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request, CreateBookFromPdf $create, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:512000'],
            'title' => ['nullable', 'string', 'max:200'],
        ], attributes: ['pdf' => 'PDF', 'title' => 'título']);

        $pdf = $request->file('pdf');

        if (! $pdf instanceof UploadedFile) {
            throw ValidationException::withMessages(['pdf' => 'Sube un archivo PDF.']);
        }

        try {
            $book = $create->handle($user, $pdf, $request->string('title')->trim()->toString() ?: null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['pdf' => $e->getMessage()]);
        }

        return to_route('books.edit', $book);
    }

    public function edit(Request $request, Book $book, FontLibrary $library, #[CurrentUser] User $user): Response
    {
        Gate::authorize('view', $book);

        $folios = $book->folioSettings();

        return Inertia::render('books/Edit', [
            'book' => [
                'id' => $book->id,
                'title' => $book->title,
                'source_filename' => $book->source_filename,
                'source_page_count' => $book->source_page_count,
                'width_mm' => (float) $book->source_width_mm,
                'height_mm' => (float) $book->source_height_mm,
                'pages' => array_map(fn (PageItem $item): array => [
                    'id' => $item->id,
                    'source' => $item->sourcePage,
                    'folio' => $item->showFolio,
                    'design' => $item->design->toArray(),
                ], $book->sequence()->items),
                'folios' => [
                    'enabled' => $folios->enabled,
                    'first_numbered_page' => $folios->firstNumberedPage,
                    'start_number' => $folios->startNumber,
                    'position' => $folios->position->value,
                    'font' => $folios->font,
                    'size_pt' => $folios->sizePt,
                    'margin_mm' => $folios->marginMm,
                ],
            ],
            'thumbnailBase' => route('books.thumbnail', [$book, 0]),
            'fonts' => $library->catalogFor($user),
            'positions' => array_map(fn (FolioPosition $p): array => ['value' => $p->value, 'label' => $p->label()], FolioPosition::cases()),
            'pageFits' => array_map(fn (PageFit $fit): array => ['value' => $fit->value, 'label' => $fit->label()], PageFit::cases()),
            'printDefaults' => PrintDefaults::from(
                $request->session()->get(PrintDefaults::SESSION_KEY),
                $this->defaultScale($book) < 1 ? PageFit::Fit : PageFit::Actual,
            ),
            'previewScale' => $this->defaultScale($book),
        ]);
    }

    public function update(BookContentRequest $request, Book $book): RedirectResponse
    {
        $request->validate(['title' => ['required', 'string', 'max:200']]);

        $book->setSequence($request->sequence());
        $book->setFolioSettings($request->folioSettings());
        $book->title = $request->string('title')->trim()->toString();
        $book->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cambios guardados.']);

        return back();
    }

    /**
     * Escala con la que se imprimirá por defecto: tamaño real si la página cabe
     * en media hoja; si no, ajustada sin recortes.
     */
    private function defaultScale(Book $book): float
    {
        $sheet = Size::letterLandscape();
        $fits = $book->pageSize()->widthMm <= $sheet->widthMm / 2 + 0.01 && $book->pageSize()->heightMm <= $sheet->heightMm + 0.01;

        return (new SheetLayout($sheet, $book->pageSize(), new PrinterProfile, $fits ? PageFit::Actual : PageFit::Fit))->scale;
    }

    public function destroy(Book $book): RedirectResponse
    {
        Gate::authorize('delete', $book);

        Storage::disk('local')->deleteDirectory($book->directory());
        $book->delete();

        return to_route('books.index');
    }

    public function thumbnail(Book $book, int $page): BinaryFileResponse
    {
        Gate::authorize('view', $book);

        $path = $book->thumbnailPath($page);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function print(PrintSettingsRequest $request, Book $book, PrintBook $print, #[CurrentUser] User $user): RedirectResponse
    {
        Gate::authorize('view', $book);

        try {
            $stored = $print->handle($user, $book, $request->settings(), $request->printer(), $request->options());
        } catch (InvalidArgumentException|TypesettingFailed $e) {
            throw ValidationException::withMessages(['print' => $e->getMessage()]);
        }

        $request->session()->put(PrintDefaults::SESSION_KEY, $request->defaults());

        return to_route('imposition.show', $stored->id);
    }
}
