<?php

namespace App\Http\Controllers;

use App\Actions\Book\CreateBookFromPdf;
use App\Actions\Book\PrintBook;
use App\Domain\Book\FolioPosition;
use App\Domain\Book\FolioSettings;
use App\Domain\Book\InvalidPageSequence;
use App\Domain\Book\PageItem;
use App\Domain\Book\PageSequence;
use App\Domain\Printing\PageFit;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\SheetLayout;
use App\Domain\Printing\Size;
use App\Domain\Typesetting\TypesettingFailed;
use App\Http\PrintDefaults;
use App\Http\Requests\PrintSettingsRequest;
use App\Models\Book;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

    public function edit(Request $request, Book $book): Response
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
            'fonts' => array_map(fn (string $family, ?string $file): array => [
                'family' => $family,
                'url' => $file === null ? null : asset("fonts/folios/{$file}"),
            ], array_keys($this->folioFonts()), array_values($this->folioFonts())),
            'positions' => array_map(fn (FolioPosition $p): array => ['value' => $p->value, 'label' => $p->label()], FolioPosition::cases()),
            'pageFits' => array_map(fn (PageFit $fit): array => ['value' => $fit->value, 'label' => $fit->label()], PageFit::cases()),
            'printDefaults' => PrintDefaults::from(
                $request->session()->get(PrintDefaults::SESSION_KEY),
                $this->defaultScale($book) < 1 ? PageFit::Fit : PageFit::Actual,
            ),
            'previewScale' => $this->defaultScale($book),
        ]);
    }

    public function update(Request $request, Book $book): RedirectResponse
    {
        Gate::authorize('update', $book);

        $fonts = array_keys($this->folioFonts());
        $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'pages' => ['required', 'array', 'min:1', 'max:2000'],
            'pages.*.id' => ['required', 'string', 'max:40'],
            'pages.*.source' => ['nullable', 'integer', 'min:1', 'max:'.$book->source_page_count],
            'pages.*.folio' => ['required', 'boolean'],
            'folios.enabled' => ['required', 'boolean'],
            'folios.first_numbered_page' => ['required', 'integer', 'min:1'],
            'folios.start_number' => ['required', 'integer', 'min:0', 'max:9999'],
            'folios.position' => ['required', Rule::enum(FolioPosition::class)],
            'folios.font' => ['required', Rule::in($fonts)],
            'folios.size_pt' => ['required', 'numeric', 'between:4,48'],
            'folios.margin_mm' => ['required', 'numeric', 'between:0,60'],
        ]);

        try {
            $book->setSequence(new PageSequence($book->source_page_count, $this->pageItems($request->array('pages'))));

            $book->setFolioSettings(new FolioSettings(
                enabled: $request->boolean('folios.enabled'),
                firstNumberedPage: $request->integer('folios.first_numbered_page'),
                startNumber: $request->integer('folios.start_number'),
                position: $request->enum('folios.position', FolioPosition::class) ?? FolioPosition::BottomCenter,
                font: $request->string('folios.font')->toString(),
                sizePt: $request->float('folios.size_pt'),
                marginMm: $request->float('folios.margin_mm'),
            ));
        } catch (InvalidPageSequence $e) {
            throw ValidationException::withMessages(['pages' => $e->getMessage()]);
        }

        $book->title = $request->string('title')->trim()->toString();
        $book->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cambios guardados.']);

        return back();
    }

    /**
     * @param  array<mixed>  $pages  Ya validadas por las reglas de update().
     * @return list<PageItem>
     */
    private function pageItems(array $pages): array
    {
        $items = [];
        foreach ($pages as $page) {
            if (! is_array($page) || ! is_string($page['id'] ?? null)) {
                continue;
            }

            $source = filter_var($page['source'] ?? null, FILTER_VALIDATE_INT);
            $items[] = new PageItem($page['id'], is_int($source) ? $source : null, filter_var($page['folio'] ?? true, FILTER_VALIDATE_BOOLEAN));
        }

        return $items;
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

    /**
     * @return array<string, string|null> familia => archivo en public/fonts/folios
     */
    private function folioFonts(): array
    {
        $fonts = [];
        foreach (config()->array('noctua.folio_fonts') as $family => $file) {
            if (is_string($family)) {
                $fonts[$family] = is_string($file) ? $file : null;
            }
        }

        return $fonts;
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
