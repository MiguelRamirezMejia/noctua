<?php

use App\Actions\Book\PrintBook;
use App\Domain\Printing\Pdf\TestDocument;
use App\Domain\Printing\PrinterProfile;
use App\Domain\Printing\PrintOptions;
use App\Domain\Printing\Size;
use App\Fonts\FontLibrary;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\ExecutableFinder;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
    app(FontLibrary::class)->register(base_path('tests/Fixtures/fonts/Nunito-Regular.ttf'), 'Nunito-Regular.ttf', category: 'Sans');
});

function bookPdf(int $pages = 6, ?Size $size = null): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'noctua-book-');
    (new TestDocument)->render($path, $pages, $size);

    return new UploadedFile($path, 'Mi cuento.pdf', 'application/pdf', test: true);
}

function binaryAvailable(string $key): bool
{
    $binary = config()->string("noctua.binaries.{$key}");

    return is_file($binary) || (new ExecutableFinder)->find($binary) !== null;
}

/**
 * Crea un libro subiendo un PDF a través de la aplicación.
 */
function uploadBook(User $user, int $pages = 6, ?Size $size = null): Book
{
    test()->actingAs($user)->post(route('books.store'), ['pdf' => bookPdf($pages, $size)])->assertSessionHasNoErrors();

    return Book::query()->latest('id')->firstOrFail();
}

function printPayload(array $overrides = []): array
{
    return [
        'sheets_per_signature' => 1,
        'short_last_signature' => '1',
        'creep_mm' => '0',
        'front_offset_x' => '0',
        'front_offset_y' => '0',
        'back_offset_x' => '0',
        'back_offset_y' => '0',
        'unprintable_margin_mm' => '5',
        'fold_marks' => '1',
        'page_fit' => 'actual',
        ...$overrides,
    ];
}

function editPayload(Book $book, array $pages, array $folios = []): array
{
    return [
        'title' => $book->title,
        'pages' => $pages,
        'folios' => [
            'enabled' => false,
            'first_numbered_page' => 1,
            'start_number' => 1,
            'position' => 'bottom-center',
            'font' => 'Libertinus Serif',
            'size_pt' => 10,
            'margin_mm' => 8,
            ...$folios,
        ],
    ];
}

it('exige iniciar sesión', function () {
    $this->get(route('books.index'))->assertRedirect(route('login'));
});

