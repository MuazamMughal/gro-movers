<?php

use App\Enums\SystemPage;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Models\Page;
use App\Support\Seo;
use Illuminate\Support\Facades\Route;

// Public site (Blade, server-rendered). Real templates arrive in T11.
Route::get('/', fn () => view('site.placeholder', [
    'seo' => ($home = Page::system(SystemPage::Home))
        ? Seo::for($home, '/')
        : Seo::make(title: 'Home', path: '/'),
]))->name('home');

// Admin panel (Inertia + Vue). Fortify auth routes share the same /admin prefix.
Route::prefix('admin')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::name('admin.')->group(function () {
            Route::resource('users', UserController::class)->except('show');

            Route::get('media', [MediaController::class, 'index'])->name('media.index');
            Route::get('media/browse', [MediaController::class, 'browse'])->name('media.browse');
            Route::post('media', [MediaController::class, 'store'])->middleware('throttle:60,1')->name('media.store');
            Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
            Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
            Route::post('media/{media}/crop', [MediaController::class, 'crop'])->name('media.crop');
            Route::post('media/{media}/derive', [MediaController::class, 'derive'])->name('media.derive');
            Route::post('media/{media}/replace', [MediaController::class, 'replace'])->name('media.replace');
            Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

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
