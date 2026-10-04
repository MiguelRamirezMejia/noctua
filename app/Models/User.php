<?php

namespace App\Models;

use App\Enums\PublisherRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use LogicException;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_publisher_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * @return BelongsToMany<Publisher, $this, PublisherMembership, 'membership'>
     */
    public function publishers(): BelongsToMany
    {
        return $this->belongsToMany(Publisher::class)
            ->using(PublisherMembership::class)
            ->as('membership')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Publisher, $this>
     */
    public function currentPublisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class, 'current_publisher_id');
    }

    /**
     * Editorial activa. El middleware EnsureUserHasPublisher garantiza que exista.
     */
    public function publisher(): Publisher
    {
        return $this->currentPublisher ?? throw new LogicException('El usuario no tiene una editorial activa.');
    }

    public function belongsToPublisher(Publisher $publisher): bool
    {
        return $this->publishers()->whereKey($publisher->id)->exists();
    }

    public function roleIn(Publisher $publisher): ?PublisherRole
    {
        return $publisher->roleOf($this);
    }

    public function switchPublisher(Publisher $publisher): void
    {
        $this->forceFill(['current_publisher_id' => $publisher->id])->save();
        $this->setRelation('currentPublisher', $publisher);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
