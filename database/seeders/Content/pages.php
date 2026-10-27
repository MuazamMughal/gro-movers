<?php

/*
| Starter content for the system pages: sections, SEO and FAQs. Keys are
| App\Enums\SystemPage values. "@hero" / "@about" are placeholder images.
| "faqs" picks questions from the global FAQ bank by their position (0-based).
*/

return [
    'home' => [
        'meta_title' => 'Movers in Dallas–Fort Worth | Free Moving Quote',
        'meta_description' => 'Local and long-distance movers serving Dallas, Plano and Irving. Packing, storage and office moves with clear, written quotes. Get your free quote today.',
        'faqs' => [0, 1, 3, 4, 14],
        'sections' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Dallas–Fort Worth movers',
                'heading' => 'Stress-Free Moving in Dallas–Fort Worth',
                'subheading' => 'Local and long-distance moves, packing and storage by careful, on-time crews. Get a clear, written quote in minutes.',
                'bullets' => [['text' => 'Written, itemized quotes'], ['text' => 'Furniture wrapped and protected'], ['text' => 'Licensed and insured'], ['text' => 'Moves seven days a week']],
                'background_image' => '@hero',
                'show_quote_form' => true,
                'theme' => 'dark',
            ]],
            ['type' => 'trust_bar', 'data' => [
                'stats' => [
                    ['value' => 'Since 2015', 'label' => 'Moving North Texas'],
                    ['value' => '6', 'label' => 'Moving services'],
                    ['value' => '7 days', 'label' => 'A week, by appointment'],
                    ['value' => 'Free', 'label' => 'Written quotes'],
                ],
                'badges' => [
                    ['icon' => 'shield-check', 'label' => 'Licensed & insured'],
                    ['icon' => 'receipt', 'label' => 'No hidden fees'],
                    ['icon' => 'users', 'label' => 'Trained, uniformed crews'],
                ],
                'background' => 'light',
            ]],
            ['type' => 'services_grid', 'data' => [
                'eyebrow' => 'What we do',
                'heading' => 'Moving services for every kind of move',
                'intro' => 'From a studio apartment to a full office, choose the help you need.',
                'mode' => 'home',
                'background' => 'muted',
            ]],
            ['type' => 'steps', 'data' => [
                'eyebrow' => 'How it works',
                'heading' => 'Moving made simple',
                'steps' => [
                    ['title' => 'Get your free quote', 'text' => 'Tell us where you are moving and when. We reply with a written estimate, usually the same day.'],
                    ['title' => 'We plan your move', 'text' => 'Your coordinator confirms the date, crew size and any items that need extra care.'],
                    ['title' => 'Moving day', 'text' => 'Our crew wraps, loads, drives and unloads, then sets up your furniture.'],
                    ['title' => 'Settle in', 'text' => 'We do a final walk-through with you so nothing is forgotten.'],
                ],
                'button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
                'background' => 'light',
            ]],
            ['type' => 'cards_grid', 'data' => [
                'eyebrow' => 'Why {company}',
                'heading' => 'Movers who treat your home like their own',
                'columns' => '3',
                'cards' => [
                    ['icon' => 'receipt', 'title' => 'Honest, written pricing', 'text' => 'Every quote is itemized. If something changes, we tell you before we start.'],
                    ['icon' => 'sofa', 'title' => 'Careful with your things', 'text' => 'Furniture is pad-wrapped, mattresses bagged and floors protected on every move.'],
                    ['icon' => 'clock', 'title' => 'On time, every time', 'text' => 'We confirm your arrival window the day before and call when we are on the way.'],
                    ['icon' => 'headset', 'title' => 'One point of contact', 'text' => 'A coordinator who knows your move answers your calls from quote to delivery.'],
                    ['icon' => 'package-check', 'title' => 'Packing and storage', 'text' => 'Need more help? Add packing, unpacking or storage to any move.'],
                    ['icon' => 'map-pin', 'title' => 'Local crews', 'text' => 'Our movers live and work in Dallas–Fort Worth and know the area well.'],
                ],
                'background' => 'muted',
            ]],
            ['type' => 'areas_grid', 'data' => [
                'eyebrow' => 'Where we move',
                'heading' => 'Serving Dallas–Fort Worth',
                'intro' => 'Local movers in these cities, and long-distance moves across the country.',
                'mode' => 'all',
                'background' => 'light',
            ]],
            ['type' => 'reviews_slider', 'data' => [
                'eyebrow' => 'Reviews',
                'heading' => 'What our customers say',
                'mode' => 'featured',
                'limit' => 6,
                'show_summary' => false,
                'button' => ['label' => 'Read all reviews', 'url' => '/reviews'],
                'background' => 'muted',
            ]],
            ['type' => 'faq', 'data' => [
                'heading' => 'Moving questions, answered',
                'mode' => 'attached',
                'background' => 'light',
            ]],
            ['type' => 'cta_banner', 'data' => [
                'heading' => 'Ready to plan your move?',
                'text' => 'Get a free, no-obligation quote in minutes, or call us to talk it through.',
                'button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
                'show_phone' => true,
                'theme' => 'dark',
            ]],
        ],
    ],

    'about' => [
        'meta_title' => 'About Us | Local Dallas–Fort Worth Moving Company',
        'meta_description' => 'Meet the team behind your move. A local Dallas–Fort Worth moving company built on careful crews, honest pricing and on-time service since 2015.',
        'sections' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'About us',
                'heading' => 'A Local Moving Company Built on Care',
                'subheading' => 'Since 2015 we have helped families and businesses across Dallas–Fort Worth move with less stress.',
                'theme' => 'dark',
            ]],
            ['type' => 'image_text', 'data' => [
                'heading' => 'Our story',
                'content' => '<p>{company} started with a simple idea: moving should not be the most stressful week of the year. Too many people had told us about late crews, surprise fees and damaged furniture. We set out to build a moving company that does the opposite.</p><p>Today our crews move apartments, family homes and offices across Dallas, Plano, Irving and the surrounding cities, along with long-distance moves across the country. We have grown, but the idea has not changed: show up on time, protect every item, and charge the price we quoted.</p>',
                'image' => '@about',
                'image_position' => 'right',
                'background' => 'light',
            ]],
            ['type' => 'cards_grid', 'data' => [
                'heading' => 'What we stand for',
                'columns' => '3',
                'cards' => [
                    ['icon' => 'receipt', 'title' => 'Honesty', 'text' => 'Written quotes, clear rates and no surprises on the final bill.'],
                    ['icon' => 'heart-handshake', 'title' => 'Respect', 'text' => 'For your home, your belongings and your time.'],
                    ['icon' => 'award', 'title' => 'Craft', 'text' => 'Trained crews who know how to pack, wrap and carry properly.'],
                ],
                'background' => 'muted',
            ]],
            ['type' => 'rich_text', 'data' => [
                'heading' => 'Our crews',
                'content' => '<p>Every {company} mover is a trained, uniformed member of our team. New crew members learn from experienced movers on real jobs before they lead a move, and every crew follows the same checklist: protect the floors, wrap the furniture, load heavy items first and check every room before leaving.</p><p>We are proud of the reviews our customers leave, and we read every one. If something ever falls short, we want to hear about it, so we can make it right.</p>',
                'width' => 'narrow',
                'background' => 'light',
            ]],
            ['type' => 'cta_banner', 'data' => [
                'heading' => 'Let us move you next',
                'text' => 'Get a free, written quote for your move.',
                'button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
                'show_phone' => true,
                'theme' => 'light',
            ]],
        ],
    ],

    'services_index' => [
        'meta_title' => 'Moving Services | Local, Long-Distance, Packing & More',
        'meta_description' => 'Local and long-distance moving, residential and commercial moves, packing and storage in Dallas–Fort Worth. Compare our moving services and get a quote.',
        'faqs' => [3, 8, 9, 10],
        'sections' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Our services',
                'heading' => 'Moving Services in Dallas–Fort Worth',
                'subheading' => 'Choose exactly the help you need, from a few heavy items to a full-service move with packing and storage.',
                'theme' => 'dark',
            ]],
            ['type' => 'services_grid', 'data' => [
                'heading' => 'Find the right service for your move',
                'mode' => 'all',
                'background' => 'light',
            ]],
            ['type' => 'faq', 'data' => ['heading' => 'Questions about our services', 'mode' => 'attached', 'background' => 'muted']],
        ],
    ],

    'areas_index' => [
        'meta_title' => 'Service Areas | Movers in Dallas, Plano & Irving, TX',
        'meta_description' => 'We move homes and businesses in Dallas, Plano, Irving and across Dallas–Fort Worth, plus long-distance moves nationwide. Find movers in your city.',
        'sections' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Service areas',
                'heading' => 'Movers Across Dallas–Fort Worth',
                'subheading' => 'Local crews who know your city, and long-distance moves to anywhere in the continental United States.',
                'theme' => 'dark',
            ]],
            ['type' => 'rich_text', 'data' => [
                'heading' => 'Local movers who know North Texas',
                'content' => '<p>Our crews move families and businesses across the Dallas–Fort Worth area every day. We know the high-rise rules in Uptown, the HOA guidelines in Plano and the apartment loading docks in Las Colinas. Choose your city below for local details, or contact us if you do not see yours listed: we serve many nearby communities too.</p>',
                'width' => 'narrow',
                'background' => 'light',
            ]],
        ],
    ],

    'reviews_index' => [
        'meta_title' => 'Customer Reviews | What People Say About Our Movers',
        'meta_description' => 'Read reviews from families and businesses we have moved in Dallas, Plano and Irving. See why customers recommend our movers to friends and neighbors.',
        'sections' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Reviews',
                'heading' => 'Reviews From Our Customers',
                'subheading' => 'Real feedback from families and businesses we have moved across Dallas–Fort Worth.',
                'theme' => 'dark',
            ]],
        ],
    ],

    'blog_index' => [
        'meta_title' => 'Moving Tips, Packing Guides & Cost Guides | Blog',
        'meta_description' => 'Practical moving tips, step-by-step packing guides and honest moving cost guides from our Dallas–Fort Worth moving crews. Plan your move with confidence.',
        'sections' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Blog',
                'heading' => 'Moving Tips and Guides',
                'subheading' => 'Advice from our crews to help you plan, pack and budget for your move.',
                'theme' => 'dark',
            ]],
        ],
    ],

    'contact' => [
        'meta_title' => 'Contact Us | Talk to a Dallas–Fort Worth Mover',
        'meta_description' => 'Questions about your move? Call, email or send us a message. Our Dallas–Fort Worth team replies within one business day, often much sooner.',
        'faqs' => [0, 6, 13],
        'sections' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Contact',
                'heading' => 'Get in Touch',
                'subheading' => 'Call us, send a message or stop by. For a price on your move, the quote form is the fastest way.',
                'primary_button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
                'theme' => 'dark',
            ]],
            ['type' => 'contact_form', 'data' => [
                'heading' => 'Send us a message',
                'intro' => 'We reply within one business day.',
                'show_details' => true,
            ]],
            ['type' => 'map', 'data' => ['heading' => 'Our office', 'height' => 'md']],
        ],
    ],

    'quote' => [
        'meta_title' => 'Get a Free Moving Quote | Fast, Written Estimates',
        'meta_description' => 'Request a free, no-obligation moving quote in minutes. Tell us your addresses, moving date and type of move, and we reply with a clear, written estimate.',
        'faqs' => [3, 4, 5],
        'sections' => [
            ['type' => 'hero', 'data' => [
                'eyebrow' => 'Free quote',
                'heading' => 'Get Your Free Moving Quote',
                'subheading' => 'It takes about two minutes. We reply with a clear, written estimate, usually the same business day.',
                'theme' => 'dark',
            ]],
            ['type' => 'quote_form', 'data' => [
                'heading' => 'Tell us about your move',
                'intro' => 'No obligation and no hidden fees. Your details are only used to prepare your quote.',
                'bullets' => [['text' => 'Written, itemized estimate'], ['text' => 'Local and long-distance moves'], ['text' => 'Licensed and insured movers']],
                'background' => 'muted',
            ]],
            ['type' => 'faq', 'data' => ['heading' => 'Before you request a quote', 'mode' => 'attached', 'background' => 'light']],
        ],
    ],

    'thank_you' => [
        'meta_title' => 'Thank You | We Received Your Request',
        'meta_description' => 'Thank you for contacting us. A member of our team will review your request and get back to you shortly with the details.',
        'sections' => [], // the built-in confirmation and next steps
    ],

    'privacy' => [
        'admin_note' => 'Template text: review this Privacy Policy with your lawyer and update it to match how you actually collect and use data before launch.',
        'meta_title' => 'Privacy Policy',
        'meta_description' => 'How we collect, use and protect the personal information you share with us when you request a quote, contact us or visit our website.',
        'sections' => [
            ['type' => 'rich_text', 'data' => [
                'content' => '<p><em>Last updated: [date]. This is a template and must be reviewed by a lawyer before use.</em></p><h2>Who we are</h2><p>{company} (“we”, “us”) operates this website. This policy explains what personal information we collect, why we collect it and how we protect it.</p><h2>Information we collect</h2><ul><li><strong>Quote and contact forms:</strong> your name, email address, phone number, moving addresses, moving date, type of move and any message you send.</li><li><strong>Website usage:</strong> pages visited, the page you came from and campaign parameters, and technical data such as your IP address and browser, collected through cookies and analytics tools.</li></ul><h2>How we use your information</h2><ul><li>To prepare your moving quote and contact you about your move.</li><li>To answer your questions and provide our services.</li><li>To improve our website and understand which pages are useful.</li><li>To meet legal and accounting obligations.</li></ul><h2>Phone, text and email</h2><p>If you request a quote, we may contact you by phone, text message or email about your move, as described next to our quote form. You can ask us to stop at any time.</p><h2>Sharing your information</h2><p>We do not sell your personal information. We share it only with service providers who help us run our business (for example email, hosting and analytics providers), and when required by law.</p><h2>Cookies and analytics</h2><p>We use cookies and similar tools to understand how visitors use our website. You can block or delete cookies in your browser settings.</p><h2>Data retention and security</h2><p>We keep your information only as long as needed for the purposes above and protect it with reasonable technical and organizational measures.</p><h2>Your choices</h2><p>You can ask us to access, correct or delete your personal information by contacting us at {phone} or through our contact page.</p><h2>Contact</h2><p>Questions about this policy? Contact {company} at {phone}.</p>',
                'width' => 'narrow',
                'background' => 'light',
            ]],
        ],
    ],

    'terms' => [
        'admin_note' => 'Template text: review these Terms of Service with your lawyer and align them with your moving contracts and local regulations before launch.',
        'meta_title' => 'Terms of Service',
        'meta_description' => 'The terms that apply when you use our website and request a moving quote, including estimates, bookings and limitations of liability.',
        'sections' => [
            ['type' => 'rich_text', 'data' => [
                'content' => '<p><em>Last updated: [date]. This is a template and must be reviewed by a lawyer before use.</em></p><h2>Using this website</h2><p>By using this website you agree to these terms. The content is provided for general information about the services of {company}.</p><h2>Quotes and estimates</h2><p>Quotes requested through this website are estimates based on the information you provide. Your final price and the terms of your move are set out in the written estimate and moving agreement you receive from us.</p><h2>Bookings and changes</h2><p>A move is confirmed only when we confirm it in writing. Please tell us about changes to your date, addresses or inventory as early as possible, as they may change the price.</p><h2>Liability and valuation</h2><p>Our liability for your belongings during a move is described in your moving agreement and the valuation coverage you choose. This website does not create any additional warranty.</p><h2>Website content</h2><p>We work to keep the information on this website accurate and up to date, but we do not guarantee that it is complete or error-free. Content may change without notice.</p><h2>Links to other websites</h2><p>Links to other websites are provided for convenience. We are not responsible for their content or privacy practices.</p><h2>Contact</h2><p>Questions about these terms? Contact {company} at {phone}.</p>',
                'width' => 'narrow',
                'background' => 'light',
            ]],
        ],
    ],
];
