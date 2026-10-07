<?php

namespace App\Actions\Demo;

use App\Actions\Publishers\CreatePublisher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea una cuenta de prueba: sin registro, con su propio espacio aislado y
 * fecha de caducidad. Antes, aprovecha para borrar las que ya caducaron.
 */
final readonly class StartDemo
{
    public function __construct(
        private CreatePublisher $createPublisher,
        private PurgeDemoAccount $purge,
    ) {}

    public function handle(): User
    {
        $this->purge->expired();

        return DB::transaction(function (): User {
            $user = new User([
                'name' => 'Invitado',
                'email' => 'prueba-'.Str::lower((string) Str::ulid()).'@prueba.noctua.invalid',
                'password' => Str::random(40),
            ]);
            $user->forceFill([
                'email_verified_at' => now(),
                'demo_expires_at' => now()->addHours(config()->integer('noctua.demo.ttl_hours')),
            ])->save();

            $this->createPublisher->personalFor($user);

            return $user;
        });
    }
}
