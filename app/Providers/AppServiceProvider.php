<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\User;
use App\Support\MailSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->configureMail();
    }

    /**
     * Use the admin Email settings. The long-running queue worker re-reads them
     * between jobs so changes apply without restarting it.
     */
    protected function configureMail(): void
    {
        MailSettings::apply();

        Queue::looping(fn () => MailSettings::refresh());
    }

    /**
     * Store short, stable type names in polymorphic columns (seo_meta, sections, faqables).
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'page' => Page::class,
            'service' => Service::class,
            'area' => Area::class,
            'area_service_page' => AreaServicePage::class,
            'category' => Category::class,
            'post' => Post::class,
            'user' => User::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
