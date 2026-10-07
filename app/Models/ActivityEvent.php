<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un evento del registro de actividad.
 *
 * @property int $id
 * @property string $type
 * @property string $category
 * @property string $description
 * @property int|null $user_id
 * @property string $actor Nombre de quien actuó, copiado al registrar.
 * @property bool $actor_is_demo
 * @property int|null $publisher_id
 * @property int|null $book_id
 * @property string|null $book_title
 * @property array<string, mixed>|null $properties
 * @property string|null $session Identificador corto de la sesión del navegador.
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $url
 * @property CarbonImmutable $created_at
 */
class ActivityEvent extends Model
{
    use MassPrunable;

    /** Días que se conserva el historial. */
    public const int RETENTION_DAYS = 90;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'actor_is_demo' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return ActivityEvent::query()->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
