<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\BookPreviewController;
use App\Http\Controllers\BorderPreviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FontController;
use App\Http\Controllers\ImpositionController;
use App\Http\Controllers\PrintingToolsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('libros', [BookController::class, 'index'])->name('books.index');
    Route::post('libros', [BookController::class, 'store'])->name('books.store');
    Route::get('libros/{book}', [BookController::class, 'edit'])->name('books.edit');
    Route::put('libros/{book}', [BookController::class, 'update'])->name('books.update');
    Route::delete('libros/{book}', [BookController::class, 'destroy'])->name('books.destroy');
    Route::get('libros/{book}/miniaturas/{page}', [BookController::class, 'thumbnail'])->whereNumber('page')->name('books.thumbnail');
    Route::post('libros/{book}/imprimir', [BookController::class, 'print'])->name('books.print');
    Route::get('libros/{book}/paginas/{page}/imagen', [BookPreviewController::class, 'pageImage'])->whereNumber('page')->name('books.page-image');
    Route::post('libros/{book}/vista-previa', [BookPreviewController::class, 'preview'])->name('books.preview');
    Route::post('libros/{book}/paginas/{page}/texto-editable', [BookPreviewController::class, 'editableText'])->whereNumber('page')->name('books.editable-text');

    Route::get('marcos/vista', BorderPreviewController::class)->name('borders.preview');

    Route::get('fuentes', [FontController::class, 'index'])->name('fonts.index');
    Route::post('fuentes', [FontController::class, 'store'])->name('fonts.store');
    Route::delete('fuentes/{font}', [FontController::class, 'destroy'])->name('fonts.destroy');
    Route::get('fuentes/{font}/archivo', [FontController::class, 'file'])->name('fonts.file');

    Route::get('imposicion', [ImpositionController::class, 'create'])->name('imposition.create');
    Route::post('imposicion', [ImpositionController::class, 'store'])->name('imposition.store');
    Route::get('imposicion/calibracion', [PrintingToolsController::class, 'calibration'])->name('printing.calibration');
    Route::get('imposicion/documento-prueba', [PrintingToolsController::class, 'testDocument'])->name('printing.test-document');
    Route::get('imposicion/{imposition}', [ImpositionController::class, 'show'])->name('imposition.show');
    Route::get('imposicion/{imposition}/pdf', [ImpositionController::class, 'pdf'])->name('imposition.pdf');
});

require __DIR__.'/settings.php';
