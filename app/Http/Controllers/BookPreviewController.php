<?php

namespace App\Http\Controllers;

use App\Actions\Book\ExtractPageText;
use App\Actions\Book\PrintBook;
use App\Actions\Book\RenderPageImage;
use App\Actions\Book\TextlessPage;
use App\Activity\ActivityLog;
use App\Activity\ActivityType;
use App\Domain\Book\ContentFrame;
use App\Domain\Book\Design\TextBox;
use App\Domain\Book\Extraction\FontMatcher;
use App\Domain\Book\Extraction\TextBlockBuilder;
use App\Domain\Book\Extraction\TextLine;
use App\Domain\Printing\PageFit;
use App\Domain\Printing\Pdf\PdfComposer;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use App\Domain\Printing\Size;
use App\Domain\Typesetting\TypesettingFailed;
use App\Fonts\FontLibrary;
use App\Http\Requests\BookContentRequest;
use App\Models\Book;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;

class BookPreviewController extends Controller
{
    private const int PREVIEW_DPI = 80;

    /**
     * Imagen de una página del PDF de origen para el editor de pliegos.
     */
    public function pageImage(Request $request, Book $book, int $page, RenderPageImage $render): BinaryFileResponse
    {
        Gate::authorize('view', $book);
        abort_unless($page >= 1 && $page <= $book->source_page_count, 404);

        return response()->file($render->handle($book, $page, $request->boolean('sin_texto')), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Convierte el texto original de una página en cuadros editables. No guarda:
     * el editor añade los cuadros a la página y marca que se use sin su texto.
     */
    public function editableText(Book $book, int $page, ExtractPageText $extract, TextlessPage $textless, FontLibrary $fonts, #[CurrentUser] User $user): JsonResponse
    {
        Gate::authorize('update', $book);
        abort_unless($page >= 1 && $page <= $book->source_page_count, 404);

        try {
            $text = $extract->handle($book, $page);
            $textless->handle($book, $page);
        } catch (RuntimeException $e) {
            app(ActivityLog::class)->record(ActivityType::Error, 'No se pudo hacer editable la página '.$page.' de «'.$book->title.'»: '.$e->getMessage(), ['página' => $page], $book);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Los cuadros se miden en la página del libro. Si esta página del PDF
        // tiene otro tamaño, se encaja igual que al imprimir: escalada y centrada.
        $source = new Size($text['width'] * 25.4 / 72, $text['height'] * 25.4 / 72);
        [$x, $y, $width] = PdfComposer::fitInto($source, $book->pageSize(), new ContentFrame(1.0, 0.0, 0.0));
        $scale = $width / $source->widthMm;
        $lines = array_map(fn (TextLine $line): TextLine => $line->transformed($scale, $x * 72 / 25.4, $y * 72 / 25.4), $text['lines']);

        $matcher = new FontMatcher($fonts->families($book->publisher));
        $boxes = (new TextBlockBuilder($matcher))->build($lines, $book->pageSize()->widthMm * 72 / 25.4, $book->pageSize()->heightMm * 72 / 25.4, 'txt-'.$page.'-'.Str::lower(Str::random(4)));

        $substitutions = [];
        foreach ($text['lines'] as $line) {
            $match = $matcher->match($line->family);
            if ($line->family !== null && ! $match->exact) {
                $substitutions[$line->family] = $match->family;
            }
        }

        app(ActivityLog::class)->record(ActivityType::TextMadeEditable, 'Hizo editable el texto de la página '.$page.' de «'.$book->title.'» ('.count($boxes).' cuadros).', [
            'página' => $page,
            'cuadros' => count($boxes),
            'líneas' => count($text['lines']),
            'fuentes_sustituidas' => $substitutions,
        ], $book);

        return response()->json([
            'elements' => array_map(fn (TextBox $box): array => $box->toArray(), $boxes),
            'substitutions' => array_map(
                fn (string $original, string $used): array => ['original' => $original, 'used' => $used],
                array_keys($substitutions),
                array_values($substitutions),
            ),
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
                $request->bookLayout(),
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

            app(ActivityLog::class)->record(ActivityType::ExactPreview, 'Miró la vista exacta de las páginas '.implode(' y ', $positions).' de «'.$book->title.'».', ['páginas' => $positions], $book);

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

        throw new RuntimeException("No se generó la página {$page} de la vista previa.");
    }
}
