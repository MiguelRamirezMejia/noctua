<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleAppearance;
use App\Models\User;
use App\Support\AccentPalette;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Appearance', [
            'presets' => array_map(
                fn (string $hex, string $name): array => ['value' => $hex, 'label' => $name],
                array_keys(AccentPalette::PRESETS),
                array_values(AccentPalette::PRESETS),
            ),
        ]);
    }

    public function update(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate([
            'appearance' => ['required', Rule::in(HandleAppearance::MODES)],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], attributes: ['appearance' => 'modo', 'accent_color' => 'color de acento']);

        $accent = $request->string('accent_color')->lower()->toString();
        $user->forceFill([
            'appearance' => $request->string('appearance')->toString(),
            'accent_color' => $accent === '' ? null : $accent,
        ])->save();

        // La cookie mantiene el modo también en las páginas sin sesión (inicio de sesión).
        return back()->withCookie(cookie()->forever('appearance', $user->appearance));
    }
}
