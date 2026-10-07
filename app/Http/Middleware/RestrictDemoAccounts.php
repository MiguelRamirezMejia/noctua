<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las cuentas de prueba no cambian datos de cuenta ni de espacio (correo,
 * contraseña, miembros, editoriales): son temporales y no tienen dueño real.
 */
class RestrictDemoAccounts
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isDemo()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'En el modo de prueba no se puede cambiar la cuenta ni el espacio de trabajo. Para eso hace falta una cuenta propia.']);

            // Lo único de los ajustes disponible en el modo de prueba es la apariencia.
            return $request->isMethod('GET') ? to_route('appearance.edit') : back();
        }

        return $next($request);
    }
}
