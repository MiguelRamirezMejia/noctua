<?php

namespace App\Http\Controllers;

use App\Activity\ActivityType;
use App\Models\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registro de actividad de toda la instalación, solo para quien la administra.
 * La página se actualiza sola mientras está abierta.
 */
class ActivityController extends Controller
{
    private const int PER_PAGE = 100;

    public function index(Request $request): Response
    {
        Gate::authorize('view-activity');

        $filters = [
            'categoria' => $request->string('categoria')->toString(),
            'persona' => $request->string('persona')->toString(),
            'sesion' => $request->string('sesion')->toString(),
            'buscar' => $request->string('buscar')->trim()->toString(),
            'navegacion' => $request->boolean('navegacion'),
        ];

        $events = $this->filtered($filters)
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (ActivityEvent $event): array => $this->present($event));

        return Inertia::render('admin/Activity', [
            'events' => $events,
            'filters' => $filters,
            'categories' => ActivityType::categories(),
            'people' => fn (): array => $this->people(),
            'stats' => fn (): array => $this->stats(),
        ]);
    }

    /**
     * @param  array{categoria: string, persona: string, sesion: string, buscar: string, navegacion: bool}  $filters
     * @return Builder<ActivityEvent>
     */
    private function filtered(array $filters): Builder
    {
        return ActivityEvent::query()
            ->when($filters['categoria'] !== '', fn (Builder $q) => $q->where('category', $filters['categoria']))
            ->when($filters['categoria'] === '' && ! $filters['navegacion'], fn (Builder $q) => $q->where('category', '!=', 'navegacion'))
            ->when($filters['persona'] !== '', fn (Builder $q) => $q->where('actor', $filters['persona']))
            ->when($filters['sesion'] !== '', fn (Builder $q) => $q->where('session', $filters['sesion']))
            ->when($filters['buscar'] !== '', function (Builder $q) use ($filters): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['buscar']).'%';
                $q->where(fn (Builder $w) => $w
                    ->where('description', 'like', $term)
                    ->orWhere('actor', 'like', $term)
                    ->orWhere('book_title', 'like', $term)
                    ->orWhere('ip', 'like', $term));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ActivityEvent $event): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type,
            'category' => $event->category,
            'description' => $event->description,
            'actor' => $event->actor,
            'actor_is_demo' => $event->actor_is_demo,
            'book_title' => $event->book_title,
            'properties' => $event->properties,
            'session' => $event->session,
            'ip' => $event->ip,
            'browser' => self::browser($event->user_agent),
            'url' => $event->url,
            'created_at' => $event->created_at->toIso8601String(),
        ];
    }

    /**
     * Personas con actividad en los últimos 7 días, la más reciente primero.
     *
     * @return list<array{name: string, demo: bool, last: string}>
     */
    private function people(): array
    {
        return array_values(ActivityEvent::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('actor, max(actor_is_demo) as demo, max(created_at) as last')
            ->groupBy('actor')
            ->orderByDesc('last')
            ->limit(50)
            ->get()
            ->map(fn (ActivityEvent $row): array => [
                'name' => $row->actor,
                'demo' => (bool) $row->getAttribute('demo'),
                'last' => is_scalar($last = $row->getAttribute('last')) ? (string) $last : '',
            ])
            ->all());
    }

    /**
     * Resumen para las tarjetas de arriba.
     *
     * @return array<string, int>
     */
    private function stats(): array
    {
        $today = ActivityEvent::query()->where('created_at', '>=', now()->startOfDay());

        return [
            'active_now' => ActivityEvent::query()
                ->where('created_at', '>=', now()->subMinutes(10))
                ->whereNotNull('session')
                ->distinct()
                ->count('session'),
            'people_today' => (clone $today)->distinct()->count('actor'),
            'demo_accounts' => User::query()->whereNotNull('demo_expires_at')->where('demo_expires_at', '>', now())->count(),
            'books_today' => (clone $today)->where('type', ActivityType::BookUploaded->value)->count(),
            'prints_today' => (clone $today)->whereIn('type', [ActivityType::BookPrinted->value, ActivityType::QuickImposition->value])->count(),
            'errors_today' => (clone $today)->where('category', 'error')->count(),
        ];
    }

    /**
     * «Chrome en Windows», a partir del user agent.
     */
    private static function browser(?string $agent): ?string
    {
        if ($agent === null || $agent === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Otro navegador',
        };

        $system = match (true) {
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $system === null ? $browser : "{$browser} en {$system}";
    }
}
