<?php

namespace App\Http\Controllers;

use App\Actions\Imposition\ImposeUploadedPdf;
use App\Actions\Imposition\StoredImposition;
use App\Domain\Imposition\Imposer;
use App\Domain\Imposition\Sheet;
use App\Domain\Imposition\Signature;
use App\Domain\Printing\PageFit;
use App\Http\Requests\ImposeRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImpositionController extends Controller
{
    private const string DEFAULTS_SESSION_KEY = 'imposition.defaults';

    public function create(Request $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('imposition/Create', [
            'defaults' => $request->session()->get(self::DEFAULTS_SESSION_KEY, [
                'sheets_per_signature' => 4,
                'short_last_signature' => false,
                'creep_mm' => 0,
                'front_offset_x' => 0,
                'front_offset_y' => 0,
                'back_offset_x' => 0,
                'back_offset_y' => 0,
                'unprintable_margin_mm' => 5,
                'fold_marks' => true,
                'page_fit' => PageFit::Fit->value,
            ]),
            'pageFits' => array_map(fn (PageFit $fit): array => ['value' => $fit->value, 'label' => $fit->label()], PageFit::cases()),
            'recent' => array_map(fn (StoredImposition $s): array => [
                'id' => $s->id,
                'name' => $s->info['original_name'],
                'created_at' => $s->info['created_at'],
                'page_count' => $s->info['page_count'],
            ], StoredImposition::recent($user)),
        ]);
    }

    public function store(ImposeRequest $request, ImposeUploadedPdf $impose, #[CurrentUser] User $user): RedirectResponse
    {
        $pdf = $request->file('pdf');

        if (! $pdf instanceof UploadedFile) {
            throw ValidationException::withMessages(['pdf' => 'Sube un archivo PDF.']);
        }

        try {
            $stored = $impose->handle($user, $pdf, $request->settings(), $request->printer(), $request->options());
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['pdf' => $e->getMessage()]);
        }

        $request->session()->put(self::DEFAULTS_SESSION_KEY, $request->safe()->except('pdf'));

        return to_route('imposition.show', $stored->id);
    }

    public function show(string $imposition, Imposer $imposer, #[CurrentUser] User $user): Response
    {
        $stored = StoredImposition::find($user, $imposition) ?? abort(404);
        $plan = $imposer->plan($stored->settings->forPageCount($stored->info['page_count']));

        return Inertia::render('imposition/Show', [
            'imposition' => [
                'id' => $stored->id,
                ...$stored->toMeta(),
            ],
            'summary' => [
                'page_count' => $plan->spec->pageCount,
                'blank_pages' => $plan->blankPageCount(),
                'signatures' => count($plan->signatures),
                'sheets' => $plan->sheetCount(),
                'faces' => 2 * $plan->sheetCount(),
            ],
            'signatures' => array_map(fn (Signature $signature): array => [
                'number' => $signature->number,
                'first_page' => $signature->firstPage,
                'last_page' => $signature->lastPage(),
                'sheets' => array_map(fn (Sheet $sheet): array => [
                    'index' => $sheet->index,
                    'front' => $sheet->front->pages(),
                    'back' => $sheet->back->pages(),
                ], $signature->sheets),
            ], $plan->signatures),
        ]);
    }

    public function pdf(Request $request, string $imposition, #[CurrentUser] User $user): BinaryFileResponse
    {
        $stored = StoredImposition::find($user, $imposition) ?? abort(404);
        $path = $stored->path(StoredImposition::OUTPUT);

        if ($request->boolean('download')) {
            $name = preg_replace('/\.pdf$/i', '', $stored->info['original_name']).'-impuesto.pdf';

            return response()->download($path, $name);
        }

        return response()->file($path, ['Content-Type' => 'application/pdf']);
    }
}
