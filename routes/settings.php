<?php

use App\Http\Controllers\PublisherController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\RestrictDemoAccounts;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// Cuenta: no disponible en el modo de prueba.
Route::middleware(['auth', RestrictDemoAccounts::class])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', RestrictDemoAccounts::class])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/espacio', [PublisherController::class, 'edit'])->name('workspace.edit');
    Route::put('settings/espacio', [PublisherController::class, 'update'])->name('workspace.update');
    Route::post('settings/espacio/miembros', [PublisherController::class, 'addMember'])->name('workspace.members.store');
    Route::put('settings/espacio/miembros/{member}', [PublisherController::class, 'updateMember'])->name('workspace.members.update');
    Route::delete('settings/espacio/miembros/{member}', [PublisherController::class, 'removeMember'])->name('workspace.members.destroy');
    Route::post('espacios', [PublisherController::class, 'store'])->name('workspaces.store');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::put('espacio-actual', [PublisherController::class, 'switch'])->name('workspaces.switch');

    Route::get('settings/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::patch('settings/appearance', [AppearanceController::class, 'update'])->name('appearance.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
