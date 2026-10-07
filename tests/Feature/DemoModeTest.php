<?php

use App\Actions\Demo\PurgeDemoAccount;
use App\Actions\Demo\StartDemo;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => config(['noctua.demo.enabled' => true]));

it('no ofrece el modo de prueba si está desactivado', function () {
    config(['noctua.demo.enabled' => false]);

    $this->post(route('demo.start'))->assertNotFound();
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('demo.enabled', false));
});

it('entra con una cuenta temporal y aislada, sin registro', function () {
    $owner = User::factory()->create();
    Book::factory()->for($owner)->create(['title' => 'Libro real']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('demo.enabled', true));
    $this->post(route('demo.start'))->assertRedirect(route('books.index'));

    $demo = auth()->user();
    expect($demo)->toBeInstanceOf(User::class)
        ->and($demo->isDemo())->toBeTrue()
        ->and($demo->demo_expires_at?->isFuture())->toBeTrue()
        ->and($demo->publisher()->personal)->toBeTrue();

    // No ve los libros de nadie.
    $this->get(route('books.index'))->assertInertia(fn (Assert $page) => $page
        ->where('books', [])
        ->where('demo.expires_at', $demo->demo_expires_at?->toIso8601String()));
});

it('no deja cambiar la cuenta ni el espacio en el modo de prueba', function () {
    $this->post(route('demo.start'));

    $this->get(route('profile.edit'))->assertRedirect(route('appearance.edit'));
    $this->get(route('workspace.edit'))->assertRedirect(route('appearance.edit'));
    $this->post(route('workspaces.store'), ['name' => 'Otra'])->assertRedirect();
    $this->get(route('appearance.edit'))->assertOk();

    expect(Publisher::query()->where('name', 'Otra')->exists())->toBeFalse();
});

it('limita los libros de una cuenta de prueba', function () {
    config(['noctua.demo.max_books' => 1]);
    $this->post(route('demo.start'));
    Book::factory()->for(auth()->user())->create();

    $this->post(route('books.store'), ['pdf' => UploadedFile::fake()->create('libro.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors(['pdf' => 'En el modo de prueba se pueden subir hasta 1 libros. Borra uno para subir otro.']);
});

it('borra la cuenta de prueba con sus libros, fuentes y archivos al salir', function () {
    $this->post(route('demo.start'));
    $demo = auth()->user();
    $publisher = $demo->publisher();
    $book = Book::factory()->for($demo)->create();
    Storage::disk('local')->put($book->directory().'/origen.pdf', 'pdf');
    Storage::disk('local')->put("imposiciones/editorial-{$publisher->id}/x/impuesto.pdf", 'pdf');

    $this->post(route('logout'));

    expect(User::query()->find($demo->id))->toBeNull()
        ->and(Publisher::query()->find($publisher->id))->toBeNull()
        ->and(Book::query()->find($book->id))->toBeNull()
        ->and(Storage::disk('local')->exists($book->directory().'/origen.pdf'))->toBeFalse()
        ->and(Storage::disk('local')->exists("imposiciones/editorial-{$publisher->id}"))->toBeFalse();
});

it('borra solo las cuentas de prueba caducadas y nunca las normales', function () {
    $normal = User::factory()->create();
    $normalBook = Book::factory()->for($normal)->create();
    $active = app(StartDemo::class)->handle();
    $expired = app(StartDemo::class)->handle();
    $expired->forceFill(['demo_expires_at' => now()->subMinute()])->save();

    $this->artisan('noctua:demo:purge')->assertSuccessful();

    expect(User::query()->find($expired->id))->toBeNull()
        ->and(User::query()->find($active->id))->not->toBeNull()
        ->and(User::query()->find($normal->id))->not->toBeNull()
        ->and(Book::query()->find($normalBook->id))->not->toBeNull();

    // Una cuenta normal nunca se borra, aunque se le pida.
    app(PurgeDemoAccount::class)->handle($normal);
    expect(User::query()->find($normal->id))->not->toBeNull();
});
