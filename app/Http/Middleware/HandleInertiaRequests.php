<?php

namespace App\Http\Middleware;

use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\Media;
use App\Support\SeoForm;
use App\Support\Settings;
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
            // Company logo and mark for the admin sidebar and the login page.
            'brand' => fn () => static::brand(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Title suffix, limits and site URL for the SEO panel on every content form.
            'seoDefaults' => fn () => $request->user() ? SeoForm::defaults() : null,
            // Unread counts for the sidebar badges.
            'inbox' => fn () => $request->user() ? [
                'leads' => $request->user()->hasPermission('leads.view') ? Lead::query()->unread()->count() : null,
                'messages' => $request->user()->hasPermission('messages') ? ContactMessage::query()->unread()->count() : null,
            ] : null,
            // T19: what the signed-in user's role allows (sidebar, buttons).
            'access' => fn () => $request->user() ? [
                'role' => $request->user()->role?->name,
                'administrator' => $request->user()->isAdministrator(),
                'permissions' => $request->user()->permissions(),
                'content' => $request->user()->can('content'),
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

    /**
     * @return array{name: string, logo: ?string, logoDark: ?string, icon: string}
     */
    public static function brand(): array
    {
        $media = Media::query()->with('variants')
            ->findMany(array_filter([Settings::get('branding.logo_light_id'), Settings::get('branding.logo_dark_id'), Settings::get('branding.favicon_id')]))
            ->keyBy('id');
        $url = fn (string $key) => $media->get(Settings::get($key))?->url(480);

        return [
            'name' => (string) Settings::get('general.company_name', config('app.name')),
            'logo' => $url('branding.logo_light_id'),
            'logoDark' => $url('branding.logo_dark_id'),
            'icon' => $url('branding.favicon_id') ?? '/favicon.png',
        ];
    }
}
