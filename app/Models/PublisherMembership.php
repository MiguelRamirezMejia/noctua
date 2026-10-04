<?php

namespace App\Models;

use App\Enums\PublisherRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $publisher_id
 * @property int $user_id
 * @property PublisherRole $role
 */
class PublisherMembership extends Pivot
{
    protected $table = 'publisher_user';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => PublisherRole::class];
    }
}
