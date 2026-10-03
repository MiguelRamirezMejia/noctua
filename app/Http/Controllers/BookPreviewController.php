<?php

namespace App\Http\Controllers;

use App\Actions\Book\PrintBook;
use App\Actions\Book\RenderPageImage;
use App\Domain\Printing\PageFit;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use App\Domain\Typesetting\TypesettingFailed;
use App\Http\Requests\BookContentRequest;
use App\Models\Book;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class BookPreviewController extends Controller
{
    private const int PREVIEW_DPI = 80;

    /**
     * Imagen de una página del PDF de origen para el editor de pliegos.
     */
    public function pageImage(Book $book, int $page, RenderPageImage $render): BinaryFileResponse
    {
        Gate::authorize('view', $book);
        abort_unless($page >= 1 && $page <= $book->source_page_count, 404);

        return response()->file($render->handle($book, $page), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Compone un pliego con los cambios aún sin guardar, tal como saldrá impreso.
     */
    public function preview(BookContentRequest $request, Book $book, PrintBook $print, #[CurrentUser] User $user): JsonResponse
    {
        $count = count($request->array('pages'));
        $request->validate([
            'positions' => ['required', 'array', 'min:1', 'max:2'],
            'positions.*' => ['integer', 'between:1,'.$count],
            'page_fit' => ['nullable', Rule::enum(PageFit::class)],
        ]);

        $positions = [];
        foreach ($request->array('positions') as $position) {
            if (is_numeric($position)) {
                $positions[] = (int) $position;
            }
        }
        $positions = array_values(array_unique($positions));
        sort($positions);

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'noctua-vista-'.bin2hex(random_bytes(6));

        try {
            $print->compose(
                $user,
                $book,
                new PrinterProfile,
                new PrintOptions(pageFit: $request->enum('page_fit', PageFit::class) ?? PageFit::Fit),
                "{$tmp}.pdf",
                $request->sequence(),
                $request->folioSettings(),
                $positions,
            );

            $process = new Process(
                [config()->string('noctua.binaries.pdftoppm'), '-r', (string) self::PREVIEW_DPI, '-png', "{$tmp}.pdf", $tmp],
                timeout: config()->integer('noctua.process_timeout'),
            );
            $process->mustRun();

            $images = [];
            foreach ($positions as $i => $position) {
                $file = $this->renderedPage($tmp, $i + 1);
                $images[$position] = 'data:image/png;base64,'.base64_encode((string) file_get_contents($file));
            }

            return response()->json(['images' => $images]);
        } catch (TypesettingFailed $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            array_map(unlink(...), glob($tmp.'*') ?: []);
        }
    }

    /**
     * pdftoppm numera las páginas con ceros a la izquierda según el total.
     */
    private function renderedPage(string $prefix, int $page): string
    {
        foreach (glob($prefix.'-*.png') ?: [] as $file) {
            if (preg_match('/-0*'.$page.'\.png$/', $file)) {
                return $file;
            }
        }

        throw new \RuntimeException("No se generó la página {$page} de la vista previa.");
    }
}
