<?php

use App\Fonts\FontLibrary;
use App\Models\Font;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->user = User::factory()->create();
});

function fixtureFont(string $name = 'MiFuente.ttf'): UploadedFile
{
    $copy = tempnam(sys_get_temp_dir(), 'noctua-font-');
    copy(base_path('tests/Fixtures/fonts/Nunito-Regular.ttf'), $copy);

    return new UploadedFile($copy, $name, 'font/ttf', test: true);
}

function fakeFile(string $content, string $name): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'noctua-fake-');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, test: true);
}

it('registra una fuente leyendo su familia, peso y estilo del archivo', function () {
    $font = app(FontLibrary::class)->register(base_path('tests/Fixtures/fonts/Nunito-Regular.ttf'), 'Nunito-Regular.ttf', category: 'Sans');

    expect($font->family)->toBe('Nunito')
        ->and($font->weight)->toBe(400)
        ->and($font->italic)->toBeFalse()
        ->and($font->publisher_id)->toBeNull()
        ->and(Storage::disk('local')->exists($font->path))->toBeTrue();
});

it('sube fuentes propias que solo ve su espacio', function () {
    $this->actingAs($this->user)
        ->post(route('fonts.store'), ['files' => [fixtureFont()]])
        ->assertSessionHasNoErrors();

    $font = Font::query()->sole();
    expect($font->publisher_id)->toBe($this->user->current_publisher_id);

    $library = app(FontLibrary::class);
    $other = User::factory()->create();

    expect($library->has($this->user->publisher(), 'Nunito'))->toBeTrue()
        ->and($library->has($other->publisher(), 'Nunito'))->toBeFalse()
        ->and($library->families($other->publisher()))->toBe(FontLibrary::BUILTIN);

    $this->actingAs($this->user)->get(route('fonts.file', $font))->assertOk()->assertHeader('Content-Type', 'font/ttf');
    $this->actingAs($other)->get(route('fonts.file', $font))->assertForbidden();
    $this->actingAs($other)->delete(route('fonts.destroy', $font))->assertForbidden();
});

it('reemplaza la misma variante en lugar de duplicarla', function () {
    $this->actingAs($this->user)->post(route('fonts.store'), ['files' => [fixtureFont('a.ttf')]]);
    $this->actingAs($this->user)->post(route('fonts.store'), ['files' => [fixtureFont('b.ttf')]]);

    expect(Font::query()->count())->toBe(1)
        ->and(Font::query()->sole()->original_name)->toBe('b.ttf');
});

it('rechaza archivos que no son fuentes imprimibles', function (string $content, string $name, string $message) {
    $this->actingAs($this->user)
        ->post(route('fonts.store'), ['files' => [fakeFile($content, $name)]])
        ->assertSessionHasErrors('files');

    expect(session('errors')->first('files'))->toContain($message);

    expect(Font::query()->count())->toBe(0);
})->with([
    'woff2' => ["wOF2\0\0\0\0", 'fuente.woff2', 'WOFF'],
    'texto' => ['hola mundo', 'fuente.ttf', 'TTF u OTF'],
]);

it('elimina una fuente propia y su archivo', function () {
    $this->actingAs($this->user)->post(route('fonts.store'), ['files' => [fixtureFont()]]);
    $font = Font::query()->sole();

    $this->actingAs($this->user)->delete(route('fonts.destroy', $font))->assertRedirect();

    expect(Font::query()->count())->toBe(0)
        ->and(Storage::disk('local')->exists($font->path))->toBeFalse();
});

it('muestra el catálogo con las fuentes propias primero', function () {
    app(FontLibrary::class)->register(base_path('tests/Fixtures/fonts/Nunito-Regular.ttf'), 'Nunito-Regular.ttf', category: 'Sans');

    $this->actingAs($this->user)
        ->get(route('fonts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('fonts/Index')
            ->where('families.0.family', 'Libertinus Serif')
            ->where('families.1.family', 'Nunito')
            ->where('families.1.faces.0.weight', 400));
});
