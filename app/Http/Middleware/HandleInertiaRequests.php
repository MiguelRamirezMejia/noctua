<?php

namespace App\Http\Middleware;

use App\Models\Publisher;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'publisher' => fn (): ?array => $this->currentPublisher($request),
                'publishers' => fn (): array => $this->publishers($request),
            ],
            'appearance' => fn (): array => $this->appearance($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array{id: int, name: string, personal: bool, role: string|null}|null
     */
    private function currentPublisher(Request $request): ?array
    {
        $user = $request->user();
        $publisher = $user instanceof User ? $user->currentPublisher : null;

        if (! $user instanceof User || $publisher === null) {
            return null;
        }

        return [
            'id' => $publisher->id,
            'name' => $publisher->name,
            'personal' => $publisher->personal,
            'role' => $user->roleIn($publisher)?->value,
        ];
    }

    /**
     * Espacios a los que pertenece el usuario: el personal primero.
     *
     * @return list<array{id: int, name: string, personal: bool}>
     */
    private function publishers(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [];
        }

        return array_values($user->publishers()
            ->orderByDesc('personal')
            ->orderBy('name')
            ->get()
            ->map(fn (Publisher $p): array => ['id' => $p->id, 'name' => $p->name, 'personal' => $p->personal])
            ->all());
    }

    /**
     * @return array{mode: string, accent: string|null}
     */
    private function appearance(Request $request): array
    {
        $user = $request->user();

        return $user instanceof User
            ? ['mode' => $user->appearance, 'accent' => $user->accent_color]
            : ['mode' => 'system', 'accent' => null];
    }
}
