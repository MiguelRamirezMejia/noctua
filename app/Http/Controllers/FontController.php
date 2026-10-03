<?php

namespace App\Http\Controllers;

use App\Fonts\FontLibrary;
use App\Fonts\InvalidFont;
use App\Models\Font;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FontController extends Controller
{
    public function index(FontLibrary $library, #[CurrentUser] User $user): Response
    {
        return Inertia::render('fonts/Index', [
            'families' => $library->catalogFor($user),
        ]);
    }

    public function store(Request $request, FontLibrary $library, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:40'],
            'files.*' => ['file', 'max:20480'],
        ], attributes: ['files' => 'archivos', 'files.*' => 'archivo']);

        $added = [];
        $errors = [];
        /** @var list<UploadedFile> $files validados como archivos */
        $files = $request->file('files', []);
        foreach ($files as $file) {
            try {
                $font = $library->register((string) $file->getRealPath(), $file->getClientOriginalName(), $user);
                $added[] = $font->family;
            } catch (InvalidFont $e) {
                $errors[] = "{$file->getClientOriginalName()}: {$e->getMessage()}";
            }
        }

        if ($added === [] && $errors !== []) {
            throw ValidationException::withMessages(['files' => implode(' ', $errors)]);
        }

        Inertia::flash('toast', [
            'type' => $errors === [] ? 'success' : 'warning',
            'message' => count($added).' variante(s) añadida(s): '.implode(', ', array_unique($added)).'.'
                .($errors === [] ? '' : ' No se pudieron añadir: '.implode(' ', $errors)),
        ]);

        return back();
    }

    public function destroy(Font $font, #[CurrentUser] User $user): RedirectResponse
    {
        abort_unless($font->user_id === $user->id, 403);

        Storage::disk('local')->delete($font->path);
        $font->delete();

        return back();
    }

    public function file(Font $font, #[CurrentUser] User $user): BinaryFileResponse
    {
        abort_unless($font->user_id === null || $font->user_id === $user->id, 403);

        $path = Storage::disk('local')->path($font->path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => str_ends_with($font->path, '.otf') ? 'font/otf' : 'font/ttf',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
