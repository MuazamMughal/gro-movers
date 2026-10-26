<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// The signed-in admin's own account (profile, password, appearance).
// Admin accounts are created and deleted in the Users module.
Route::middleware(['auth'])->group(function () {
    Route::get('account', fn () => to_route('profile.edit'));

    Route::get('account/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('account/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('account/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('account/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('account/appearance', 'account/Appearance')->name('appearance.edit');
});
