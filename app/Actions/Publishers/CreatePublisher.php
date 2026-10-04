<?php

namespace App\Actions\Publishers;

use App\Enums\PublisherRole;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreatePublisher
{
    /**
     * Crea un espacio con el usuario como propietario y lo deja activo.
     *
     * @param  bool  $personal  Espacio personal (uno por cuenta, sin otros miembros) o editorial compartida.
     */
    public function handle(User $owner, string $name, bool $personal = false): Publisher
    {
        return DB::transaction(function () use ($owner, $name, $personal): Publisher {
            $base = Str::slug($personal ? "personal-{$owner->id}" : $name) ?: 'editorial';
            $slug = $base;
            for ($i = 2; Publisher::query()->where('slug', $slug)->exists(); $i++) {
                $slug = "{$base}-{$i}";
            }

            $publisher = new Publisher(['name' => $name, 'slug' => $slug]);
            $publisher->personal = $personal;
            $publisher->save();
            $publisher->members()->attach($owner->id, ['role' => PublisherRole::Owner->value]);
            $owner->switchPublisher($publisher);

            return $publisher;
        });
    }

    /**
     * Espacio personal de una cuenta (lo crea si aún no lo tiene).
     */
    public function personalFor(User $owner): Publisher
    {
        $personal = $owner->publishers()->where('personal', true)->first();

        return $personal ?? $this->handle($owner, 'Personal', personal: true);
    }
}
