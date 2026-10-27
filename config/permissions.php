<?php

/*
|--------------------------------------------------------------------------
| Admin permissions (T19, docs/02-build-prompt.md §14.1)
|--------------------------------------------------------------------------
| Defined once here, grouped for the Roles screen. Each key is also a Gate
| ability (`can:pages` etc.). Users and Roles are not listed: only the
| Administrator role manages them.
*/

return [
    'groups' => [
        'Content' => [
            'pages' => 'Pages',
            'services' => 'Services and Area × Service pages',
            'areas' => 'Service areas',
            'reviews' => 'Reviews',
            'faqs' => 'FAQs',
            'media' => 'Media Library (manage, replace and delete images)',
        ],
        'Blog' => [
            'blog' => 'Posts, categories, authors and tags',
        ],
        'Leads' => [
            'leads.view' => 'See quote requests',
            'leads.manage' => 'Update quote requests (status, notes, delete)',
            'leads.export' => 'Export quote requests (CSV)',
            'messages' => 'Contact messages',
        ],
        'Site' => [
            'menus' => 'Menus',
            'move_types' => 'Move types',
            'seo' => 'SEO Tools (report, redirects, robots.txt)',
            'settings' => 'Settings',
            'static' => 'Rebuild the website',
        ],
    ],

    /** Permissions that let a user add images from the picker inside an editor. */
    'content' => ['pages', 'services', 'areas', 'reviews', 'blog', 'media', 'menus'],

    /** Editable roles created on install (the Administrator role is built in). */
    'defaults' => [
        'Content Editor' => [
            'description' => 'Edits pages, services, areas, blog, reviews, FAQs, images and menus.',
            'permissions' => ['pages', 'services', 'areas', 'blog', 'reviews', 'faqs', 'media', 'menus'],
        ],
        'Sales' => [
            'description' => 'Works quote requests and contact messages.',
            'permissions' => ['leads.view', 'leads.manage', 'leads.export', 'messages'],
        ],
        'SEO Manager' => [
            'description' => 'Runs SEO Tools and edits page content and SEO fields.',
            'permissions' => ['seo', 'pages', 'services', 'areas', 'blog', 'media', 'static'],
        ],
    ],
];
