<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\ImpositionController;
use App\Http\Controllers\PrintingToolsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

    Route::get('libros', [BookController::class, 'index'])->name('books.index');
    Route::post('libros', [BookController::class, 'store'])->name('books.store');
    Route::get('libros/{book}', [BookController::class, 'edit'])->name('books.edit');
    Route::put('libros/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('libros/{book}', [BookController::class, 'destroy'])->name('books.destroy');
    Route::get('libros/{book}/miniaturas/{page}', [BookController::class, 'thumbnail'])->whereNumber('page')->name('books.thumbnail');
    Route::post('libros/{book}/imprimir', [BookController::class, 'print'])->name('books.print');

    Route::get('imposicion', [ImpositionController::class, 'create'])->name('imposition.create');
    Route::post('imposicion', [ImpositionController::class, 'store'])->name('imposition.store');
    Route::get('imposicion/calibracion', [PrintingToolsController::class, 'calibration'])->name('printing.calibration');
    Route::get('imposicion/documento-prueba', [PrintingToolsController::class, 'testDocument'])->name('printing.test-document');
    Route::get('imposicion/{imposition}', [ImpositionController::class, 'show'])->name('imposition.show');
    Route::get('imposicion/{imposition}/pdf', [ImpositionController::class, 'pdf'])->name('imposition.pdf');
});

require __DIR__.'/settings.php';
