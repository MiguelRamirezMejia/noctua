<?php

namespace App\Http\Controllers;

use App\Actions\Demo\StartDemo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * «Probar Noctua»: entra con una cuenta temporal, sin registro.
 */
class DemoController extends Controller
{
    public function store(Request $request, StartDemo $start): RedirectResponse
    {
        abort_unless(config()->boolean('noctua.demo.enabled'), 404);

        $user = $start->handle();
        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Estás probando Noctua. Sube un PDF para empezar: nada de lo que hagas se guarda de forma permanente.',
        ]);

        return to_route('books.index');
    }
}
