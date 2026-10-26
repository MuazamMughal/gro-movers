<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

// Public site (Blade, server-rendered). Real templates arrive in T11.
Route::view('/', 'site.placeholder')->name('home');

// Admin panel (Inertia + Vue). Fortify auth routes share the same /admin prefix.
Route::prefix('admin')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::name('admin.')->group(function () {
            Route::resource('users', UserController::class)->except('show');

            Route::get('settings', fn () => to_route('admin.settings.edit', 'general'))->name('settings.index');
            Route::post('settings/email/test', [SettingsController::class, 'testEmail'])
                ->middleware('throttle:5,1')
                ->name('settings.test-email');
            Route::delete('settings/secret', [SettingsController::class, 'clearSecret'])->name('settings.clear-secret');
            Route::get('settings/{tab}', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings/{tab}', [SettingsController::class, 'update'])->name('settings.update');
        });
    });

    require __DIR__.'/account.php';
});
