<?php

use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\AreaServiceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MoveTypeController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PreviewController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Site\AreaController as SiteAreaController;
use App\Http\Controllers\Site\PageController as SitePageController;
use App\Http\Controllers\Site\ServiceController as SiteServiceController;
use Illuminate\Support\Facades\Route;

// Public site (Blade, server-rendered).
Route::get('/', [SitePageController::class, 'home'])->name('home');
Route::get('services', [SiteServiceController::class, 'index'])->name('services.index');
Route::get('service-areas', [SiteAreaController::class, 'index'])->name('areas.index');
Route::get('service-areas/{area}', [SiteAreaController::class, 'show'])->where('area', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('areas.show');
Route::get('service-areas/{area}/{service}', [SiteAreaController::class, 'service'])
    ->where(['area' => '[a-z0-9]+(?:-[a-z0-9]+)*', 'service' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
    ->name('areas.service');
Route::get('services/{slug}', [SiteServiceController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('services.show');

// Admin panel (Inertia + Vue). Fortify auth routes share the same /admin prefix.
Route::prefix('admin')->group(function () {
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::name('admin.')->group(function () {
            Route::resource('users', UserController::class)->except('show');

            Route::resource('pages', PageController::class)->except('show');

            Route::post('services/reorder', [ServiceController::class, 'reorder'])->name('services.reorder');
            Route::patch('services/{service}/toggle', [ServiceController::class, 'toggle'])->name('services.toggle');
            Route::resource('services', ServiceController::class)->except('show');

            Route::post('areas/reorder', [AreaController::class, 'reorder'])->name('areas.reorder');
            Route::patch('areas/{area}/toggle', [AreaController::class, 'toggle'])->name('areas.toggle');
            Route::resource('areas', AreaController::class)->except('show');

            Route::get('area-services', [AreaServiceController::class, 'index'])->name('area-services.index');
            Route::get('area-services/template', [AreaServiceController::class, 'editTemplate'])->name('area-services.template');
            Route::put('area-services/template', [AreaServiceController::class, 'updateTemplate'])->name('area-services.template.update');
            Route::patch('area-services/{areaService}/toggle', [AreaServiceController::class, 'toggle'])->name('area-services.toggle');
            Route::get('area-services/{areaService}/edit', [AreaServiceController::class, 'edit'])->name('area-services.edit');
            Route::put('area-services/{areaService}', [AreaServiceController::class, 'update'])->name('area-services.update');

            Route::post('move-types/reorder', [MoveTypeController::class, 'reorder'])->name('move-types.reorder');
            Route::resource('move-types', MoveTypeController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('preview/page', [PreviewController::class, 'page'])->name('preview.page');
            // A refreshed or bookmarked preview tab arrives as GET: explain instead of erroring.
            Route::get('preview/page', fn () => response()->view('admin.preview-error', [
                'errors' => [],
                'expired' => true,
            ]));

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

// Builder pages at "/{slug}" (about-us, contact-us, custom pages). Registered last.
Route::get('{slug}', [SitePageController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('page.show');
