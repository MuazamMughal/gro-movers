<?php

use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\AreaServiceController;
use App\Http\Controllers\Admin\AuthorController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\LeadController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\MoveTypeController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PreviewController;
use App\Http\Controllers\Admin\ReviewController;
use App\Http\Controllers\Admin\SeoToolsController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaticSiteController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Site\AreaController as SiteAreaController;
use App\Http\Controllers\Site\BlogController as SiteBlogController;
use App\Http\Controllers\Site\FormController;
use App\Http\Controllers\Site\PageController as SitePageController;
use App\Http\Controllers\Site\ReviewController as SiteReviewController;
use App\Http\Controllers\Site\SeoFileController;
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

Route::get('reviews', [SiteReviewController::class, 'index'])->name('reviews.index');

Route::get('sitemap.xml', [SeoFileController::class, 'sitemap'])->name('sitemap');
Route::get('robots.txt', [SeoFileController::class, 'robots'])->name('robots');
Route::get('google{code}.html', [SeoFileController::class, 'verification'])->where('code', '[a-z0-9]+')->name('search-console-file');

// Public forms (no CSRF: see bootstrap/app.php).
Route::get('forms/token', [FormController::class, 'token'])->name('forms.token');
Route::middleware('form.origin')->group(function () {
    Route::post('forms/quote', [FormController::class, 'quote'])->middleware('throttle:form-quote')->name('forms.quote');
    Route::post('forms/contact', [FormController::class, 'contact'])->middleware('throttle:form-contact')->name('forms.contact');
});

Route::get('blog', [SiteBlogController::class, 'index'])->name('blog.index');
Route::get('blog/page/{page}', [SiteBlogController::class, 'index'])->whereNumber('page')->name('blog.page');
Route::get('blog/category/{slug}', [SiteBlogController::class, 'category'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('blog.category');
Route::get('blog/category/{slug}/page/{page}', [SiteBlogController::class, 'category'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->whereNumber('page')
    ->name('blog.category.page');
Route::get('blog/{slug}', [SiteBlogController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('blog.show');

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

            Route::patch('posts/{post}/featured', [PostController::class, 'toggleFeatured'])->name('posts.featured');
            Route::get('posts/{post}/preview', [SiteBlogController::class, 'preview'])->name('posts.preview');
            Route::resource('posts', PostController::class)->except('show');
            Route::post('blog-categories/reorder', [CategoryController::class, 'reorder'])->name('blog-categories.reorder');
            Route::resource('blog-categories', CategoryController::class)->except('show')->parameters(['blog-categories' => 'category']);
            Route::resource('authors', AuthorController::class)->except('show');
            Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);

            Route::patch('reviews/{review}/toggle', [ReviewController::class, 'toggle'])->name('reviews.toggle');
            Route::resource('reviews', ReviewController::class)->except('show');

            Route::get('leads/export', [LeadController::class, 'export'])->name('leads.export');
            Route::post('leads/{lead}/notes', [LeadController::class, 'storeNote'])->name('leads.notes.store');
            Route::delete('leads/{lead}/notes/{note}', [LeadController::class, 'destroyNote'])->name('leads.notes.destroy');
            Route::resource('leads', LeadController::class)->only(['index', 'show', 'update', 'destroy']);
            Route::resource('messages', ContactMessageController::class)->only(['index', 'show', 'update', 'destroy']);

            Route::post('static/rebuild', [StaticSiteController::class, 'rebuild'])->middleware('throttle:6,1')->name('static.rebuild');

            Route::prefix('seo')->name('seo.')->controller(SeoToolsController::class)->group(function () {
                Route::get('/', 'report')->name('report');
                Route::post('check', 'check')->name('check');
                Route::get('redirects', 'redirects')->name('redirects.index');
                Route::post('redirects', 'storeRedirect')->name('redirects.store');
                Route::post('redirects/import', 'importRedirects')->name('redirects.import');
                Route::put('redirects/{redirect}', 'updateRedirect')->name('redirects.update');
                Route::delete('redirects/{redirect}', 'destroyRedirect')->name('redirects.destroy');
                Route::get('robots', 'robots')->name('robots');
                Route::put('robots', 'updateRobots')->name('robots.update');
                Route::post('sitemap', 'regenerateSitemap')->name('sitemap.regenerate');
            });

            Route::get('menus', [MenuController::class, 'index'])->name('menus.index');
            Route::get('menus/{menu}/edit', [MenuController::class, 'edit'])->name('menus.edit');
            Route::put('menus/{menu}', [MenuController::class, 'update'])->name('menus.update');

            Route::post('faqs/reorder', [FaqController::class, 'reorder'])->name('faqs.reorder');
            Route::resource('faqs', FaqController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

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
