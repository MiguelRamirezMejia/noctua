<?php

namespace App\Models;

use App\Enums\PublisherRole;
use Database\Factories\PublisherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Espacio de trabajo (el inquilino): el espacio personal de una cuenta o una
 * editorial compartida. Sus libros, fuentes e imposiciones los comparten todos
 * sus miembros.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property bool $personal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Publisher extends Model
{
    /** @use HasFactory<PublisherFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['personal' => 'boolean'];
    }

    /**
     * @return BelongsToMany<User, $this, PublisherMembership, 'membership'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(PublisherMembership::class)
            ->as('membership')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * @return HasMany<Font, $this>
     */
    public function fonts(): HasMany
    {
        return $this->hasMany(Font::class);
    }

    public function roleOf(User $user): ?PublisherRole
    {
        return PublisherMembership::query()
            ->where('publisher_id', $this->id)
            ->where('user_id', $user->id)
            ->first()?->role;
    }
}
