<?php

namespace App\Http\Middleware;

use App\Models\ContactMessage;
use App\Models\Lead;
use App\Support\SeoForm;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Title suffix, limits and site URL for the SEO panel on every content form.
            'seoDefaults' => fn () => $request->user() ? SeoForm::defaults() : null,
            // Unread counts for the sidebar badges.
            'inbox' => fn () => $request->user() ? [
                'leads' => Lead::query()->unread()->count(),
                'messages' => ContactMessage::query()->unread()->count(),
            ] : null,
            // Crop presets for the image picker and cropper (config/media.php).
            'mediaPresets' => fn () => collect(config('media.presets'))->map(fn (array $p) => [
                'label' => $p['label'],
                'width' => $p['width'],
                'height' => $p['height'],
                'svg' => $p['svg'] ?? false,
            ]),
        ];
    }
}
