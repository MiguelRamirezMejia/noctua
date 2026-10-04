<?php

use Illuminate\Support\Facades\Storage;

it('usa siempre un disco falso en los tests', function () {
    // Si esto fallara, un test podría borrar libros reales (ya ocurrió una vez).
    expect(Storage::disk('local')->path(''))->not->toStartWith(storage_path('app/private'));
});