it('crea un libro desde un PDF con sus miniaturas y lo abre en el editor', function () {
    $book = uploadBook($this->user, 6, new Size(215.9, 279.4));

    expect($book->title)->toBe('Mi cuento')
        ->and($book->source_page_count)->toBe(6)
        ->and((float) $book->source_width_mm)->toBe(215.9)
        ->and(count($book->pages))->toBe(6)
        ->and(is_file($book->sourcePath()))->toBeTrue()
        ->and(is_file($book->thumbnailPath(6)))->toBeTrue();

    $this->get(route('books.edit', $book))
        ->assertInertia(fn (Assert $page) => $page
            ->component('books/Edit')
            ->where('book.pages.0', ['id' => 'p1', 'source' => 1, 'folio' => true, 'design' => ['background' => null, 'elements' => []]])
            ->where('previewScale', fn ($scale) => $scale < 1));

    $this->get(route('books.thumbnail', [$book, 1]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
})->skip(fn () => ! binaryAvailable('pdftoppm'), 'Requiere pdftoppm (poppler-utils).');

it('lista solo los libros propios', function () {
    Book::factory()->for($this->user)->create(['title' => 'Mío']);
    Book::factory()->create(['title' => 'Ajeno']);

    $this->actingAs($this->user)
        ->get(route('books.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('books/Index')
            ->has('books', 1)
            ->where('books.0.title', 'Mío'));
});

it('no deja ver, editar ni imprimir libros ajenos', function () {
    $book = Book::factory()->create();

    $this->actingAs($this->user)->get(route('books.edit', $book))->assertForbidden();
    $this->actingAs($this->user)->put(route('books.update', $book), editPayload($book, []))->assertForbidden();
    $this->actingAs($this->user)->post(route('books.print', $book), printPayload())->assertForbidden();
    $this->actingAs($this->user)->get(route('books.thumbnail', [$book, 1]))->assertForbidden();
});

it('guarda el nuevo orden, blancos, páginas sin número y folios', function () {
    $book = Book::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->put(route('books.update', $book), editPayload($book, [
            ['id' => 'p3', 'source' => 3, 'folio' => true],
            ['id' => 'b1', 'source' => null, 'folio' => true],
            ['id' => 'p1', 'source' => 1, 'folio' => false],
            ['id' => 'p1-copia', 'source' => 1, 'folio' => true],
        ], ['enabled' => true, 'first_numbered_page' => 3, 'font' => 'Nunito', 'position' => 'bottom-outer']))
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $book->refresh();
    $sequence = $book->sequence();

    expect(array_map(fn ($i) => $i->sourcePage, $sequence->items))->toBe([3, null, 1, 1])
        ->and($sequence->items[2]->showFolio)->toBeFalse()
        ->and($sequence->removedSourcePages())->toBe([2, 4, 5, 6, 7, 8])
        ->and($book->folioSettings()->font)->toBe('Nunito')
        ->and(array_map(fn ($f) => $f->text, $sequence->folios($book->folioSettings())))->toBe([4 => '2']);
});

it('valida la edición', function (array $pages, array $folios, string $error) {
    $book = Book::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->put(route('books.update', $book), editPayload($book, $pages, $folios))
        ->assertSessionHasErrors($error);
})->with([
    'sin páginas' => [[], [], 'pages'],
    'página inexistente' => [[['id' => 'x', 'source' => 99, 'folio' => true]], [], 'pages.0.source'],
    'ids repetidos' => [[['id' => 'x', 'source' => 1, 'folio' => true], ['id' => 'x', 'source' => 2, 'folio' => true]], [], 'pages'],
    'fuente no disponible' => [[['id' => 'x', 'source' => 1, 'folio' => true]], ['font' => 'Comic Sans'], 'folios.font'],
    'posición desconocida' => [[['id' => 'x', 'source' => 1, 'folio' => true]], ['position' => 'centro'], 'folios.position'],
]);

it('imprime el libro editado con folios estampados', function () {
    $book = uploadBook($this->user, 4);

    $this->put(route('books.update', $book), editPayload($book, [
        ['id' => 'p2', 'source' => 2, 'folio' => true],
        ['id' => 'p1', 'source' => 1, 'folio' => true],
        ['id' => 'b', 'source' => null, 'folio' => true],
        ['id' => 'p4', 'source' => 4, 'folio' => false],
    ], ['enabled' => true, 'font' => 'Nunito', 'size_pt' => 14]))->assertSessionHasNoErrors();

    $response = $this->post(route('books.print', $book), printPayload());
    $id = basename((string) $response->headers->get('Location'));
    $response->assertRedirect(route('imposition.show', $id));

    $this->get(route('imposition.show', $id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.page_count', 4)
            ->where('imposition.original_name', "{$book->title}.pdf"));

    // Hoja de 1 pliego: frente 4|1, dorso 2|3 → en el libro compuesto: p4(sin nº) | p2(nº 1), p1(nº 2) | blanco.
    $pdf = Storage::disk('local')->path("imposiciones/{$this->user->id}/{$id}/impuesto.pdf");
    $front = Process::timeout(30)->run(['pdftotext', '-layout', '-f', '1', '-l', '1', $pdf, '-'])->output();
    $back = Process::timeout(30)->run(['pdftotext', '-layout', '-f', '2', '-l', '2', $pdf, '-'])->output();

    expect(preg_replace('/\s+/', ' ', $front))->toContain('Página 4')->toContain('Página 2')
        ->and(preg_replace('/\s+/', ' ', $back))->toContain('Página 1')->not->toContain('Página 3');
})->skip(fn () => ! binaryAvailable('pdftoppm') || ! binaryAvailable('typst') || (new ExecutableFinder)->find('pdftotext') === null, 'Requiere Typst y Poppler.');

it('imprime fondos de color y cuadros de texto con fragmentos en negrita', function () {
    $book = uploadBook($this->user, 4);
    $black = ['background' => ['color' => '#000000', 'mode' => 'behind'], 'elements' => [[
        'id' => 't1', 'type' => 'text', 'x' => 10, 'y' => 80, 'width' => 120, 'height' => 40,
        'font' => 'Nunito', 'size_pt' => 20, 'line_height' => 1.3, 'color' => '#ffffff',
        'align' => 'center', 'valign' => 'middle', 'fill' => null,
        'spans' => [
            ['text' => 'Había una vez un ', 'bold' => false, 'italic' => false, 'color' => null],
            ['text' => 'dragón', 'bold' => true, 'italic' => false, 'color' => '#e11d48'],
        ],
    ]]];

    $this->put(route('books.update', $book), editPayload($book, [
        ['id' => 'blanco', 'source' => null, 'folio' => true, 'design' => $black],
        ['id' => 'p1', 'source' => 1, 'folio' => true, 'design' => ['background' => ['color' => '#bfdbfe', 'mode' => 'tint'], 'elements' => []]],
    ]))->assertSessionHasNoErrors();

    $composed = tempnam(sys_get_temp_dir(), 'noctua-compuesto-');
    app(PrintBook::class)->compose($this->user, $book->refresh(), new PrinterProfile, new PrintOptions, $composed);

    $text = Process::timeout(30)->run(['pdftotext', '-f', '1', '-l', '1', $composed, '-'])->output();
    $fonts = Process::timeout(30)->run(['pdffonts', $composed])->output();
    $png = Process::timeout(30)->run(['pdftoppm', '-r', '10', '-png', '-singlefile', $composed, $composed.'-img'])->successful();
    $image = imagecreatefrompng($composed.'-img.png');
    $corner = imagecolorat($image, 2, 2);

    // pdftotext deduce los espacios por distancia y falla al cambiar de variante: se comparan las palabras.
    expect(preg_replace('/\s+/', '', $text))->toContain('Habíaunavezundragón')
        ->and($fonts)->toContain('Nunito')
        ->and($png)->toBeTrue()
        ->and([($corner >> 16) & 0xFF, ($corner >> 8) & 0xFF, $corner & 0xFF])->toBe([0, 0, 0]);

    // Segunda página: el papel blanco del PDF original queda teñido de azul.
    Process::timeout(30)->run(['pdftoppm', '-r', '10', '-png', '-f', '2', '-l', '2', '-singlefile', $composed, $composed.'-p2']);
    $tinted = imagecolorat(imagecreatefrompng($composed.'-p2.png'), 2, 2);
    expect([($tinted >> 16) & 0xFF, ($tinted >> 8) & 0xFF, $tinted & 0xFF])->toBe([0xBF, 0xDB, 0xFE]);
})->skip(fn () => ! binaryAvailable('pdftoppm') || ! binaryAvailable('typst') || (new ExecutableFinder)->find('pdftotext') === null, 'Requiere Typst y Poppler.');

it('rechaza textos con fuentes no disponibles', function () {
    $book = Book::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->put(route('books.update', $book), editPayload($book, [[
            'id' => 'p1', 'source' => 1, 'folio' => true, 'design' => ['background' => null, 'elements' => [[
                'id' => 't', 'type' => 'text', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10,
                'font' => 'Comic Sans', 'spans' => [['text' => 'hola']],
            ]]],
        ]]))
        ->assertSessionHasErrors(['pages' => 'La fuente «Comic Sans» de la página 1 no está disponible.']);
});

it('elimina el libro y sus archivos', function () {
    $book = Book::factory()->for($this->user)->create();
    Storage::disk('local')->put($book->directory().'/origen.pdf', '%PDF');

    $this->actingAs($this->user)->delete(route('books.destroy', $book))->assertRedirect(route('books.index'));

    expect(Book::query()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($book->directory()))->toBeFalse();
});
