<?php

use App\Actions\Publishers\CreatePublisher;
use App\Enums\PublisherRole;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function registration(array $overrides = []): array
{
    return [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'account_type' => 'personal',
        ...$overrides,
    ];
}

it('crea un espacio personal al registrarse para uno mismo', function () {
    $this->post(route('register.store'), registration())->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'ana@example.com')->sole();

    expect($user->publishers)->toHaveCount(1)
        ->and($user->publisher()->personal)->toBeTrue()
        ->and($user->roleIn($user->publisher()))->toBe(PublisherRole::Owner);
});

it('crea espacio personal y editorial al registrarse para una editorial', function () {
    $this->post(route('register.store'), registration(['account_type' => 'publisher', 'publisher_name' => 'Ediciones del Búho']))
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'ana@example.com')->sole();

    expect($user->publishers->pluck('name')->sort()->values()->all())->toBe(['Ediciones del Búho', 'Personal'])
        ->and($user->publisher()->name)->toBe('Ediciones del Búho')
        ->and($user->publisher()->personal)->toBeFalse();
});

it('exige el nombre de la editorial', function () {
    $this->post(route('register.store'), registration(['account_type' => 'publisher']))
        ->assertSessionHasErrors('publisher_name');
});

it('comparte los libros entre los miembros de una editorial y los aísla del resto', function () {
    $owner = User::factory()->create();
    $editorial = app(CreatePublisher::class)->handle($owner, 'Ediciones del Búho');
    $colleague = User::factory()->create();
    $editorial->members()->attach($colleague->id, ['role' => PublisherRole::Editor->value]);
    $colleague->switchPublisher($editorial);
    $stranger = User::factory()->create();

    $book = Book::factory()->for($owner)->create(['title' => 'Compartido']);

    expect($book->publisher_id)->toBe($editorial->id);

    $this->actingAs($colleague)->get(route('books.index'))
        ->assertInertia(fn (Assert $page) => $page->has('books', 1)->where('books.0.title', 'Compartido'));
    $this->actingAs($colleague)->get(route('books.edit', $book))->assertOk();
    $this->actingAs($stranger)->get(route('books.edit', $book))->assertForbidden();
    $this->actingAs($stranger)->get(route('books.index'))->assertInertia(fn (Assert $page) => $page->has('books', 0));

    // Un editor no puede borrar libros ajenos; el propietario sí.
    $this->actingAs($colleague)->delete(route('books.destroy', $book))->assertForbidden();
    $this->actingAs($owner)->delete(route('books.destroy', $book))->assertRedirect();
});

it('cambia de espacio solo entre los propios', function () {
    $user = User::factory()->create();
    $personal = $user->publisher();
    $editorial = app(CreatePublisher::class)->handle($user, 'Mi editorial');
    $foreign = Publisher::factory()->create();

    $this->actingAs($user)->put(route('workspaces.switch'), ['publisher_id' => $personal->id])->assertRedirect(route('dashboard'));
    expect($user->refresh()->current_publisher_id)->toBe($personal->id);

    $this->actingAs($user)->put(route('workspaces.switch'), ['publisher_id' => $foreign->id])->assertForbidden();
    expect($user->refresh()->current_publisher_id)->toBe($personal->id)
        ->and($editorial->exists)->toBeTrue();
});

it('comparte el estado del espacio activo con la interfaz', function () {
    $user = User::factory()->create();
    app(CreatePublisher::class)->handle($user, 'Ediciones del Búho');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.publisher.name', 'Ediciones del Búho')
            ->where('auth.publisher.role', 'owner')
            ->has('auth.publishers', 2)
            ->where('auth.publishers.0.personal', true));
});

it('añade miembros por correo y valida los casos imposibles', function () {
    $owner = User::factory()->create();
    app(CreatePublisher::class)->handle($owner, 'Ediciones del Búho');
    $colleague = User::factory()->create(['email' => 'luis@example.com']);

    $this->actingAs($owner)->post(route('workspace.members.store'), ['email' => 'LUIS@example.com', 'role' => 'editor'])->assertSessionHasNoErrors();
    expect($colleague->belongsToPublisher($owner->publisher()))->toBeTrue();

    $this->actingAs($owner)->post(route('workspace.members.store'), ['email' => 'luis@example.com', 'role' => 'editor'])->assertSessionHasErrors('email');
    $this->actingAs($owner)->post(route('workspace.members.store'), ['email' => 'nadie@example.com', 'role' => 'editor'])->assertSessionHasErrors('email');

    // Un editor no administra miembros.
    $colleague->switchPublisher($owner->publisher());
    $this->actingAs($colleague)->post(route('workspace.members.store'), ['email' => $owner->email, 'role' => 'editor'])->assertForbidden();
});

it('no permite añadir miembros al espacio personal', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->post(route('workspace.members.store'), ['email' => $other->email, 'role' => 'editor'])->assertSessionHasErrors('email');
});

it('protege al último propietario y deja salir a los miembros', function () {
    $owner = User::factory()->create();
    $editorial = app(CreatePublisher::class)->handle($owner, 'Ediciones del Búho');
    $colleague = User::factory()->create();
    $editorial->members()->attach($colleague->id, ['role' => PublisherRole::Editor->value]);

    $this->actingAs($owner)->delete(route('workspace.members.destroy', $owner))->assertSessionHasErrors('role');
    $this->actingAs($owner)->put(route('workspace.members.update', $owner), ['role' => 'editor'])->assertSessionHasErrors('role');

    $colleague->switchPublisher($editorial);
    $this->actingAs($colleague)->delete(route('workspace.members.destroy', $colleague))->assertRedirect(route('dashboard'));
    expect($colleague->belongsToPublisher($editorial))->toBeFalse();

    // Al volver, el middleware lo devuelve a su espacio personal.
    $this->actingAs($colleague->refresh())->get(route('dashboard'))->assertOk();
    expect($colleague->refresh()->publisher()->personal)->toBeTrue();
});
