<?php

/*
| Default values for the admin-editable `settings` table. SettingsSeeder only inserts
| keys that do not exist yet, so admin changes are never overwritten by a re-seed.
| Keys are "{group}.{name}"; the group drives the Settings tabs in admin (T2).
*/

return [
    // Initial admin account, read by AdminUserSeeder (via config so it works with config:cache).
    'admin' => [
        'name' => env('ADMIN_NAME', 'Site Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'defaults' => [
        // General
        'general.company_name' => 'Grosolve Moving Network',
        'general.legal_name' => 'Grosolve Moving Network LLC',
        'general.phone' => '(214) 555-0100',
        'general.email' => 'info@example.com',
        'general.address_street' => '1234 Commerce St',
        'general.address_city' => 'Dallas',
        'general.address_state' => 'TX',
        'general.address_zip' => '75201',
        'general.lat' => 32.7767,
        'general.lng' => -96.7970,
        'general.business_hours' => [
            ['days' => 'Mon–Fri', 'hours' => '7:00 AM – 7:00 PM'],
            ['days' => 'Sat', 'hours' => '8:00 AM – 5:00 PM'],
            ['days' => 'Sun', 'hours' => 'Closed'],
        ],
        'general.usdot_number' => null,
        'general.mc_number' => null,
        'general.founded_year' => 2015,
        'general.service_radius_text' => 'Serving Dallas–Fort Worth and moves nationwide',
        'general.price_range' => '$$',
        'general.timezone' => 'America/Chicago',
        'general.social' => [
            'facebook' => null,
            'instagram' => null,
            'google' => null,
            'yelp' => null,
            'youtube' => null,
            'linkedin' => null,
        ],

        // Branding (media IDs)
        'branding.logo_light_id' => null,
        'branding.logo_dark_id' => null,
        'branding.favicon_id' => null,

        // Theme colors (CSS variables)
        'theme.primary' => '#FFFFFF',
        'theme.secondary' => '#0A2E5C', // logo navy
        'theme.tertiary' => '#F66419', // logo orange ("MOVING")
        'theme.muted' => '#F1F5FA',
        'theme.border' => '#DCE4EE',

        // Email (SMTP fields override .env when filled)
        'email.smtp_host' => null,
        'email.smtp_port' => null,
        'email.smtp_username' => null,
        'email.smtp_password' => null, // stored encrypted (T2)
        'email.smtp_encryption' => 'tls',
        'email.from_name' => 'Grosolve Moving Network',
        'email.from_address' => 'no-reply@example.com',
        'email.lead_recipients' => ['admin@example.com'],
        'email.auto_reply_enabled' => true,
        'email.auto_reply_subject' => 'We received your moving quote request',
        'email.auto_reply_body' => "Hi {name},\n\nThanks for requesting a free moving quote for {move_date}. A member of our team will contact you shortly.\n\n— {company}",

        // Integrations
        'integrations.google_places_key' => null,
        'integrations.ga4_id' => null,
        'integrations.gtm_id' => null,
        'integrations.search_console_verification' => null,
        'integrations.facebook_pixel_id' => null,
        'integrations.head_scripts' => null,
        'integrations.body_scripts' => null,
        'integrations.turnstile_site_key' => null,   // T18
        'integrations.turnstile_secret_key' => null, // T18

        // Forms
        'forms.tcpa_text' => 'By submitting this form, you agree that {company} may contact you by phone, text message and email about your move, including with automated technology. Consent is not a condition of purchase. Message and data rates may apply.',
        'forms.quote_button_label' => 'Get My Free Quote',
        'forms.success_message' => 'Thank you! We received your request and will contact you shortly.',

        // SEO
        'seo.title_suffix' => '| Grosolve Moving Network',
        'seo.default_description' => 'Licensed and insured movers in Dallas, Plano and Irving, TX. Local and long-distance moving, packing and storage. Get a free quote today.',
        'seo.default_og_image_id' => null,
        'seo.canonical_host' => null, // e.g. "example.com" (no www); null = APP_URL host
        'seo.robots_txt' => "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /forms",

        // Static HTML cache (T14)
        'static.enabled' => true,
    ],
];
