<?php

namespace App\Http\Middleware;

use App\Actions\Publishers\CreatePublisher;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantiza que el usuario autenticado tenga una editorial activa de la que sea
 * miembro: la primera a la que pertenezca o, si no pertenece a ninguna, su
 * espacio personal.
 */
class EnsureUserHasPublisher
{
    public function __construct(
        private readonly CreatePublisher $createPublisher,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $current = $user->currentPublisher;

            if ($current === null || ! $user->belongsToPublisher($current)) {
                $first = $user->publishers()->orderBy('publishers.id')->first();

                if ($first !== null) {
                    $user->switchPublisher($first);
                } else {
                    $this->createPublisher->personalFor($user);
                }
            }
        }

        return $next($request);
    }
}
