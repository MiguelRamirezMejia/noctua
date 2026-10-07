<?php

namespace App\Http\Controllers;

use App\Actions\Publishers\CreatePublisher;
use App\Activity\ActivityLog;
use App\Activity\ActivityType;
use App\Enums\PublisherRole;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Espacios de trabajo: el personal de cada cuenta y las editoriales compartidas.
 */
class PublisherController extends Controller
{
    public function edit(#[CurrentUser] User $user): Response
    {
        $publisher = $user->publisher();

        return Inertia::render('settings/Workspace', [
            'workspace' => [
                'id' => $publisher->id,
                'name' => $publisher->name,
                'personal' => $publisher->personal,
                'role' => $user->roleIn($publisher)?->value,
            ],
            'members' => $publisher->personal ? [] : $publisher->members()
                ->orderBy('name')
                ->get()
                ->map(fn (User $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $publisher->roleOf($member)?->value,
                ])
                ->values()
                ->all(),
            'roles' => array_map(fn (PublisherRole $r): array => ['value' => $r->value, 'label' => $r->label()], PublisherRole::cases()),
        ]);
    }

    public function store(Request $request, CreatePublisher $create, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:120']], attributes: ['name' => 'nombre de la editorial']);

        $publisher = $create->handle($user, $request->string('name')->trim()->toString());
        app(ActivityLog::class)->record(ActivityType::WorkspaceCreated, "Creó la editorial «{$publisher->name}».");

        Inertia::flash('toast', ['type' => 'success', 'message' => "Editorial «{$publisher->name}» creada. Ya estás trabajando en ella."]);

        return to_route('dashboard');
    }

    public function switch(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate(['publisher_id' => ['required', 'integer']]);
        $publisher = Publisher::query()->find($request->integer('publisher_id'));

        if ($publisher === null || ! $user->belongsToPublisher($publisher)) {
            abort(403);
        }

        $user->switchPublisher($publisher);
        app(ActivityLog::class)->record(ActivityType::WorkspaceSwitched, "Cambió al espacio «{$publisher->name}».");

        return to_route('dashboard');
    }

    public function update(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $publisher = $this->ownedPublisher($user);
        $request->validate(['name' => ['required', 'string', 'max:120']], attributes: ['name' => 'nombre']);

        $old = $publisher->name;
        $publisher->update(['name' => $request->string('name')->trim()->toString()]);
        app(ActivityLog::class)->record(ActivityType::WorkspaceRenamed, "Renombró el espacio «{$old}» a «{$publisher->name}».");
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nombre actualizado.']);

        return back();
    }

    public function addMember(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $publisher = $this->ownedPublisher($user);
        $this->ensureShared($publisher);

        $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(PublisherRole::class)],
        ], attributes: ['email' => 'correo', 'role' => 'rol']);

        $member = User::query()->where('email', $request->string('email')->trim()->lower()->toString())->first();

        if ($member === null) {
            throw ValidationException::withMessages(['email' => 'No hay ninguna cuenta con ese correo. Pídele que se registre en Noctua y vuelve a intentarlo.']);
        }

        if ($member->belongsToPublisher($publisher)) {
            throw ValidationException::withMessages(['email' => "{$member->name} ya es miembro de esta editorial."]);
        }

        $publisher->members()->attach($member->id, ['role' => $request->string('role')->toString()]);
        app(ActivityLog::class)->record(ActivityType::MemberAdded, "Añadió a {$member->name} a «{$publisher->name}».", ['rol' => $request->string('role')->toString()]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->name} se ha unido a la editorial."]);

        return back();
    }

    public function updateMember(Request $request, User $member, #[CurrentUser] User $user): RedirectResponse
    {
        $publisher = $this->ownedPublisher($user);
        $request->validate(['role' => ['required', Rule::enum(PublisherRole::class)]]);
        $role = $request->enum('role', PublisherRole::class) ?? PublisherRole::Editor;

        abort_unless($member->belongsToPublisher($publisher), 404);

        if ($role !== PublisherRole::Owner && $publisher->roleOf($member) === PublisherRole::Owner) {
            $this->ensureAnotherOwner($publisher, $member);
        }

        $publisher->members()->updateExistingPivot($member->id, ['role' => $role->value]);
        app(ActivityLog::class)->record(ActivityType::MemberRoleChanged, "Cambió el rol de {$member->name} a {$role->label()}.");

        return back();
    }

    /**
     * Un propietario puede quitar a cualquiera; cualquier miembro puede salir.
     */
    public function removeMember(User $member, #[CurrentUser] User $user): RedirectResponse
    {
        $publisher = $user->publisher();
        $this->ensureShared($publisher);
        abort_unless($member->belongsToPublisher($publisher), 404);
        abort_unless($member->id === $user->id || $user->roleIn($publisher) === PublisherRole::Owner, 403);

        if ($publisher->roleOf($member) === PublisherRole::Owner) {
            $this->ensureAnotherOwner($publisher, $member);
        }

        $publisher->members()->detach($member->id);
        app(ActivityLog::class)->record(ActivityType::MemberRemoved, $member->id === $user->id ? "Salió de «{$publisher->name}»." : "Quitó a {$member->name} de «{$publisher->name}».");

        if ($member->id === $user->id) {
            $user->forceFill(['current_publisher_id' => null])->save();

            return to_route('dashboard');
        }

        return back();
    }

    private function ownedPublisher(User $user): Publisher
    {
        $publisher = $user->publisher();
        abort_unless($user->roleIn($publisher) === PublisherRole::Owner, 403, 'Solo un propietario puede cambiar esto.');

        return $publisher;
    }

    private function ensureShared(Publisher $publisher): void
    {
        if ($publisher->personal) {
            throw ValidationException::withMessages(['email' => 'Tu espacio personal es solo tuyo. Crea una editorial para trabajar con otras personas.']);
        }
    }

    private function ensureAnotherOwner(Publisher $publisher, User $leaving): void
    {
        $owners = $publisher->members()->wherePivot('role', PublisherRole::Owner->value)->whereKeyNot($leaving->id)->count();

        if ($owners === 0) {
            throw ValidationException::withMessages(['role' => 'La editorial necesita al menos un propietario.']);
        }
    }
}
