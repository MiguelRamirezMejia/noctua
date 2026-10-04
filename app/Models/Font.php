<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Variante de una fuente (familia + peso + cursiva). Sin editorial = catálogo.
 *
 * @property int $id
 * @property int|null $publisher_id
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
     * @return BelongsTo<Publisher, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    /**
     * Fuentes del catálogo más las propias de la editorial.
     *
     * @param  Builder<Font>  $query
     */
    public function scopeAvailableTo(Builder $query, Publisher $publisher): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('publisher_id')->orWhere('publisher_id', $publisher->id));
    }
}
