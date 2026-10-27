<?php

/*
|--------------------------------------------------------------------------
| Page builder sections
|--------------------------------------------------------------------------
| Each section type is defined once here. The definition drives:
|   - the admin form (generated from `fields`, see SectionBuilder.vue),
|   - validation and sanitizing (App\Sections\SectionRegistry),
|   - rendering: resources/views/sections/{type}.blade.php.
|
| Field types: text, textarea, richtext, image (with `preset`), link, toggle,
| select (with `options`), number, repeater (with `fields`), icon,
| services, areas, reviews, faqs (multi-pickers), code.
|
| Text fields may use tokens: {company} {phone} {city} {state} {state_code}
| {service} ({city}/{service} only on area and area × service pages).
*/

$background = ['key' => 'background', 'type' => 'select', 'label' => 'Background', 'options' => ['light' => 'White', 'muted' => 'Light grey', 'dark' => 'Navy'], 'default' => 'light'];
$eyebrow = ['key' => 'eyebrow', 'type' => 'text', 'label' => 'Eyebrow', 'help' => 'Small label above the heading.', 'max' => 60];
$heading = ['key' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max' => 120];
$intro = ['key' => 'intro', 'type' => 'textarea', 'label' => 'Intro text', 'max' => 600];
$button = fn (string $key = 'button', string $label = 'Button') => ['key' => $key, 'type' => 'link', 'label' => $label];

return [

    // Curated Lucide icons offered in icon pickers (resources/svg names, kebab-case).
    'icons' => [
        'truck', 'package', 'package-check', 'boxes', 'archive', 'warehouse', 'house', 'building-2', 'store',
        'sofa', 'armchair', 'bed-double', 'lamp', 'piano', 'monitor', 'wine', 'gem',
        'shield-check', 'badge-check', 'award', 'star', 'thumbs-up', 'heart-handshake', 'hand-helping', 'smile',
        'clock', 'timer', 'calendar-check', 'calendar-days', 'zap', 'map-pin', 'map', 'route', 'navigation',
        'phone', 'phone-call', 'mail', 'message-circle', 'headset', 'users', 'user-check', 'hard-hat',
        'dollar-sign', 'receipt', 'calculator', 'tag', 'percent', 'clipboard-check', 'clipboard-list', 'file-text',
        'ruler', 'scale', 'weight', 'wrench', 'key', 'lock', 'leaf', 'recycle', 'circle-check', 'sparkles',
    ],

    'types' => [

        'hero' => [
            'label' => 'Hero',
            'description' => 'Big headline at the top of a page, with buttons and an optional quote form.',
            'icon' => 'house',
            'fields' => [
                $eyebrow,
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading', 'required' => true, 'max' => 120, 'help' => 'The page H1 when this is the first hero.'],
                ['key' => 'subheading', 'type' => 'textarea', 'label' => 'Subheading', 'max' => 400],
                ['key' => 'bullets', 'type' => 'repeater', 'label' => 'Bullet points', 'max_items' => 5, 'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'Text', 'required' => true, 'max' => 80],
                ]],
                $button('primary_button', 'Primary button'),
                $button('secondary_button', 'Secondary button'),
                ['key' => 'background_image', 'type' => 'image', 'label' => 'Background image', 'preset' => 'hero'],
                ['key' => 'show_quote_form', 'type' => 'toggle', 'label' => 'Show the quote form next to the heading', 'default' => false],
                ['key' => 'theme', 'type' => 'select', 'label' => 'Style', 'options' => ['dark' => 'Dark (navy)', 'light' => 'Light'], 'default' => 'dark'],
            ],
            'defaults' => [
                'heading' => 'Stress-free moving in {city}',
                'primary_button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
            ],
        ],

        'trust_bar' => [
            'label' => 'Trust bar',
            'description' => 'Key numbers and badges, e.g. "10,000+ moves", "Licensed & insured".',
            'icon' => 'badge-check',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading (optional)', 'max' => 120],
                ['key' => 'stats', 'type' => 'repeater', 'label' => 'Numbers', 'max_items' => 6, 'fields' => [
                    ['key' => 'value', 'type' => 'text', 'label' => 'Value', 'required' => true, 'max' => 20, 'placeholder' => '10,000+'],
                    ['key' => 'label', 'type' => 'text', 'label' => 'Label', 'required' => true, 'max' => 60, 'placeholder' => 'Moves completed'],
                ]],
                ['key' => 'badges', 'type' => 'repeater', 'label' => 'Badges', 'max_items' => 6, 'fields' => [
                    ['key' => 'icon', 'type' => 'icon', 'label' => 'Icon'],
                    ['key' => 'label', 'type' => 'text', 'label' => 'Label', 'required' => true, 'max' => 60],
                ]],
                ['key' => 'background', 'type' => 'select', 'label' => 'Background', 'options' => ['light' => 'White', 'muted' => 'Light grey', 'dark' => 'Navy'], 'default' => 'muted'],
            ],
        ],

        'rich_text' => [
            'label' => 'Text',
            'description' => 'Headings, paragraphs, lists and links.',
            'icon' => 'file-text',
            'fields' => [
                $eyebrow,
                $heading,
                ['key' => 'content', 'type' => 'richtext', 'label' => 'Content', 'required' => true],
                ['key' => 'width', 'type' => 'select', 'label' => 'Width', 'options' => ['narrow' => 'Narrow (best for reading)', 'wide' => 'Wide'], 'default' => 'narrow'],
                $background,
            ],
        ],

        'image_text' => [
            'label' => 'Image + text',
            'description' => 'A photo beside text, with optional bullets and a button.',
            'icon' => 'clipboard-list',
            'fields' => [
                $eyebrow,
                $heading,
                ['key' => 'content', 'type' => 'richtext', 'label' => 'Content'],
                ['key' => 'bullets', 'type' => 'repeater', 'label' => 'Checklist', 'max_items' => 8, 'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'Text', 'required' => true, 'max' => 120],
                ]],
                ['key' => 'image', 'type' => 'image', 'label' => 'Image', 'preset' => 'card', 'required' => true],
                ['key' => 'image_position', 'type' => 'select', 'label' => 'Image position', 'options' => ['left' => 'Left', 'right' => 'Right'], 'default' => 'right'],
                $button(),
                $background,
            ],
        ],

        'cards_grid' => [
            'label' => 'Feature cards',
            'description' => 'A grid of cards with an icon, title and text (e.g. "Why choose us").',
            'icon' => 'sparkles',
            'fields' => [
                $eyebrow,
                $heading,
                $intro,
                ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3'],
                ['key' => 'cards', 'type' => 'repeater', 'label' => 'Cards', 'min_items' => 1, 'max_items' => 12, 'fields' => [
                    ['key' => 'icon', 'type' => 'icon', 'label' => 'Icon'],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 80],
                    ['key' => 'text', 'type' => 'textarea', 'label' => 'Text', 'max' => 400],
                    ['key' => 'link', 'type' => 'link', 'label' => 'Link'],
                ]],
                $background,
            ],
        ],

        'steps' => [
            'label' => 'How it works',
            'description' => 'Numbered steps (e.g. Request a quote → Plan → Move day).',
            'icon' => 'route',
            'fields' => [
                $eyebrow,
                $heading,
                $intro,
                ['key' => 'steps', 'type' => 'repeater', 'label' => 'Steps', 'min_items' => 1, 'max_items' => 6, 'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 80],
                    ['key' => 'text', 'type' => 'textarea', 'label' => 'Text', 'max' => 400],
                ]],
                $button(),
                $background,
            ],
        ],

        'services_grid' => [
            'label' => 'Services grid',
            'description' => 'Cards linking to your service pages.',
            'icon' => 'truck',
            'fields' => [
                $eyebrow,
                $heading,
                $intro,
                ['key' => 'mode', 'type' => 'select', 'label' => 'Which services', 'options' => ['all' => 'All active services', 'home' => 'Services marked "show on home"', 'picked' => 'Pick services'], 'default' => 'all'],
                ['key' => 'services', 'type' => 'services', 'label' => 'Services', 'show_if' => ['mode' => 'picked']],
                $button(),
                $background,
            ],
        ],

        'areas_grid' => [
            'label' => 'Service areas',
            'description' => 'Links to the cities you serve.',
            'icon' => 'map-pin',
            'fields' => [
                $eyebrow,
                $heading,
                $intro,
                ['key' => 'mode', 'type' => 'select', 'label' => 'Which areas', 'options' => ['all' => 'All active areas', 'picked' => 'Pick areas'], 'default' => 'all'],
                ['key' => 'areas', 'type' => 'areas', 'label' => 'Areas', 'show_if' => ['mode' => 'picked']],
                $button(),
                $background,
            ],
        ],

        'reviews_slider' => [
            'label' => 'Reviews',
            'description' => 'Customer reviews with star ratings.',
            'icon' => 'star',
            'fields' => [
                $eyebrow,
                $heading,
                ['key' => 'mode', 'type' => 'select', 'label' => 'Which reviews', 'options' => ['featured' => 'Featured reviews', 'latest' => 'Latest reviews', 'context' => 'Reviews for this page\'s city or service', 'picked' => 'Pick reviews'], 'default' => 'featured'],
                ['key' => 'reviews', 'type' => 'reviews', 'label' => 'Reviews', 'show_if' => ['mode' => 'picked']],
                ['key' => 'limit', 'type' => 'number', 'label' => 'How many', 'min' => 1, 'max' => 12, 'default' => 6],
                ['key' => 'show_summary', 'type' => 'toggle', 'label' => 'Show the average rating', 'default' => true],
                $button(),
                $background,
            ],
        ],

        'faq' => [
            'label' => 'FAQ',
            'description' => 'Questions and answers in an accordion (adds FAQ rich results).',
            'icon' => 'message-circle',
            'fields' => [
                $eyebrow,
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max' => 120, 'default' => 'Frequently asked questions'],
                $intro,
                ['key' => 'mode', 'type' => 'select', 'label' => 'Which questions', 'options' => ['attached' => 'FAQs attached to this page', 'picked' => 'Pick FAQs'], 'default' => 'attached'],
                ['key' => 'faqs', 'type' => 'faqs', 'label' => 'FAQs', 'show_if' => ['mode' => 'picked']],
                $background,
            ],
        ],

        'cta_banner' => [
            'label' => 'Call to action',
            'description' => 'A bold banner with Call Now and Get a Free Quote.',
            'icon' => 'phone-call',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading', 'required' => true, 'max' => 120],
                ['key' => 'text', 'type' => 'textarea', 'label' => 'Text', 'max' => 300],
                ['key' => 'button', 'type' => 'link', 'label' => 'Button'],
                ['key' => 'show_phone', 'type' => 'toggle', 'label' => 'Show the Call Now button', 'default' => true],
                ['key' => 'theme', 'type' => 'select', 'label' => 'Style', 'options' => ['dark' => 'Navy', 'accent' => 'Orange', 'light' => 'Light grey'], 'default' => 'dark'],
            ],
            'defaults' => [
                'heading' => 'Ready for a stress-free move?',
                'button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
                'show_phone' => true,
            ],
        ],

        'quote_form' => [
            'label' => 'Quote form',
            'description' => 'The free quote request form with a short sales pitch.',
            'icon' => 'clipboard-check',
            'fields' => [
                $eyebrow,
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max' => 120, 'default' => 'Get your free moving quote'],
                $intro,
                ['key' => 'bullets', 'type' => 'repeater', 'label' => 'Reasons to request', 'max_items' => 6, 'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'Text', 'required' => true, 'max' => 100],
                ]],
                $background,
            ],
        ],

        'contact_form' => [
            'label' => 'Contact form',
            'description' => 'The contact form with phone, email, address and hours.',
            'icon' => 'mail',
            'fields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading', 'max' => 120, 'default' => 'Send us a message'],
                $intro,
                ['key' => 'show_details', 'type' => 'toggle', 'label' => 'Show phone, email, address and hours', 'default' => true],
            ],
        ],

        'gallery' => [
            'label' => 'Gallery',
            'description' => 'A grid of photos.',
            'icon' => 'boxes',
            'fields' => [
                $eyebrow,
                $heading,
                ['key' => 'columns', 'type' => 'select', 'label' => 'Columns', 'options' => ['2' => '2', '3' => '3', '4' => '4'], 'default' => '3'],
                ['key' => 'images', 'type' => 'repeater', 'label' => 'Photos', 'min_items' => 1, 'max_items' => 24, 'fields' => [
                    ['key' => 'image', 'type' => 'image', 'label' => 'Photo', 'preset' => 'card', 'required' => true],
                    ['key' => 'caption', 'type' => 'text', 'label' => 'Caption', 'max' => 120],
                ]],
                $background,
            ],
        ],

        'map' => [
            'label' => 'Map',
            'description' => 'An embedded Google Map (loads only when scrolled into view).',
            'icon' => 'map',
            'fields' => [
                $heading,
                ['key' => 'embed_url', 'type' => 'text', 'label' => 'Google Maps embed URL', 'help' => 'In Google Maps: Share → Embed a map → copy the src="…" link. Leave empty to show the business address.', 'max' => 2000],
                ['key' => 'height', 'type' => 'select', 'label' => 'Height', 'options' => ['sm' => 'Short', 'md' => 'Medium', 'lg' => 'Tall'], 'default' => 'md'],
            ],
        ],

        'pricing_factors' => [
            'label' => 'Cost factors',
            'description' => 'What affects the price of a move, with an optional note.',
            'icon' => 'calculator',
            'fields' => [
                $eyebrow,
                $heading,
                $intro,
                ['key' => 'factors', 'type' => 'repeater', 'label' => 'Factors', 'min_items' => 1, 'max_items' => 12, 'fields' => [
                    ['key' => 'icon', 'type' => 'icon', 'label' => 'Icon'],
                    ['key' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 80],
                    ['key' => 'text', 'type' => 'textarea', 'label' => 'Text', 'max' => 400],
                ]],
                ['key' => 'note', 'type' => 'textarea', 'label' => 'Note', 'help' => 'e.g. "Every quote is free and binding — no hidden fees."', 'max' => 400],
                $button(),
                $background,
            ],
        ],

        'custom_html' => [
            'label' => 'Custom HTML',
            'description' => 'Advanced: paste embed code (e.g. a video or a review widget).',
            'icon' => 'wrench',
            'fields' => [
                ['key' => 'html', 'type' => 'code', 'label' => 'HTML', 'required' => true, 'max' => 20000, 'help' => 'Printed as-is. Only paste code from sources you trust; scripts slow the page down.'],
            ],
        ],
    ],
];
