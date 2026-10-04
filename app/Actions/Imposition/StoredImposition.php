<?php

namespace App\Actions\Imposition;

use App\Domain\Imposition\ImpositionSettings;
use App\Domain\Printing\Offset;
use App\Domain\Printing\PageFit;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use App\Models\Publisher;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * Imposición guardada en el disco privado: imposiciones/editorial-{id}/{id}/.
 * Provisional hasta que la fase 2 introduzca la tabla `builds`.
 */
final readonly class StoredImposition
{
    public const string SOURCE = 'fuente.pdf';

    public const string OUTPUT = 'impuesto.pdf';

    public const string META = 'meta.json';

    /**
     * @param  array{original_name: string, created_at: string, page_count: int}  $info
     */
    public function __construct(
        public string $id,
        public string $directory,
        public ImpositionSettings $settings,
        public PrinterProfile $printer,
        public PrintOptions $options,
        public array $info,
    ) {}

    public static function directoryFor(Publisher $publisher, string $id): string
    {
        return "imposiciones/editorial-{$publisher->id}/{$id}";
    }

    public static function find(Publisher $publisher, string $id): ?self
    {
        if (! preg_match('/^[0-9a-z]{26}$/', $id)) {
            return null;
        }

        $directory = self::directoryFor($publisher, $id);
        $json = Storage::disk('local')->get("{$directory}/".self::META);

        if ($json === null) {
            return null;
        }

        try {
            $meta = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($meta) ? self::fromMeta($id, $directory, $meta) : null;
    }

    /**
     * @return list<self>
     */
    public static function recent(Publisher $publisher, int $limit = 10): array
    {
        $ids = array_map(basename(...), Storage::disk('local')->directories("imposiciones/editorial-{$publisher->id}"));
        rsort($ids);

        return array_values(array_filter(array_map(
            fn (string $id): ?self => self::find($publisher, $id),
            array_slice($ids, 0, $limit),
        )));
    }

    public function path(string $file): string
    {
        return Storage::disk('local')->path("{$this->directory}/{$file}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toMeta(): array
    {
        return [
            ...$this->info,
            'settings' => [
                'sheets_per_signature' => $this->settings->sheetsPerSignature,
                'short_last_signature' => $this->settings->shortLastSignature,
                'creep_mm' => $this->settings->creepMmPerSheet,
            ],
            'printer' => [
                'front_offset_x' => $this->printer->frontOffset->xMm,
                'front_offset_y' => $this->printer->frontOffset->yMm,
                'back_offset_x' => $this->printer->backOffset->xMm,
                'back_offset_y' => $this->printer->backOffset->yMm,
                'unprintable_margin_mm' => $this->printer->unprintableMarginMm,
            ],
            'options' => [
                'fold_marks' => $this->options->foldMarks,
                'page_fit' => $this->options->pageFit->value,
            ],
        ];
    }

    /**
     * @param  array<mixed>  $meta
     */
    private static function fromMeta(string $id, string $directory, array $meta): ?self
    {
        $settings = is_array($meta['settings'] ?? null) ? $meta['settings'] : [];
        $printer = is_array($meta['printer'] ?? null) ? $meta['printer'] : [];
        $options = is_array($meta['options'] ?? null) ? $meta['options'] : [];
        $number = fn (array $source, string $key, float $default = 0.0): float => is_numeric($source[$key] ?? null) ? (float) $source[$key] : $default;

        if (! is_string($meta['original_name'] ?? null) || ! is_string($meta['created_at'] ?? null) || ! is_int($meta['page_count'] ?? null)) {
            return null;
        }

        return new self(
            id: $id,
            directory: $directory,
            settings: new ImpositionSettings(
                sheetsPerSignature: is_int($settings['sheets_per_signature'] ?? null) ? $settings['sheets_per_signature'] : 4,
                shortLastSignature: ($settings['short_last_signature'] ?? false) === true,
                creepMmPerSheet: $number($settings, 'creep_mm'),
            ),
            printer: new PrinterProfile(
                frontOffset: new Offset($number($printer, 'front_offset_x'), $number($printer, 'front_offset_y')),
                backOffset: new Offset($number($printer, 'back_offset_x'), $number($printer, 'back_offset_y')),
                unprintableMarginMm: $number($printer, 'unprintable_margin_mm', 5.0),
            ),
            options: new PrintOptions(
                foldMarks: ($options['fold_marks'] ?? true) === true,
                pageFit: PageFit::tryFrom(is_string($options['page_fit'] ?? null) ? $options['page_fit'] : '') ?? PageFit::Actual,
            ),
            info: [
                'original_name' => $meta['original_name'],
                'created_at' => $meta['created_at'],
                'page_count' => $meta['page_count'],
            ],
        );
    }
}
