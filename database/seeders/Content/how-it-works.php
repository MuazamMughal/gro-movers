<?php

/*
| Starter content for the How It Works page (a normal builder page, editable in admin).
| "faqs" picks questions from the global FAQ bank by their position (0-based).
*/

return [
    'title' => 'How It Works',
    'slug' => 'how-it-works',
    'meta_title' => 'How Our Moving Process Works | From Quote to Move-In',
    'meta_description' => 'See how your move works, step by step: a free written quote, a clear plan, a careful crew on moving day and a final walk-through. Get your free quote.',
    'faqs' => [0, 2, 3, 5, 13],
    'sections' => [
        ['type' => 'hero', 'data' => [
            'eyebrow' => 'How it works',
            'heading' => 'How Your Move Works, Step by Step',
            'subheading' => 'No surprises and no guesswork. Here is exactly what happens from your first quote to the last box.',
            'theme' => 'dark',
        ]],
        ['type' => 'steps', 'data' => [
            'heading' => 'Four simple steps',
            'intro' => 'Every move follows the same clear process, whether it is a studio apartment or a full office.',
            'steps' => [
                ['title' => 'Get your free quote', 'text' => 'Tell us your addresses, moving date and type of move, online or by phone. We reply with a clear, written estimate, usually the same business day.'],
                ['title' => 'We plan your move', 'text' => 'Your coordinator confirms the date, crew size and any items that need extra care, and checks building or HOA rules. We confirm your arrival window the day before.'],
                ['title' => 'Moving day', 'text' => 'We call when we are on the way. Your crew protects your floors, wraps your furniture, then loads, drives and unloads everything at your new place.'],
                ['title' => 'Settle in', 'text' => 'We put your furniture where you want it and reassemble what we took apart. Then we do a final walk-through with you so nothing is forgotten.'],
            ],
            'background' => 'light',
        ]],
        ['type' => 'cards_grid', 'data' => [
            'eyebrow' => 'On moving day',
            'heading' => 'What your crew takes care of',
            'columns' => '3',
            'cards' => [
                ['icon' => 'shield-check', 'title' => 'Protecting your home', 'text' => 'Floor runners, door-jamb protectors and moving pads go down at both homes before anything moves.'],
                ['icon' => 'sofa', 'title' => 'Wrapping your furniture', 'text' => 'Furniture is pad-wrapped and mattresses are bagged, so everything arrives the way it left.'],
                ['icon' => 'wrench', 'title' => 'Taking furniture apart', 'text' => 'Beds, tables and other large pieces are taken apart and put back together on full-service moves.'],
                ['icon' => 'truck', 'title' => 'Loading and driving', 'text' => 'Your crew loads the truck carefully, drives to your new home and carries everything inside.'],
                ['icon' => 'package-check', 'title' => 'Packing, if you want it', 'text' => 'Add full or partial packing to any move. When our crew packs, all materials are included.'],
                ['icon' => 'warehouse', 'title' => 'Storage, if you need it', 'text' => 'New home not ready yet? We can store your belongings and deliver them when you have the keys.'],
            ],
            'background' => 'muted',
        ]],
        ['type' => 'pricing_factors', 'data' => [
            'eyebrow' => 'Pricing',
            'heading' => 'How your price is worked out',
            'intro' => 'Local moves are usually priced by the hour. Long-distance moves get a flat, written price. These are the things that shape your estimate.',
            'factors' => [
                ['icon' => 'house', 'title' => 'Size of your move', 'text' => 'How much you are moving decides the crew size, the truck and the time it takes.'],
                ['icon' => 'map-pin', 'title' => 'Distance and access', 'text' => 'How far you are moving, plus stairs, elevators and long walks from the truck at both homes.'],
                ['icon' => 'package-check', 'title' => 'Packing and extras', 'text' => 'Packing, materials, storage and heavy items such as pianos or gun safes.'],
            ],
            'note' => 'Your estimate lists everything that affects the price. If something changes, we tell you before any work is done.',
            'background' => 'light',
        ]],
        ['type' => 'rich_text', 'data' => [
            'heading' => 'How to get ready for moving day',
            'content' => '<p>A little preparation keeps your move on schedule. Before the crew arrives:</p><ul><li>Finish packing anything you are packing yourself.</li><li>Empty drawers of loose or fragile items.</li><li>Reserve elevators and parking if your building requires it.</li><li>Set aside valuables, medication and important documents to take with you.</li><li>Tell us about any large or unusual items that were not in your quote.</li></ul>',
            'width' => 'narrow',
            'background' => 'muted',
        ]],
        ['type' => 'reviews_slider', 'data' => [
            'eyebrow' => 'Reviews',
            'heading' => 'What our customers say',
            'mode' => 'featured',
            'limit' => 6,
            'show_summary' => false,
            'button' => ['label' => 'Read all reviews', 'url' => '/reviews'],
            'background' => 'light',
        ]],
        ['type' => 'faq', 'data' => [
            'heading' => 'Questions about the process',
            'mode' => 'attached',
            'background' => 'muted',
        ]],
        ['type' => 'cta_banner', 'data' => [
            'heading' => 'Ready for step one?',
            'text' => 'Get a free, no-obligation quote in minutes, or call us to talk through your move.',
            'button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
            'show_phone' => true,
        ]],
    ],
];
