<?php

use App\Domain\Printing\Pdf\TestDocument;
use App\Domain\Printing\Size;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

function uploadedTestPdf(int $pages = 18, ?Size $size = null): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'noctua-upload-');
    (new TestDocument)->render($path, $pages, $size);

    return new UploadedFile($path, 'mi-libro.pdf', 'application/pdf', test: true);
}

function impositionPayload(array $overrides = []): array
{
    return [
        'pdf' => uploadedTestPdf(),
        'sheets_per_signature' => 4,
        'short_last_signature' => '0',
        'creep_mm' => '0',
        'front_offset_x' => '0',
        'front_offset_y' => '0',
        'back_offset_x' => '0.5',
        'back_offset_y' => '-0.25',
        'unprintable_margin_mm' => '5',
        'fold_marks' => '1',
        'page_fit' => 'actual',
        ...$overrides,
    ];
}

it('exige iniciar sesión', function () {
    $this->get(route('imposition.create'))->assertRedirect(route('login'));
});

it('muestra el formulario con valores por defecto', function () {
    $this->actingAs($this->user)
        ->get(route('imposition.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imposition/Create')
            ->where('defaults.sheets_per_signature', 4)
            ->where('recent', []));
});

it('impone el PDF subido y muestra los pliegos', function () {
    $response = $this->actingAs($this->user)->post(route('imposition.store'), impositionPayload());

    $id = basename((string) $response->headers->get('Location'));
    $response->assertRedirect(route('imposition.show', $id));

    $this->get(route('imposition.show', $id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('imposition/Show')
            ->where('imposition.original_name', 'mi-libro.pdf')
            ->where('imposition.printer.back_offset_x', 0.5)
            ->where('summary.page_count', 18)
            ->where('summary.signatures', 2)
            ->where('summary.faces', 16)
            ->where('signatures.0.sheets.0.front', [16, 1])
            ->where('signatures.1.sheets.0.front', [null, 17])
            ->where('signatures.1.sheets.0.back', [18, null]));

    $this->get(route('imposition.pdf', $id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('imposition.pdf', [$id, 'download' => 1]))
        ->assertDownload('mi-libro-impuesto.pdf');
});

it('recuerda la configuración y lista las imposiciones recientes', function () {
    $this->actingAs($this->user)->post(route('imposition.store'), impositionPayload(['sheets_per_signature' => 2]));

    $this->get(route('imposition.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaults.sheets_per_signature', 2)
            ->has('recent', 1)
            ->where('recent.0.name', 'mi-libro.pdf'));
});

it('no deja ver imposiciones de otro usuario', function () {
    $response = $this->actingAs($this->user)->post(route('imposition.store'), impositionPayload());
    $id = basename((string) $response->headers->get('Location'));

    $this->actingAs(User::factory()->create())
        ->get(route('imposition.show', $id))
        ->assertNotFound();
});

it('rechaza identificadores con forma de ruta', function () {
    $this->actingAs($this->user)
        ->get('/imposicion/..%2F..%2Fsecretos/pdf')
        ->assertNotFound();
});

it('valida las opciones', function (array $overrides, string $field) {
    $this->actingAs($this->user)
        ->post(route('imposition.store'), impositionPayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'nueve hojas' => [['sheets_per_signature' => 9], 'sheets_per_signature'],
    'creep negativo' => [['creep_mm' => '-1'], 'creep_mm'],
    'offset enorme' => [['back_offset_x' => '50'], 'back_offset_x'],
    'sin archivo' => [['pdf' => null], 'pdf'],
    'ajuste desconocido' => [['page_fit' => 'estirar'], 'page_fit'],
]);

it('explica por qué no se puede imponer un PDF de páginas demasiado grandes', function () {
    $this->actingAs($this->user)
        ->post(route('imposition.store'), impositionPayload(['pdf' => uploadedTestPdf(4, new Size(210, 297))]))
        ->assertSessionHasErrors(['pdf' => 'La página de 210.0 × 297.0 mm no cabe dos veces en la hoja de 279.4 × 215.9 mm. Usa «Ajustar» o «Llenar» para escalarla.']);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('impone páginas carta ajustándolas a media hoja', function () {
    $response = $this->actingAs($this->user)->post(route('imposition.store'), impositionPayload([
        'pdf' => uploadedTestPdf(8, new Size(215.9, 279.4)),
        'page_fit' => 'fit',
    ]));

    $id = basename((string) $response->headers->get('Location'));
    $response->assertSessionHasNoErrors();

    $this->get(route('imposition.show', $id))
        ->assertInertia(fn (Assert $page) => $page->where('imposition.options.page_fit', 'fit'));
});

it('descarga la hoja de calibración y el documento de prueba', function () {
    $this->actingAs($this->user)
        ->get(route('printing.calibration', ['back_offset_x' => 0.5]))
        ->assertDownload('calibracion.pdf');

    $this->actingAs($this->user)
        ->get(route('printing.test-document', ['pages' => 8]))
        ->assertDownload('prueba-8-paginas.pdf');
});
