<?php

namespace App\Actions\Imposition;

use App\Domain\Imposition\ImpositionSettings;
use App\Domain\Printing\Pdf\PdfImposer;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final readonly class ImposeUploadedPdf
{
    public function __construct(
        private PdfImposer $imposer,
    ) {}

    public function handle(User $user, UploadedFile $pdf, ImpositionSettings $settings, PrinterProfile $printer, PrintOptions $options): StoredImposition
    {
        $id = Str::lower((string) Str::ulid());
        $directory = StoredImposition::directoryFor($user, $id);
        $disk = Storage::disk('local');

        $disk->putFileAs($directory, $pdf, StoredImposition::SOURCE);

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
            'original_name' => $pdf->getClientOriginalName(),
            'created_at' => now()->toIso8601String(),
            'page_count' => $plan->spec->pageCount,
        ]);

        $disk->put("{$directory}/".StoredImposition::META, json_encode($stored->toMeta(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return $stored;
    }
}
