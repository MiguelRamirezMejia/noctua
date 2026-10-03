<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Variante de una fuente (familia + peso + cursiva). Sin usuario = catálogo.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $family
 * @property int $weight
 * @property bool $italic
 * @property string|null $category
 * @property string|null $catalog_family
 * @property string $path
 * @property string $original_name
 * @property string $sha256
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Font extends Model
{
    protected $fillable = ['family', 'weight', 'italic', 'category', 'catalog_family', 'path', 'original_name', 'sha256'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'italic' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Fuentes del catálogo más las propias del usuario.
     *
     * @param  Builder<Font>  $query
     */
    public function scopeAvailableTo(Builder $query, User $user): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }
}
