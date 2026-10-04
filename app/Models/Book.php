<?php

namespace App\Models;

use App\Domain\Book\BookLayout;
use App\Domain\Book\Design\PageDesign;
use App\Domain\Book\FolioPosition;
use App\Domain\Book\FolioSettings;
use App\Domain\Book\PageItem;
use App\Domain\Book\PageSequence;
use App\Domain\Printing\Size;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Libro a partir de un PDF ya maquetado: el PDF de origen se conserva intacto y
 * la edición (orden, blancos, folios) se guarda como datos.
 *
 * @property int $id
 * @property int $publisher_id
 * @property-read Publisher $publisher
 * @property int $user_id Quien lo creó.
 * @property string $title
 * @property string $source_filename
 * @property int $source_page_count
 * @property string $source_width_mm
 * @property string $source_height_mm
 * @property array<mixed> $pages
 * @property array<mixed> $folios
 * @property array<mixed>|null $layout
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected $fillable = ['title', 'source_filename', 'source_page_count', 'source_width_mm', 'source_height_mm', 'pages', 'folios', 'layout'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pages' => 'array',
            'folios' => 'array',
            'layout' => 'array',
            'source_page_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function directory(): string
    {
        return "libros/{$this->user_id}/{$this->id}";
    }

    public function sourcePath(): string
    {
        return Storage::disk('local')->path($this->directory().'/origen.pdf');
    }

    public function thumbnailPath(int $page): string
    {
        return Storage::disk('local')->path($this->directory()."/miniaturas/{$page}.jpg");
    }

    public function pageImagePath(int $page, bool $textless = false): string
    {
        return Storage::disk('local')->path($this->directory()."/paginas/{$page}".($textless ? '-sin-texto' : '').'.jpg');
    }

    public function textlessPagePath(int $page): string
    {
        return Storage::disk('local')->path($this->directory()."/sin-texto/{$page}.pdf");
    }

    public function pageSize(): Size
    {
        return new Size((float) $this->source_width_mm, (float) $this->source_height_mm);
    }

    public function sequence(): PageSequence
    {
        $items = [];
        foreach ($this->pages as $page) {
            if (! is_array($page) || ! is_string($page['id'] ?? null)) {
                continue;
            }

            $source = $page['source'] ?? null;
            $items[] = new PageItem($page['id'], is_int($source) ? $source : null, ($page['folio'] ?? true) !== false, PageDesign::fromArray($page['design'] ?? null));
        }

        return new PageSequence($this->source_page_count, $items);
    }

    public function setSequence(PageSequence $sequence): void
    {
        $this->pages = array_map(fn (PageItem $item): array => [
            'id' => $item->id,
            'source' => $item->sourcePage,
            'folio' => $item->showFolio,
            'design' => $item->design->isEmpty() ? null : $item->design->toArray(),
        ], $sequence->items);
    }

    public function folioSettings(): FolioSettings
    {
        $f = $this->folios;
        $int = fn (string $key, int $default): int => is_int($f[$key] ?? null) ? $f[$key] : $default;
        $float = fn (string $key, float $default): float => is_numeric($f[$key] ?? null) ? (float) $f[$key] : $default;

        return new FolioSettings(
            enabled: ($f['enabled'] ?? false) === true,
            firstNumberedPage: $int('first_numbered_page', 1),
            startNumber: $int('start_number', 1),
            position: FolioPosition::tryFrom(is_string($f['position'] ?? null) ? $f['position'] : '') ?? FolioPosition::BottomCenter,
            font: is_string($f['font'] ?? null) ? $f['font'] : 'Libertinus Serif',
            sizePt: $float('size_pt', 9.0),
            marginMm: $float('margin_mm', 8.0),
        );
    }

    public function bookLayout(): BookLayout
    {
        return BookLayout::fromArray($this->layout);
    }

    public function setBookLayout(BookLayout $layout): void
    {
        $this->layout = $layout->toArray();
    }

    public function setFolioSettings(FolioSettings $settings): void
    {
        $this->folios = [
            'enabled' => $settings->enabled,
            'first_numbered_page' => $settings->firstNumberedPage,
            'start_number' => $settings->startNumber,
            'position' => $settings->position->value,
            'font' => $settings->font,
            'size_pt' => $settings->sizePt,
            'margin_mm' => $settings->marginMm,
        ];
    }
}
