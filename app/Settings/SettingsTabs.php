<?php

namespace App\Settings;

use App\Support\SiteTime;
use Illuminate\Support\Arr;

/**
 * Field definitions for the admin Settings screen, one entry per tab.
 * Drives both validation (rules) and the Vue form (type, label, help, options).
 *
 * Field types: text, email, tel, url, number, textarea, code, color, toggle,
 * select, secret (stored encrypted, never sent back), email_list, hours,
 * social, media (media picker, enabled in T3).
 */
class SettingsTabs
{
    /**
     * @return array<string, array{label: string, description: string, fields: list<array<string, mixed>>}>
     */
    public static function all(): array
    {
        return [
            'general' => [
                'label' => 'General',
                'description' => 'Company details shown in the header, footer, contact page and LocalBusiness schema.',
                'fields' => [
                    ['key' => 'general.company_name', 'type' => 'text', 'label' => 'Company name', 'rules' => ['required', 'string', 'max:100']],
                    ['key' => 'general.legal_name', 'type' => 'text', 'label' => 'Legal name', 'rules' => ['nullable', 'string', 'max:150']],
                    ['key' => 'general.phone', 'type' => 'tel', 'label' => 'Phone', 'help' => 'Shown on every page as the Call Now number.', 'rules' => ['required', 'string', 'regex:/^\+?1?[\s.\-]?\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]?\d{4}$/']],
                    ['key' => 'general.email', 'type' => 'email', 'label' => 'Public email', 'rules' => ['required', 'email', 'max:150']],
                    ['key' => 'general.address_street', 'type' => 'text', 'label' => 'Street address', 'rules' => ['required', 'string', 'max:150']],
                    ['key' => 'general.address_city', 'type' => 'text', 'label' => 'City', 'width' => 'third', 'rules' => ['required', 'string', 'max:80']],
                    ['key' => 'general.address_state', 'type' => 'text', 'label' => 'State', 'width' => 'third', 'help' => 'Two letters, e.g. TX.', 'rules' => ['required', 'alpha', 'size:2']],
                    ['key' => 'general.address_zip', 'type' => 'text', 'label' => 'ZIP', 'width' => 'third', 'rules' => ['required', 'regex:/^\d{5}(-\d{4})?$/']],
                    ['key' => 'general.lat', 'type' => 'number', 'label' => 'Latitude', 'width' => 'half', 'help' => 'Used for the map and schema geo.', 'rules' => ['nullable', 'numeric', 'between:-90,90']],
                    ['key' => 'general.lng', 'type' => 'number', 'label' => 'Longitude', 'width' => 'half', 'rules' => ['nullable', 'numeric', 'between:-180,180']],
                    ['key' => 'general.business_hours', 'type' => 'hours', 'label' => 'Business hours', 'rules' => ['nullable', 'array', 'max:7'], 'item_rules' => [
                        'days' => ['required', 'string', 'max:40'],
                        'hours' => ['required', 'string', 'max:60'],
                    ]],
                    ['key' => 'general.usdot_number', 'type' => 'text', 'label' => 'USDOT number', 'width' => 'half', 'rules' => ['nullable', 'digits_between:1,8']],
                    ['key' => 'general.mc_number', 'type' => 'text', 'label' => 'MC number', 'width' => 'half', 'rules' => ['nullable', 'regex:/^(MC-?)?\d{1,8}$/i']],
                    ['key' => 'general.founded_year', 'type' => 'number', 'label' => 'Founded year', 'width' => 'half', 'rules' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')]],
                    ['key' => 'general.price_range', 'type' => 'select', 'label' => 'Price range (schema)', 'width' => 'half', 'options' => ['$' => '$', '$$' => '$$', '$$$' => '$$$', '$$$$' => '$$$$'], 'rules' => ['nullable', 'in:$,$$,$$$,$$$$']],
                    ['key' => 'general.timezone', 'type' => 'select', 'label' => 'Time zone', 'width' => 'half', 'help' => 'Blog publish times and dates on the website use this zone.', 'options' => SiteTime::ZONES, 'rules' => ['required', 'in:'.implode(',', array_keys(SiteTime::ZONES))]],
                    ['key' => 'general.service_radius_text', 'type' => 'text', 'label' => 'Service area line', 'help' => 'Shown in the top contact bar.', 'rules' => ['nullable', 'string', 'max:150']],
                    ['key' => 'general.footer_blurb', 'type' => 'textarea', 'label' => 'Footer text', 'help' => 'Short description under the logo in the footer.', 'rules' => ['nullable', 'string', 'max:300']],
                    ['key' => 'general.social', 'type' => 'social', 'label' => 'Social profiles', 'help' => 'Full URLs. Used for footer icons and schema sameAs.', 'rules' => ['nullable', 'array'], 'item_rules' => ['*' => ['nullable', 'url:https', 'max:255']],
                        'options' => ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'google' => 'Google Business', 'yelp' => 'Yelp', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn']],
                ],
            ],
            'branding' => [
                'label' => 'Branding',
                'description' => 'Logos and favicon.',
                'fields' => [
                    ['key' => 'branding.logo_light_id', 'type' => 'media', 'label' => 'Logo (for light backgrounds)', 'preset' => 'logo', 'rules' => ['nullable', 'integer', 'exists:media,id']],
                    ['key' => 'branding.logo_dark_id', 'type' => 'media', 'label' => 'Logo (for dark backgrounds)', 'preset' => 'logo', 'rules' => ['nullable', 'integer', 'exists:media,id']],
                    ['key' => 'branding.favicon_id', 'type' => 'media', 'label' => 'Favicon', 'preset' => 'square', 'rules' => ['nullable', 'integer', 'exists:media,id']],
                ],
            ],
            'theme' => [
                'label' => 'Theme',
                'description' => 'Site colors. Saved as CSS variables; the whole site updates after the static cache is rebuilt.',
                'fields' => [
                    ['key' => 'theme.primary', 'type' => 'color', 'label' => 'Primary', 'help' => 'Page backgrounds and cards.', 'rules' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                    ['key' => 'theme.secondary', 'type' => 'color', 'label' => 'Secondary', 'help' => 'Text, headings, header/footer and dark sections.', 'rules' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                    ['key' => 'theme.tertiary', 'type' => 'color', 'label' => 'Tertiary (accent)', 'help' => 'Buttons, CTAs, links and highlights.', 'rules' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                    ['key' => 'theme.muted', 'type' => 'color', 'label' => 'Muted', 'help' => 'Alternating section backgrounds.', 'rules' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                    ['key' => 'theme.border', 'type' => 'color', 'label' => 'Border', 'help' => 'Borders and form inputs.', 'rules' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/']],
                ],
            ],
            'email' => [
                'label' => 'Email',
                'description' => 'Lead notifications and the customer auto-reply. SMTP fields override the server .env settings when filled.',
                'fields' => [
                    ['key' => 'email.lead_recipients', 'type' => 'email_list', 'label' => 'Lead notification recipients', 'help' => 'Every quote request and contact message is emailed to these addresses.', 'rules' => ['required', 'array', 'min:1', 'max:10'], 'item_rules' => ['*' => ['required', 'email', 'max:150']]],
                    ['key' => 'email.from_name', 'type' => 'text', 'label' => 'From name', 'width' => 'half', 'rules' => ['required', 'string', 'max:100']],
                    ['key' => 'email.from_address', 'type' => 'email', 'label' => 'From address', 'width' => 'half', 'rules' => ['required', 'email', 'max:150']],
                    ['key' => 'email.auto_reply_enabled', 'type' => 'toggle', 'label' => 'Send an auto-reply to the customer', 'rules' => ['boolean']],
                    ['key' => 'email.auto_reply_subject', 'type' => 'text', 'label' => 'Auto-reply subject', 'rules' => ['required_if_accepted:email.auto_reply_enabled', 'nullable', 'string', 'max:150']],
                    ['key' => 'email.auto_reply_body', 'type' => 'textarea', 'label' => 'Auto-reply message', 'help' => 'Tokens: {name}, {first_name}, {move_date}, {move_type}, {company}, {phone}.', 'rules' => ['required_if_accepted:email.auto_reply_enabled', 'nullable', 'string', 'max:5000']],
                    ['key' => 'email.smtp_host', 'type' => 'text', 'label' => 'SMTP host', 'width' => 'half', 'group' => 'SMTP server (optional)', 'rules' => ['nullable', 'string', 'max:150']],
                    ['key' => 'email.smtp_port', 'type' => 'number', 'label' => 'SMTP port', 'width' => 'half', 'rules' => ['nullable', 'integer', 'between:1,65535']],
                    ['key' => 'email.smtp_username', 'type' => 'text', 'label' => 'SMTP username', 'width' => 'half', 'rules' => ['nullable', 'string', 'max:150']],
                    ['key' => 'email.smtp_password', 'type' => 'secret', 'label' => 'SMTP password', 'width' => 'half', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'email.smtp_encryption', 'type' => 'select', 'label' => 'Encryption', 'width' => 'half', 'options' => ['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'], 'rules' => ['nullable', 'in:tls,ssl,none']],
                ],
            ],
            'integrations' => [
                'label' => 'Integrations',
                'description' => 'Analytics, Search Console and address autocomplete.',
                'fields' => [
                    ['key' => 'integrations.google_places_key', 'type' => 'text', 'label' => 'Google Maps / Places API key', 'help' => 'Powers address autocomplete on Moving From / Moving To. Restrict the key to your domain in Google Cloud.', 'rules' => ['nullable', 'string', 'max:100']],
                    ['key' => 'integrations.gtm_id', 'type' => 'text', 'label' => 'Google Tag Manager ID', 'width' => 'half', 'help' => 'e.g. GTM-ABC1234. When set, load GA4 through GTM.', 'rules' => ['nullable', 'regex:/^GTM-[A-Z0-9]{4,10}$/']],
                    ['key' => 'integrations.ga4_id', 'type' => 'text', 'label' => 'GA4 Measurement ID', 'width' => 'half', 'help' => 'e.g. G-ABC123XYZ. Ignored when a GTM ID is set.', 'rules' => ['nullable', 'regex:/^G-[A-Z0-9]{4,12}$/']],
                    ['key' => 'integrations.search_console_verification', 'type' => 'text', 'label' => 'Search Console verification', 'help' => 'Paste the full <meta> tag or just its content value.', 'rules' => ['nullable', 'string', 'max:255']],
                    ['key' => 'integrations.facebook_pixel_id', 'type' => 'text', 'label' => 'Facebook Pixel ID', 'rules' => ['nullable', 'digits_between:5,20']],
                    ['key' => 'integrations.head_scripts', 'type' => 'code', 'label' => 'Custom <head> code', 'help' => 'Printed before </head> on every public page.', 'rules' => ['nullable', 'string', 'max:20000']],
                    ['key' => 'integrations.body_scripts', 'type' => 'code', 'label' => 'Custom <body> code', 'help' => 'Printed right after <body> on every public page.', 'rules' => ['nullable', 'string', 'max:20000']],
                ],
            ],
            'forms' => [
                'label' => 'Forms',
                'description' => 'Quote and contact form wording.',
                'fields' => [
                    ['key' => 'forms.quote_button_label', 'type' => 'text', 'label' => 'Quote form button label', 'rules' => ['required', 'string', 'max:40']],
                    ['key' => 'forms.success_message', 'type' => 'textarea', 'label' => 'Success message', 'rules' => ['required', 'string', 'max:300']],
                    ['key' => 'forms.tcpa_text', 'type' => 'textarea', 'label' => 'TCPA consent text', 'help' => 'Shown under the submit button. {company} is replaced with the company name.', 'rules' => ['required', 'string', 'max:1000']],
                ],
            ],
            'seo' => [
                'label' => 'SEO',
                'description' => 'Defaults used when a page has no custom SEO values.',
                'fields' => [
                    ['key' => 'seo.title_suffix', 'type' => 'text', 'label' => 'Title suffix', 'help' => 'Appended to fallback titles, e.g. "| Grosolve Moving Network".', 'rules' => ['nullable', 'string', 'max:60']],
                    ['key' => 'seo.default_description', 'type' => 'textarea', 'label' => 'Default meta description', 'help' => 'Aim for 120–160 characters.', 'max' => 160, 'rules' => ['nullable', 'string', 'max:170']],
                    ['key' => 'seo.default_og_image_id', 'type' => 'media', 'label' => 'Default social share image', 'preset' => 'blog', 'rules' => ['nullable', 'integer', 'exists:media,id']],
                    ['key' => 'seo.canonical_host', 'type' => 'text', 'label' => 'Canonical host', 'help' => 'e.g. example.com (no www, no https://). Empty = use the site URL.', 'rules' => ['nullable', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i', 'max:150']],
                ],
            ],
            'static' => [
                'label' => 'Static Cache',
                'description' => 'Pre-built HTML pages for speed and SEO.',
                'fields' => [
                    ['key' => 'static.enabled', 'type' => 'toggle', 'label' => 'Serve pages from the static cache', 'help' => 'Turn off only for debugging: every request is then rendered live.', 'rules' => ['boolean']],
                ],
            ],
        ];
    }

    public static function exists(string $tab): bool
    {
        return array_key_exists($tab, static::all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fields(string $tab): array
    {
        return static::all()[$tab]['fields'];
    }

    /**
     * Validation rules for a tab, keyed by the field key (nested via dots).
     *
     * @return array<string, mixed>
     */
    public static function rules(string $tab): array
    {
        $rules = [];

        foreach (static::fields($tab) as $field) {
            $rules[$field['key']] = $field['rules'];

            foreach ($field['item_rules'] ?? [] as $itemKey => $itemRules) {
                $suffix = $itemKey === '*' ? '*' : "*.{$itemKey}";

                if ($field['type'] === 'social') {
                    $suffix = '*';
                }

                $rules["{$field['key']}.{$suffix}"] = $itemRules;
            }
        }

        return $rules;
    }

    /**
     * Human-readable attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public static function attributes(string $tab): array
    {
        return Arr::mapWithKeys(static::fields($tab), fn (array $f) => [$f['key'] => $f['label']]);
    }
}
