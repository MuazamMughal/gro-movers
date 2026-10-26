<?php

use Illuminate\Support\Facades\Route;

// Public site (Blade, server-rendered). Real templates arrive in T11.
Route::view('/', 'site.placeholder')->name('home');

// Admin panel (Inertia + Vue). Fortify auth routes share the same /admin prefix.
Route::prefix('admin')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::inertia('/', 'Dashboard')->name('dashboard');
    });

    require __DIR__.'/settings.php';
});
