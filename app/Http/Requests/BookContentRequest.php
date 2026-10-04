<?php

namespace App\Http\Requests;

use App\Domain\Book\BookLayout;
use App\Domain\Book\BorderStyle;
use App\Domain\Book\Design\InvalidPageDesign;
use App\Domain\Book\Design\PageDesign;
use App\Domain\Book\FolioPosition;
use App\Domain\Book\FolioSettings;
use App\Domain\Book\InvalidPageSequence;
use App\Domain\Book\PageBorder;
use App\Domain\Book\PageItem;
use App\Domain\Book\PageSequence;
use App\Fonts\FontLibrary;
use App\Models\Book;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Contenido editable de un libro: páginas (orden, blancos, diseño) y folios.
 * Lo usan «guardar» y «vista exacta» (que compone sin guardar).
 */
class BookContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->book());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pages' => ['required', 'array', 'min:1', 'max:2000'],
            'pages.*.id' => ['required', 'string', 'max:40'],
            'pages.*.source' => ['nullable', 'integer', 'min:1', 'max:'.$this->book()->source_page_count],
            'pages.*.folio' => ['required', 'boolean'],
            'pages.*.design' => ['nullable', 'array'],
            'folios.enabled' => ['required', 'boolean'],
            'folios.first_numbered_page' => ['required', 'integer', 'min:1'],
            'folios.start_number' => ['required', 'integer', 'min:0', 'max:9999'],
            'folios.position' => ['required', Rule::enum(FolioPosition::class)],
            'folios.font' => ['required', Rule::in($this->fonts())],
            'folios.size_pt' => ['required', 'numeric', 'between:4,48'],
            'folios.margin_mm' => ['required', 'numeric', 'between:0,60'],
            'layout.margin_top_mm' => ['sometimes', 'numeric', 'between:0,'.BookLayout::MAX_MARGIN_MM],
            'layout.margin_bottom_mm' => ['sometimes', 'numeric', 'between:0,'.BookLayout::MAX_MARGIN_MM],
            'layout.margin_inside_mm' => ['sometimes', 'numeric', 'between:0,'.BookLayout::MAX_MARGIN_MM],
            'layout.margin_outside_mm' => ['sometimes', 'numeric', 'between:0,'.BookLayout::MAX_MARGIN_MM],
            'layout.border' => ['sometimes', 'nullable', 'array'],
            'layout.border.style' => ['required_with:layout.border', Rule::enum(BorderStyle::class)],
            'layout.border.color' => ['required_with:layout.border', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout.border.width_pt' => ['required_with:layout.border', 'numeric', 'between:'.PageBorder::MIN_WIDTH_PT.','.PageBorder::MAX_WIDTH_PT],
            'layout.border.inset_mm' => ['required_with:layout.border', 'numeric', 'between:'.PageBorder::MIN_INSET_MM.','.PageBorder::MAX_INSET_MM],
            'layout.border.unit_mm' => ['required_with:layout.border', 'numeric', 'between:'.PageBorder::MIN_UNIT_MM.','.PageBorder::MAX_UNIT_MM],
        ];
    }

    public function book(): Book
    {
        $book = $this->route('book');

        return $book instanceof Book ? $book : abort(404);
    }

    /**
     * Secuencia validada a fondo por el dominio (diseños y fuentes disponibles).
     */
    public function sequence(): PageSequence
    {
        try {
            $sequence = new PageSequence($this->book()->source_page_count, $this->pageItems($this->array('pages')));
        } catch (InvalidPageSequence|InvalidPageDesign $e) {
            throw ValidationException::withMessages(['pages' => $e->getMessage()]);
        }

        $fonts = $this->fonts();
        foreach ($sequence->items as $index => $item) {
            foreach ($item->design->textBoxes() as $box) {
                if (! in_array($box->font, $fonts, true)) {
                    throw ValidationException::withMessages(['pages' => 'La fuente «'.$box->font.'» de la página '.($index + 1).' no está disponible.']);
                }
            }
        }

        return $sequence;
    }

    public function bookLayout(): BookLayout
    {
        return BookLayout::fromArray($this->input('layout'));
    }

    public function folioSettings(): FolioSettings
    {
        try {
            return new FolioSettings(
                enabled: $this->boolean('folios.enabled'),
                firstNumberedPage: $this->integer('folios.first_numbered_page'),
                startNumber: $this->integer('folios.start_number'),
                position: $this->enum('folios.position', FolioPosition::class) ?? FolioPosition::BottomCenter,
                font: $this->string('folios.font')->toString(),
                sizePt: $this->float('folios.size_pt'),
                marginMm: $this->float('folios.margin_mm'),
            );
        } catch (InvalidPageSequence $e) {
            throw ValidationException::withMessages(['folios' => $e->getMessage()]);
        }
    }

    /**
     * @return list<string>
     */
    private function fonts(): array
    {
        return app(FontLibrary::class)->families($this->book()->publisher);
    }

    /**
     * @param  array<mixed>  $pages
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
            $items[] = new PageItem(
                $page['id'],
                is_int($source) ? $source : null,
                filter_var($page['folio'] ?? true, FILTER_VALIDATE_BOOLEAN),
                PageDesign::fromArray($page['design'] ?? null),
            );
        }

        return $items;
    }
}
