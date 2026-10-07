<?php

namespace App\Activity;

use App\Models\ActivityEvent;
use App\Models\Book;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Registra lo que hace cada persona en Noctua. Nunca interrumpe la acción
 * registrada: si guardar el evento falla, solo se anota en el log.
 */
final class ActivityLog
{
    /**
     * @param  array<string, mixed>  $properties  Detalles del evento (se muestran desplegados).
     * @param  User|null  $user  Quién actúa; por defecto, el usuario de la sesión.
     */
    public function record(ActivityType $type, string $description, array $properties = [], ?Book $book = null, ?User $user = null): ?ActivityEvent
    {
        try {
            $request = request();
            $user ??= $request->user() instanceof User ? $request->user() : null;
            $session = $request->hasSession() ? substr(hash('sha256', $request->session()->getId()), 0, 10) : null;

            return ActivityEvent::query()->create([
                'type' => $type->value,
                'category' => $type->category(),
                'description' => Str::limit($description, 495),
                'user_id' => $user?->id,
                'actor' => self::actorName($user),
                'actor_is_demo' => $user?->isDemo() ?? false,
                'publisher_id' => $book !== null ? $book->publisher_id : $user?->current_publisher_id,
                'book_id' => $book?->id,
                'book_title' => $book?->title,
                'properties' => $properties === [] ? null : $properties,
                'session' => $session,
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 295),
                'url' => Str::limit($request->fullUrl(), 495),
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar la actividad: '.$e->getMessage(), ['type' => $type->value]);

            return null;
        }
    }

    /**
     * Error inesperado de la aplicación.
     */
    public function error(Throwable $e): void
    {
        $this->record(ActivityType::Error, 'Error: '.Str::limit($e->getMessage() ?: $e::class, 300), [
            'excepcion' => $e::class,
            'archivo' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()).':'.$e->getLine(),
        ]);
    }

    /**
     * Nombre con el que aparece quien actúa. Las cuentas de prueba llevan un
     * código corto para distinguir a cada invitado.
     */
    public static function actorName(?User $user): string
    {
        if ($user === null) {
            return 'Visitante';
        }

        if ($user->isDemo()) {
            return 'Invitado '.strtoupper(substr(hash('crc32b', (string) $user->id.$user->email), 0, 4));
        }

        return $user->name;
    }
}
