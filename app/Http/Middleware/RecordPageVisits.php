<?php

namespace App\Http\Middleware;

use App\Activity\ActivityLog;
use App\Activity\ActivityType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anota las páginas que visita cada persona. Solo pantallas completas: ni
 * imágenes, ni archivos, ni recargas parciales (como la actualización en vivo
 * del propio registro).
 */
class RecordPageVisits
{
    /** Ruta => nombre de la pantalla. Lo que no está aquí no se anota. */
    private const array PAGES = [
        'home' => 'la portada',
        'login' => 'el inicio de sesión',
        'register' => 'el registro',
        'dashboard' => 'el inicio',
        'books.index' => 'la lista de libros',
        'fonts.index' => 'las fuentes',
        'imposition.create' => 'la imposición rápida',
        'imposition.show' => 'el resultado de una imposición',
        'profile.edit' => 'su perfil',
        'security.edit' => 'la seguridad de su cuenta',
        'appearance.edit' => 'la apariencia',
        'workspace.edit' => 'su espacio de trabajo',
    ];

    public function __construct(private ActivityLog $activity) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $route = $request->route()?->getName();
        if (
            $request->isMethod('GET')
            && is_string($route)
            && isset(self::PAGES[$route])
            && $response->getStatusCode() === 200
            && ! $request->hasHeader('X-Inertia-Partial-Data')
        ) {
            $this->activity->record(ActivityType::PageVisited, 'Visitó '.self::PAGES[$route].'.', ['ruta' => $route]);
        }

        return $response;
    }
}
