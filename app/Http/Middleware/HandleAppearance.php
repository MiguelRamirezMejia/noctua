<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AccentPalette;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modo (claro, oscuro o sistema) y color de acento para la plantilla HTML, de
 * modo que la página llegue ya con su tema y no parpadee al cargar. Para un
 * usuario con sesión manda lo guardado en su cuenta; si no, la cookie.
 */
class HandleAppearance
{
    public const array MODES = ['light', 'dark', 'system'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $cookie = $request->cookie('appearance');
        $mode = $user instanceof User ? $user->appearance : (is_string($cookie) ? $cookie : 'system');

        View::share('appearance', in_array($mode, self::MODES, true) ? $mode : 'system');
        View::share('accentStyle', AccentPalette::style($user instanceof User ? $user->accent_color : null));
        View::share('authenticated', $user instanceof User);

        return $next($request);
    }
}
