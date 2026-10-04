<?php

namespace App\Actions\Imposition;

use App\Domain\Imposition\ImpositionSettings;
use App\Domain\Printing\Pdf\PdfImposer;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use App\Models\Publisher;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class ImposeUploadedPdf
{
    public function __construct(
        private PdfImposer $imposer,
    ) {}

    /**
     * @param  UploadedFile|string  $pdf  Archivo subido o ruta a un PDF en disco.
     */
    public function handle(Publisher $publisher, UploadedFile|string $pdf, ImpositionSettings $settings, PrinterProfile $printer, PrintOptions $options, ?string $name = null): StoredImposition
    {
        $id = Str::lower((string) Str::ulid());
        $directory = StoredImposition::directoryFor($publisher, $id);
        $disk = Storage::disk('local');

        $disk->putFileAs($directory, is_string($pdf) ? new File($pdf) : $pdf, StoredImposition::SOURCE);

        try {
            $plan = $this->imposer->impose(
                $disk->path("{$directory}/".StoredImposition::SOURCE),
                $disk->path("{$directory}/".StoredImposition::OUTPUT),
                $settings,
                $printer,
                $options,
            );
        } catch (Throwable $e) {
            $disk->deleteDirectory($directory);

            throw $e;
        }

        $stored = new StoredImposition($id, $directory, $settings, $printer, $options, [
            'original_name' => $name ?? ($pdf instanceof UploadedFile ? $pdf->getClientOriginalName() : basename($pdf)),
            'created_at' => now()->toIso8601String(),
            'page_count' => $plan->spec->pageCount,
        ]);

        $disk->put("{$directory}/".StoredImposition::META, json_encode($stored->toMeta(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $stored;
    }
}
