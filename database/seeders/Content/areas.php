<?php

/*
| Starter content: service areas, the shared city × service template, and the
| global FAQ bank. Tokens: {company} {phone} {city} {state} {state_code} {service}.
*/

return [
    'areas' => [
        [
            'city' => 'Dallas', 'state_name' => 'Texas', 'state_code' => 'TX', 'slug' => 'dallas-tx',
            'lat' => 32.7767, 'lng' => -96.7970,
            'zip_codes' => ['75201', '75204', '75205', '75206', '75214', '75219', '75225', '75230', '75231', '75248'],
            'intro' => 'From Uptown high-rises to family homes in Lake Highlands and Preston Hollow, our Dallas movers handle every kind of move across the city.',
            'meta_title' => 'Movers in Dallas, TX | Local & Long-Distance Moving',
            'meta_description' => 'Trusted Dallas movers for apartments, homes and offices. Local and long-distance moving, packing and storage across Dallas, TX. Get a free quote.',
            'content' => '<p>Dallas is a city of contrasts, and so are the moves we handle here. One day it is a high-rise apartment in Uptown with a reserved freight elevator and a tight loading-dock window. The next it is a two-story home in Lake Highlands with a long driveway and a piano in the living room. Our Dallas crews plan for both.</p><p>We know the building rules in Downtown and Uptown, where many properties require a certificate of insurance and a booked elevator. We know that traffic on I-35E and the Woodall Rodgers Freeway can turn a short drive into a long one, and we schedule around it. And we know that summer in North Texas means early starts, plenty of water and extra care with anything that does not like the heat.</p><h3>Moving in and around Dallas</h3><p>Whether you are moving across the street, to a suburb like Plano or Irving, or out of Texas entirely, {company} gives you one clear plan and one written price.</p>',
            'faqs' => [
                ['Do you handle high-rise moves in Uptown and Downtown Dallas?', '<p>Yes. We regularly move residents in Dallas high-rises and work with building managers on elevator reservations, loading-dock times and insurance certificates.</p>'],
                ['How early can a Dallas move start?', '<p>In summer we often start at 7 or 8 a.m. to beat the afternoon heat. Your arrival window is confirmed the day before.</p>'],
            ],
            'icon' => 'building',
        ],
        [
            'city' => 'Plano', 'state_name' => 'Texas', 'state_code' => 'TX', 'slug' => 'plano-tx',
            'lat' => 33.0198, 'lng' => -96.6989,
            'zip_codes' => ['75023', '75024', '75025', '75074', '75075', '75093', '75094'],
            'intro' => 'Family homes, new builds and corporate relocations: our Plano movers know West Plano, Legacy and every neighborhood in between.',
            'meta_title' => 'Movers in Plano, TX | Home & Corporate Relocation',
            'meta_description' => 'Plano movers for family homes, apartments and corporate relocations. Careful crews, packing and storage in Plano, TX. Request your free quote today.',
            'content' => '<p>Plano is one of the busiest moving cities in North Texas. Families move here for the schools and parks, and companies around the Legacy business district bring new employees from across the country. {company} moves both.</p><p>Many Plano homes have two stories, large furniture and garages full of tools and sports gear. Our crews arrive with the equipment to move heavy items safely up and down stairs, and we protect hardwood floors, banisters and door frames along the way. HOA communities often have rules about truck parking and moving hours; we check them before moving day.</p><h3>Corporate relocations to Plano</h3><p>Relocating for work? We coordinate with your schedule and your employer’s relocation team, and we can store your belongings while you find the right home.</p>',
            'faqs' => [
                ['Do you work with HOA rules in Plano neighborhoods?', '<p>Yes. Tell us your HOA’s rules for moving trucks and hours, and we will plan parking and timing around them.</p>'],
                ['Can you store our things while we close on a Plano home?', '<p>Yes. Our storage service bridges the gap between moving out and moving in, with pickup and delivery included.</p>'],
            ],
            'icon' => 'house',
        ],
        [
            'city' => 'Irving', 'state_name' => 'Texas', 'state_code' => 'TX', 'slug' => 'irving-tx',
            'lat' => 32.8140, 'lng' => -96.9489,
            'zip_codes' => ['75038', '75039', '75060', '75061', '75062', '75063'],
            'intro' => 'Apartments in Las Colinas, family homes in Valley Ranch and offices near DFW Airport: our Irving movers cover it all.',
            'meta_title' => 'Movers in Irving, TX | Las Colinas & Valley Ranch',
            'meta_description' => 'Irving movers for apartments, homes and offices in Las Colinas, Valley Ranch and across Irving, TX. Local and long-distance moves. Free quote.',
            'content' => '<p>Irving sits between Dallas and Fort Worth, right next to DFW Airport, which makes it a popular home for frequent travelers, young professionals and growing families. Our Irving movers handle apartment moves in Las Colinas, family moves in Valley Ranch and office moves along the State Highway 114 corridor.</p><p>Apartment communities in Irving often have strict move-in and move-out hours and require trucks to use specific loading areas. We confirm those details in advance, so your move fits the schedule and your deposit stays safe.</p><h3>Office moves in Irving</h3><p>Irving is home to many corporate offices. {company} plans commercial moves after hours and on weekends to keep your business running.</p>',
            'faqs' => [
                ['Do you move apartments in Las Colinas?', '<p>Yes. We move apartments and condos across Las Colinas and work within each community’s moving hours and loading rules.</p>'],
                ['Can you move our Irving office over a weekend?', '<p>Yes. Weekend and after-hours office moves are common in Irving, so your team can start Monday in the new space.</p>'],
            ],
            'icon' => 'pin',
        ],
    ],

    // Shared city × service template (all 18 combinations use it).
    'template' => [
        ['type' => 'rich_text', 'data' => [
            'heading' => '{service} in {city}, {state_code}',
            'content' => '<p>Looking for reliable {service} in {city}? {company} provides careful, on-time moving crews who know {city} and the surrounding area. Every move starts with a clear, written estimate, so you know the price before moving day.</p><p>Our crews arrive with padded trucks, dollies, straps and floor protection. We wrap furniture, protect your home and handle every item as if it were our own, whether you are moving a single room or an entire house.</p><p>Planning a move in {city}, {state}? Call {phone} or request a free quote online. We usually reply the same business day.</p>',
            'width' => 'narrow',
            'background' => 'light',
        ]],
        ['type' => 'cards_grid', 'data' => [
            'heading' => 'Why {city} chooses {company}',
            'columns' => '3',
            'cards' => [
                ['icon' => 'map-pin', 'title' => 'Local knowledge', 'text' => 'Our crews know {city} streets, buildings and parking rules.'],
                ['icon' => 'receipt', 'title' => 'Written estimates', 'text' => 'A clear, itemized price for your {service} before moving day.'],
                ['icon' => 'shield-check', 'title' => 'Careful handling', 'text' => 'Furniture wrapped and floors protected on every move.'],
            ],
            'background' => 'muted',
        ]],
        ['type' => 'steps', 'data' => [
            'heading' => 'How {service} in {city} works',
            'steps' => [
                ['title' => 'Get a free quote', 'text' => 'Tell us about your move in {city} and get a written estimate.'],
                ['title' => 'Confirm the plan', 'text' => 'We confirm the date, arrival window and any special items.'],
                ['title' => 'Moving day', 'text' => 'Your crew wraps, loads, drives and sets up at your new place.'],
            ],
            'background' => 'light',
        ]],
        ['type' => 'reviews_slider', 'data' => [
            'heading' => 'What our customers in {city} say',
            'mode' => 'context',
            'limit' => 6,
            'show_summary' => true,
            'background' => 'muted',
        ]],
    ],

    // Global FAQ bank (attached to the home, services index, quote and contact pages).
    'faqs' => [
        ['How do I get a moving quote?', '<p>Fill in the free quote form with your addresses, moving date and type of move, or call us. We reply with a clear, written estimate, usually the same business day.</p>'],
        ['Are your movers licensed and insured?', '<p>Yes. {company} is a licensed and insured moving company. Ask us for details of our coverage and valuation options for your move.</p>'],
        ['How far in advance should I book my move?', '<p>Two to four weeks ahead is ideal for local moves, and four to eight weeks for long-distance moves. Summer weekends and month-ends fill up fastest.</p>'],
        ['Do you charge by the hour or a flat rate?', '<p>Local moves are usually priced by the hour. Long-distance moves get a flat, written price based on the size of your shipment and the distance.</p>'],
        ['Are there any hidden fees?', '<p>No. Your estimate lists everything that affects the price, such as packing materials, stairs or long carries. If something changes, we tell you before any work is done.</p>'],
        ['What do I need to do before the movers arrive?', '<p>Pack anything you are packing yourself, empty drawers of loose or fragile items, reserve elevators or parking if needed, and set aside valuables and documents to take with you.</p>'],
        ['Do you move on weekends?', '<p>Yes. We move seven days a week. Weekend dates are popular, so book early if you need a Saturday or Sunday.</p>'],
        ['What items can’t you move?', '<p>For safety we cannot move hazardous materials such as propane tanks, gasoline, paint, chemicals or ammunition. Perishable food and plants are best moved by you.</p>'],
        ['Do you move pianos and other heavy items?', '<p>Yes. Pianos, gun safes and other heavy items need extra planning and equipment. Mention them in your quote request so we bring the right crew and tools.</p>'],
        ['Can you disassemble and reassemble furniture?', '<p>Yes. Taking apart and putting back together beds, tables and other furniture is included in our full-service moves.</p>'],
        ['Do you offer packing materials?', '<p>Yes. When our crew packs, all materials are included. You can also buy boxes, tape and packing paper from us if you prefer to pack yourself.</p>'],
        ['What happens if something is damaged?', '<p>We take great care to prevent damage. If it does happen, contact us right away and we will walk you through the valuation coverage and claims process.</p>'],
        ['How do you protect my home during the move?', '<p>We use floor runners, door-jamb protectors and moving pads to protect your floors, walls and furniture at both homes.</p>'],
        ['Can I change my moving date?', '<p>Yes. Let us know as early as possible and we will do our best to move your date. Short-notice changes depend on availability.</p>'],
        ['Which areas do you serve?', '<p>We serve Dallas, Plano, Irving and the wider Dallas–Fort Worth area, and we handle long-distance moves to destinations across the United States.</p>'],
    ],

    // Sample reviews (flagged as samples: replace with real ones before launch).
    'reviews' => [
        ['name' => 'Jessica M.', 'city' => 'Dallas, TX', 'area' => 'dallas-tx', 'service' => 'local-moving', 'rating' => 5, 'featured' => true, 'body' => 'The crew showed up right on time and had our apartment loaded in under three hours. Everything was wrapped, nothing was scratched, and they even set up our bed before leaving.'],
        ['name' => 'Marcus T.', 'city' => 'Plano, TX', 'area' => 'plano-tx', 'service' => 'residential-moving', 'rating' => 5, 'featured' => true, 'body' => 'We moved a four-bedroom house with two kids and a dog. The team was patient, careful with our floors and made a stressful day feel easy. The price matched the estimate.'],
        ['name' => 'Priya S.', 'city' => 'Irving, TX', 'area' => 'irving-tx', 'service' => 'packing-services', 'rating' => 5, 'featured' => true, 'body' => 'I booked full packing and it was worth every penny. They packed our entire kitchen in a couple of hours and every box was labeled. Unpacking was a breeze.'],
        ['name' => 'David R.', 'city' => 'Dallas, TX', 'area' => 'dallas-tx', 'service' => 'long-distance-moving', 'rating' => 5, 'featured' => false, 'body' => 'Moved from Dallas to Denver. Our coordinator kept us updated the whole way and the truck arrived in the delivery window they promised. Great experience.'],
        ['name' => 'Angela W.', 'city' => 'Plano, TX', 'area' => 'plano-tx', 'service' => 'storage-services', 'rating' => 4, 'featured' => false, 'body' => 'Our closing got pushed back two weeks, so they stored everything for us and delivered as soon as we got the keys. Smooth and well organized.'],
        ['name' => 'Kevin L.', 'city' => 'Irving, TX', 'area' => 'irving-tx', 'service' => 'commercial-moving', 'rating' => 5, 'featured' => true, 'body' => 'They moved our 20-person office over a weekend. Every desk and monitor was in the right spot on Monday morning. Very professional team.'],
        ['name' => 'Sofia G.', 'city' => 'Dallas, TX', 'area' => 'dallas-tx', 'service' => 'local-moving', 'rating' => 5, 'featured' => false, 'body' => 'High-rise move in Uptown with a tight elevator window. They planned it perfectly and finished early. Friendly, fast and careful.'],
        ['name' => 'Brian H.', 'city' => 'Plano, TX', 'area' => 'plano-tx', 'service' => 'local-moving', 'rating' => 4, 'featured' => false, 'body' => 'Good crew, worked hard in the heat and took care of our furniture. Arrived a little later than planned but called ahead to let us know.'],
        ['name' => 'Rachel K.', 'city' => 'Irving, TX', 'area' => 'irving-tx', 'service' => 'residential-moving', 'rating' => 5, 'featured' => false, 'body' => 'From the quote to the last box, everything was clear and honest. The movers were polite and treated our home with respect.'],
        ['name' => 'Tom B.', 'city' => 'Dallas, TX', 'area' => 'dallas-tx', 'service' => 'packing-services', 'rating' => 5, 'featured' => false, 'body' => 'They packed and moved my late mother’s china and artwork without a single chip. I can’t thank them enough for the care they took.'],
        ['name' => 'Lauren P.', 'city' => 'Plano, TX', 'area' => 'plano-tx', 'service' => 'long-distance-moving', 'rating' => 5, 'featured' => false, 'body' => 'Relocated from Plano to Atlanta for work. Flat price, no surprises, and our furniture arrived in perfect shape. Would use them again.'],
        ['name' => 'Chris N.', 'city' => 'Irving, TX', 'area' => 'irving-tx', 'service' => 'local-moving', 'rating' => 4, 'featured' => false, 'body' => 'Quick apartment move in Las Colinas. The crew knew the building rules and got us in and out within the allowed hours. Solid service.'],
    ],
];
