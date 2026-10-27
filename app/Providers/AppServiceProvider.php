<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Section;
use App\Models\Service;
use App\Models\User;
use App\Services\Media\MediaUsage;
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
        $this->configureMediaUsage();
    }

    /**
     * Images inside page-builder sections count as "in use" in the media library.
     */
    protected function configureMediaUsage(): void
    {
        MediaUsage::resolveUsing('sections', fn (Media $media) => Section::query()
            ->whereJsonContains('media_ids', $media->id)
            ->with('sectionable')
            ->get()
            ->map(fn (Section $section) => [
                'label' => $section->sectionable
                    ? class_basename($section->sectionable).': '.($section->sectionable->title ?? $section->sectionable->name ?? $section->sectionable->city ?? "#{$section->sectionable_id}")
                    : 'Deleted page',
                'field' => (config("sections.types.{$section->type}.label") ?? $section->type).' section',
            ])
            ->all());

        MediaUsage::resolveUsing('post_content', fn (Media $media) => Post::withTrashed()
            ->where('content', 'like', '%data-media-id="'.$media->id.'"%')
            ->get(['id', 'title'])
            ->map(fn (Post $post) => ['label' => 'Post: '.$post->title, 'field' => 'Image in the article'])
            ->all());
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
