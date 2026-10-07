<?php

use App\Activity\ActivityLog;
use App\Activity\ActivityType;
use App\Models\ActivityEvent;
use App\Models\Book;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['noctua.admins' => ['admin@noctua.test'], 'noctua.demo.enabled' => true]);
    $this->admin = User::factory()->create(['email' => 'admin@noctua.test', 'name' => 'Admin']);
});

/**
 * @return list<string>
 */
function activityTypes(): array
{
    return ActivityEvent::query()->orderBy('id')->pluck('type')->all();
}

it('registra lo que se hace con un libro, con un resumen de los cambios', function () {
    $user = User::factory()->create(['name' => 'Ana']);
    $book = Book::factory()->for($user)->create(['title' => 'Cuento']);

    $this->actingAs($user)->get(route('books.edit', $book))->assertOk();
    $this->put(route('books.update', $book), [
        'title' => 'Cuento nuevo',
        'pages' => [['id' => 'p1', 'source' => 1, 'folio' => true]],
        'folios' => ['enabled' => true, 'first_numbered_page' => 1, 'start_number' => 1, 'position' => 'bottom-center', 'font' => 'Libertinus Serif', 'size_pt' => 10, 'margin_mm' => 8],
        'layout' => ['margin_top_mm' => 10, 'margin_bottom_mm' => 10, 'margin_inside_mm' => 10, 'margin_outside_mm' => 10, 'border' => ['style' => 'greca', 'color' => '#000000', 'width_pt' => 1, 'inset_mm' => 8, 'unit_mm' => 6]],
    ])->assertSessionHasNoErrors();
    $this->delete(route('books.destroy', $book->refresh()));

    expect(activityTypes())->toBe([
        ActivityType::BookOpened->value,
        ActivityType::BookSaved->value,
        ActivityType::BookDeleted->value,
    ]);

    $saved = ActivityEvent::query()->where('type', ActivityType::BookSaved->value)->firstOrFail();
    expect($saved->actor)->toBe('Ana')
        ->and($saved->category)->toBe('libro')
        ->and($saved->description)->toContain('título «Cuento» → «Cuento nuevo»')
        ->and($saved->description)->toContain('numeración')
        ->and($saved->description)->toContain('márgenes')
        ->and($saved->description)->toContain('marco «Greca griega»')
        // El libro ya no existe, pero el historial conserva su título.
        ->and(ActivityEvent::query()->where('type', ActivityType::BookDeleted->value)->value('book_title'))->toBe('Cuento nuevo');
});

it('conserva la actividad de una cuenta de prueba después de borrarla', function () {
    $this->post(route('demo.start'));
    $demo = auth()->user();
    $this->get(route('books.index'));
    $this->post(route('logout'));

    $events = ActivityEvent::query()->orderBy('id')->get();
    expect($events->pluck('type')->all())->toBe([
        ActivityType::DemoStarted->value,
        ActivityType::PageVisited->value,
        ActivityType::DemoDeleted->value,
    ])
        ->and($events->every(fn (ActivityEvent $e): bool => $e->actor_is_demo && str_starts_with($e->actor, 'Invitado ')))->toBeTrue()
        ->and($events->pluck('actor')->unique())->toHaveCount(1)
        ->and(User::query()->find($demo->id))->toBeNull()
        ->and($events->every(fn (ActivityEvent $e): bool => $e->fresh()?->user_id === null))->toBeTrue();
});

it('registra inicios de sesión, intentos fallidos y salidas', function () {
    $user = User::factory()->create(['email' => 'ana@noctua.test', 'password' => 'secreto-largo-123']);

    $this->post(route('login.store'), ['email' => 'ana@noctua.test', 'password' => 'mal']);
    $this->post(route('login.store'), ['email' => 'ana@noctua.test', 'password' => 'secreto-largo-123']);
    $this->post(route('logout'));

    expect(activityTypes())->toBe([
        ActivityType::LoginFailed->value,
        ActivityType::LoggedIn->value,
        ActivityType::LoggedOut->value,
    ])
        ->and(ActivityEvent::query()->where('type', ActivityType::LoginFailed->value)->value('description'))->toContain('ana@noctua.test')
        ->and(ActivityEvent::query()->where('type', ActivityType::LoggedIn->value)->value('user_id'))->toBe($user->id);
});

it('anota las páginas visitadas pero no las recargas parciales', function () {
    $this->actingAs($this->admin)->get(route('dashboard'));
    $this->get(route('dashboard'), ['X-Inertia' => 'true', 'X-Inertia-Partial-Data' => 'stats', 'X-Inertia-Partial-Component' => 'Dashboard']);

    expect(ActivityEvent::query()->where('type', ActivityType::PageVisited->value)->count())->toBe(1);
});

it('registra los errores con su origen', function () {
    app(ActivityLog::class)->error(new RuntimeException('Typst se cayó'));

    $error = ActivityEvent::query()->firstOrFail();
    expect($error->category)->toBe('error')
        ->and($error->description)->toBe('Error: Typst se cayó')
        ->and($error->properties['excepcion'] ?? null)->toBe(RuntimeException::class);
});

it('muestra el registro solo a quien administra', function () {
    $this->actingAs(User::factory()->create())->get(route('activity.index'))->assertForbidden();

    $this->post(route('logout'));
    $this->post(route('demo.start'));
    $this->get(route('activity.index'))->assertForbidden();
});

it('filtra el registro por categoría, persona y texto, sin la navegación por defecto', function () {
    $log = app(ActivityLog::class);
    $ana = User::factory()->create(['name' => 'Ana']);
    $log->record(ActivityType::BookUploaded, 'Subió «Timo.pdf».', user: $ana);
    $log->record(ActivityType::FontUploaded, 'Subió fuentes: Lora.', user: $ana);
    $log->record(ActivityType::PageVisited, 'Visitó el inicio.', user: $ana);

    $this->actingAs($this->admin)
        ->get(route('activity.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Activity')
            ->has('events.data', 2)
            ->where('stats.books_today', 1)
            ->where('people.0.name', 'Ana'));

    $this->get(route('activity.index', ['categoria' => 'fuente']))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 1)->where('events.data.0.description', 'Subió fuentes: Lora.'));

    $this->get(route('activity.index', ['buscar' => 'Timo']))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 1));

    $this->get(route('activity.index', ['navegacion' => 1, 'persona' => 'Ana']))
        ->assertInertia(fn (Assert $page) => $page->has('events.data', 3));

    // Solo el administrador ve el enlace en el menú.
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('auth.can.viewActivity', true));
});
