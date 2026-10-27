<?php

/*
| Starter content: the six moving services. Original US-English placeholder copy,
| written to be edited. "@card" / "@hero" in section data are replaced by the
| service's placeholder images at seed time. {company} and {phone} are tokens.
*/

return [
    [
        'slug' => 'local-moving',
        'title' => 'Local Moving',
        'icon' => 'truck',
        'short' => 'Careful, on-time local moves across Dallas–Fort Worth with clear hourly pricing, trained crews and padded trucks.',
        'meta_title' => 'Local Movers in Dallas–Fort Worth | Free Quote',
        'meta_description' => 'Local moving in Dallas, Plano, Irving and nearby cities. Trained crews, padded trucks and clear hourly rates. Get your free local moving quote today.',
        'hero' => [
            'eyebrow' => 'Local moving',
            'heading' => 'Local Movers You Can Count On',
            'subheading' => 'From a one-bedroom apartment to a four-bedroom house, {company} moves you across town on time and on budget.',
            'bullets' => ['Clear hourly pricing', 'Furniture wrapped and protected', 'Same crew from start to finish'],
        ],
        'sections' => [
            ['type' => 'image_text', 'data' => [
                'eyebrow' => 'Across town, done right',
                'heading' => 'A local move without the stress',
                'content' => '<p>A local move looks simple until you are carrying a sofa down three flights of stairs in the Texas heat. Our local moving crews take that work off your hands. We arrive in a clean, padded truck with the tools for the job: dollies, straps, floor runners and plenty of moving blankets.</p><p>Before anything leaves your home, we wrap furniture, protect door frames and plan the order of loading so heavy items ride at the bottom and fragile boxes stay on top. At your new place we place every piece in the room you choose and reassemble beds and tables we took apart.</p><p>Most local moves in Dallas–Fort Worth are finished in a single day. You get a written estimate up front and an hourly rate that starts when we arrive, so there are no surprises on the final bill.</p>',
                'bullets' => [
                    ['text' => 'Apartments, condos, townhomes and houses'],
                    ['text' => 'Disassembly and reassembly of furniture'],
                    ['text' => 'Floor and door-frame protection included'],
                ],
                'image' => '@card',
                'image_position' => 'right',
                'background' => 'light',
            ]],
            ['type' => 'steps', 'data' => [
                'eyebrow' => 'How it works',
                'heading' => 'Your local move in four steps',
                'steps' => [
                    ['title' => 'Request a free quote', 'text' => 'Tell us your addresses, moving date and home size. We reply with a clear estimate, usually the same day.'],
                    ['title' => 'Plan the details', 'text' => 'We confirm the arrival window, parking, elevators and any items that need extra care.'],
                    ['title' => 'Moving day', 'text' => 'Your crew wraps, loads, drives and unloads, then sets up furniture in the rooms you choose.'],
                    ['title' => 'Walk-through', 'text' => 'We check every room with you before we leave, so nothing is forgotten.'],
                ],
                'background' => 'muted',
            ]],
            ['type' => 'pricing_factors', 'data' => [
                'eyebrow' => 'Pricing',
                'heading' => 'What affects the cost of a local move',
                'intro' => 'Local moves are priced by the hour. These are the things that change how long the job takes.',
                'factors' => [
                    ['icon' => 'house', 'title' => 'Size of your home', 'text' => 'More rooms and more furniture mean more time to wrap, carry and load.'],
                    ['icon' => 'route', 'title' => 'Distance and traffic', 'text' => 'Drive time between your two addresses is part of the hourly total.'],
                    ['icon' => 'building-2', 'title' => 'Stairs and elevators', 'text' => 'Walk-ups, long hallways and shared elevators add carrying time.'],
                    ['icon' => 'package', 'title' => 'Packing help', 'text' => 'Packed and labeled boxes load fastest. We can pack for you if you prefer.'],
                ],
                'note' => 'Every quote is written and itemized. Ask us how to keep your local move as short as possible.',
                'background' => 'light',
            ]],
        ],
        'faqs' => [
            ['How much does a local move cost?', '<p>Local moves are charged by the hour for the crew and truck. The total depends mostly on the size of your home, access at both addresses and how much is packed before we arrive. We give you a written estimate before moving day so you know what to expect.</p>'],
            ['How long does a local move take?', '<p>A one-bedroom apartment usually takes three to five hours. A three-bedroom house is often a full day. Your estimate includes the expected time for your move.</p>'],
            ['Do you take apart and set up furniture?', '<p>Yes. Taking apart beds, tables and other large items and putting them back together at your new home is part of every local move.</p>'],
            ['Can you move me on short notice?', '<p>Often, yes. Weekday dates are easiest to fit in at short notice. Call us and we will check the schedule while you are on the phone.</p>'],
        ],
    ],
    [
        'slug' => 'long-distance-moving',
        'title' => 'Long-Distance Moving',
        'icon' => 'route',
        'short' => 'Moving out of Texas or across the state? Flat-rate long-distance moves with a dedicated coordinator and tracked delivery windows.',
        'meta_title' => 'Long-Distance Movers from Dallas | Flat-Rate Quotes',
        'meta_description' => 'Long-distance and interstate moves from Dallas–Fort Worth with flat-rate pricing, a dedicated move coordinator and careful packing. Request a free quote.',
        'hero' => [
            'eyebrow' => 'Long-distance moving',
            'heading' => 'Long-Distance Moves Without the Guesswork',
            'subheading' => 'Flat-rate pricing, one coordinator from start to finish and a crew that treats your belongings like their own.',
            'bullets' => ['Flat-rate, written quotes', 'Dedicated move coordinator', 'Delivery window confirmed in advance'],
        ],
        'sections' => [
            ['type' => 'rich_text', 'data' => [
                'heading' => 'Moving out of state, simplified',
                'content' => '<p>A long-distance move has more moving parts than a local one: travel days, delivery windows, packing for a longer ride and paperwork for interstate moves. {company} brings it together into one plan, with one point of contact who answers your questions from the first call until the last box comes off the truck.</p><h3>Flat-rate pricing</h3><p>Long-distance moves are priced on the size of your shipment and the distance, not the clock. After a short walk-through, in person or by video, you receive a written, itemized price. If your inventory stays the same, the price stays the same.</p><h3>Packed for the road</h3><p>Furniture is wrapped in thick moving pads and shrink wrap, and loaded in tiers so nothing shifts on the highway. Fragile items get extra padding and are packed by our crew if you choose our packing service.</p><h3>Delivery you can plan around</h3><p>We confirm your delivery window before the truck leaves and keep you updated on the way, so you can schedule utilities, keys and time off work with confidence.</p>',
                'width' => 'narrow',
                'background' => 'light',
            ]],
            ['type' => 'cards_grid', 'data' => [
                'eyebrow' => 'Included',
                'heading' => 'What every long-distance move includes',
                'columns' => '3',
                'cards' => [
                    ['icon' => 'clipboard-check', 'title' => 'Inventory and plan', 'text' => 'A written inventory and a moving plan with dates for packing, loading and delivery.'],
                    ['icon' => 'shield-check', 'title' => 'Valuation coverage', 'text' => 'Standard valuation coverage is included. Ask about extra coverage for high-value items.'],
                    ['icon' => 'headset', 'title' => 'One coordinator', 'text' => 'A single contact who knows your move and keeps you updated on the road.'],
                ],
                'background' => 'muted',
            ]],
            ['type' => 'steps', 'data' => [
                'heading' => 'How a long-distance move works',
                'steps' => [
                    ['title' => 'Walk-through', 'text' => 'We review your home in person or by video to build an accurate inventory.'],
                    ['title' => 'Flat-rate quote', 'text' => 'You get a written price and a schedule for packing, loading and delivery.'],
                    ['title' => 'Packing and loading', 'text' => 'Our crew packs (if you choose), wraps furniture and loads the truck in one visit.'],
                    ['title' => 'Delivery', 'text' => 'We unload, place furniture and reassemble what we took apart at your new home.'],
                ],
                'background' => 'light',
            ]],
        ],
        'faqs' => [
            ['How is a long-distance move priced?', '<p>Long-distance moves are priced by the size of your shipment and the distance traveled. You receive a flat, written quote after a walk-through, so the price does not change with traffic or travel time.</p>'],
            ['How far in advance should I book a long-distance move?', '<p>Four to eight weeks ahead is ideal, especially in summer and at the end of the month. We can sometimes help with shorter notice, so it is always worth asking.</p>'],
            ['Will my belongings share a truck with other moves?', '<p>Ask us about a dedicated truck for your move. Your coordinator will explain the options and how they affect timing and price.</p>'],
            ['Do you move to every state?', '<p>We move households from Dallas–Fort Worth to destinations across the continental United States. Tell us where you are going and we will confirm the details.</p>'],
        ],
    ],
    [
        'slug' => 'residential-moving',
        'title' => 'Residential Moving',
        'icon' => 'house',
        'short' => 'Full-service home moves for apartments, condos and family houses, planned around your schedule and your family.',
        'meta_title' => 'Residential Movers in Dallas–Fort Worth | Home Moves',
        'meta_description' => 'Residential moving for apartments, condos and family homes in Dallas, Plano and Irving. Careful crews, furniture protection and honest pricing.',
        'hero' => [
            'eyebrow' => 'Residential moving',
            'heading' => 'Home Moves Planned Around Your Family',
            'subheading' => 'Apartments, condos and houses of every size, moved with care by a crew that respects your home.',
            'bullets' => ['Apartments to five-bedroom homes', 'Kids’ rooms and nurseries set up first', 'Pet-friendly planning'],
        ],
        'sections' => [
            ['type' => 'image_text', 'data' => [
                'heading' => 'Every home is different. So is every move.',
                'content' => '<p>A residential move is personal. It is your furniture, your family photos and the little things that make a house feel like home. {company} plans every home move around the people in it: school schedules, work calls, pets and the rooms you need set up first.</p><p>We start by learning about your home and what matters most to you. On moving day, your crew protects floors and doorways, wraps furniture in clean pads and labels each piece so it lands in the right room. Beds and cribs go together first, so the first night in your new home is a comfortable one.</p>',
                'bullets' => [
                    ['text' => 'Apartment, condo, townhome and house moves'],
                    ['text' => 'Appliance disconnection and reconnection help'],
                    ['text' => 'Packing and unpacking available'],
                ],
                'image' => '@card',
                'image_position' => 'left',
                'background' => 'light',
            ]],
            ['type' => 'cards_grid', 'data' => [
                'eyebrow' => 'Why families choose us',
                'heading' => 'Care you can see on moving day',
                'columns' => '3',
                'cards' => [
                    ['icon' => 'sofa', 'title' => 'Furniture protection', 'text' => 'Every piece is pad-wrapped. Mattresses go in clean covers.'],
                    ['icon' => 'users', 'title' => 'Background-checked crews', 'text' => 'The people in your home are trained, uniformed employees.'],
                    ['icon' => 'clock', 'title' => 'On-time arrival', 'text' => 'We confirm your arrival window the day before and call when we are on the way.'],
                ],
                'background' => 'muted',
            ]],
            ['type' => 'rich_text', 'data' => [
                'heading' => 'Tips for a smoother home move',
                'content' => '<ul><li><strong>Start with the rooms you use least.</strong> Guest rooms, storage closets and the garage can be packed weeks ahead.</li><li><strong>Pack an essentials box.</strong> Chargers, medicine, a change of clothes, coffee and toilet paper should travel with you.</li><li><strong>Label by room and priority.</strong> “Kitchen – open first” saves a lot of searching later.</li><li><strong>Reserve elevators and parking.</strong> Apartment and HOA rules often require a booking for moving day.</li></ul>',
                'width' => 'narrow',
                'background' => 'light',
            ]],
        ],
        'faqs' => [
            ['Do you move both apartments and houses?', '<p>Yes. We move studios, apartments, condos, townhomes and houses of every size, including homes with stairs, tight hallways and long carries.</p>'],
            ['Can you help with appliances?', '<p>Our crew can move washers, dryers and refrigerators and help with basic disconnection and reconnection. Gas and water lines should be handled by a licensed technician.</p>'],
            ['What should I pack myself?', '<p>Keep valuables, important documents, medicine and jewelry with you. Everything else can be packed by you or by our crew.</p>'],
            ['Will you protect my floors and walls?', '<p>Yes. Floor runners, door-jamb protectors and moving pads are standard on every residential move.</p>'],
        ],
    ],
    [
        'slug' => 'commercial-moving',
        'title' => 'Commercial Moving',
        'icon' => 'building',
        'short' => 'Office, retail and small-business moves planned around your hours, with labeled crates, IT-friendly packing and after-hours options.',
        'meta_title' => 'Commercial & Office Movers in Dallas | Business Moves',
        'meta_description' => 'Office and commercial moving in Dallas–Fort Worth. Evening and weekend moves, labeled crates and careful handling of workstations, files and IT equipment.',
        'hero' => [
            'eyebrow' => 'Commercial moving',
            'heading' => 'Office Moves That Keep Your Business Running',
            'subheading' => 'Evening and weekend moves, a clear room-by-room plan and a crew that is set up for Monday morning.',
            'bullets' => ['After-hours and weekend moves', 'Workstation and file handling', 'One project lead'],
        ],
        'sections' => [
            ['type' => 'rich_text', 'data' => [
                'heading' => 'A business move is a project. We run it like one.',
                'content' => '<p>Every hour your office is closed costs you. {company} plans commercial moves to keep downtime as short as possible, with a project lead who works with your office manager from the first walk-through to the last desk set up.</p><h3>Planned room by room</h3><p>We map every room in your current space to the new floor plan. Furniture, crates and equipment are labeled with their destination so the crew can place everything in one pass.</p><h3>Moves on your schedule</h3><p>Most offices move after hours or over a weekend, so staff leave on Friday and start work in the new space on Monday. Retail and medical moves can be scheduled around opening hours.</p><h3>Equipment handled with care</h3><p>Computers, monitors and printers are wrapped and boxed. Filing cabinets and records can be moved intact or packed in sealed crates.</p>',
                'width' => 'narrow',
                'background' => 'light',
            ]],
            ['type' => 'cards_grid', 'data' => [
                'heading' => 'Commercial moves we handle',
                'columns' => '4',
                'cards' => [
                    ['icon' => 'building-2', 'title' => 'Offices', 'text' => 'Small offices to full floors, including cubicles and conference rooms.'],
                    ['icon' => 'store', 'title' => 'Retail', 'text' => 'Fixtures, displays and stock moved around your opening hours.'],
                    ['icon' => 'monitor', 'title' => 'IT equipment', 'text' => 'Desktops, monitors and printers wrapped and labeled.'],
                    ['icon' => 'archive', 'title' => 'Files and records', 'text' => 'Cabinets moved intact or packed into sealed, labeled crates.'],
                ],
                'background' => 'muted',
            ]],
            ['type' => 'steps', 'data' => [
                'heading' => 'Our commercial moving process',
                'steps' => [
                    ['title' => 'Site visit', 'text' => 'We walk both spaces with you and note access, elevators and building rules.'],
                    ['title' => 'Move plan', 'text' => 'You receive a written plan with labels, timing and a fixed quote.'],
                    ['title' => 'Move day', 'text' => 'Crews move room by room, placing each item by the floor plan.'],
                    ['title' => 'Ready for work', 'text' => 'Desks, chairs and equipment are in place before your team arrives.'],
                ],
                'background' => 'light',
            ]],
        ],
        'faqs' => [
            ['Can you move our office after hours?', '<p>Yes. Evening and weekend moves are common for offices, so your team can leave on Friday and start work in the new space on Monday.</p>'],
            ['Do you provide a certificate of insurance for our building?', '<p>Many office buildings ask for one before move day. Tell us your building’s requirements and we will arrange the paperwork with your property manager.</p>'],
            ['How do you keep track of every desk and box?', '<p>Every item gets a label that matches a room or desk number on your new floor plan, so the crew can place it in one pass.</p>'],
            ['Do you move servers and network equipment?', '<p>We move computers, monitors and printers. Servers and network racks should be disconnected by your IT provider; we then move them with extra care.</p>'],
        ],
    ],
    [
        'slug' => 'packing-services',
        'title' => 'Packing Services',
        'icon' => 'box',
        'short' => 'Full or partial packing with quality boxes and materials, from fragile kitchens to artwork, so everything arrives the way it left.',
        'meta_title' => 'Professional Packing Services in Dallas | Movers',
        'meta_description' => 'Full or partial packing by trained movers in Dallas–Fort Worth. Quality boxes and materials, careful wrapping of fragile items and clear labels.',
        'hero' => [
            'eyebrow' => 'Packing services',
            'heading' => 'Professional Packing, Box by Box',
            'subheading' => 'Let our crew pack your home quickly and safely, with the right materials for every item.',
            'bullets' => ['Full or partial packing', 'Boxes and materials included', 'Clear labels by room'],
        ],
        'sections' => [
            ['type' => 'image_text', 'data' => [
                'heading' => 'Packing is the hardest part. Hand it to us.',
                'content' => '<p>Most people underestimate how long packing takes. A typical three-bedroom home holds well over a hundred boxes of belongings. Our packing crews work fast and carefully, using sturdy boxes, packing paper, bubble wrap and special cartons for dishes, glasses, TVs and clothing.</p><p>Every box is labeled with its room and contents, so unpacking is simple. Choose full packing for the whole home, or partial packing for the rooms that take the longest, usually the kitchen and living room.</p>',
                'bullets' => [
                    ['text' => 'Dish packs and glass dividers for the kitchen'],
                    ['text' => 'Wardrobe boxes for hanging clothes'],
                    ['text' => 'TV boxes and mirror cartons'],
                ],
                'image' => '@card',
                'image_position' => 'right',
                'background' => 'light',
            ]],
            ['type' => 'cards_grid', 'data' => [
                'heading' => 'Choose the packing that fits',
                'columns' => '3',
                'cards' => [
                    ['icon' => 'package-check', 'title' => 'Full packing', 'text' => 'We pack the whole home the day before your move.'],
                    ['icon' => 'boxes', 'title' => 'Partial packing', 'text' => 'We pack the kitchen, fragile items or any rooms you choose.'],
                    ['icon' => 'archive', 'title' => 'Unpacking', 'text' => 'We unpack, place items and take the empty boxes away.'],
                ],
                'background' => 'muted',
            ]],
            ['type' => 'rich_text', 'data' => [
                'heading' => 'Packing materials we use',
                'content' => '<p>Good packing starts with good materials. We bring new, double-wall boxes in several sizes, clean unprinted packing paper, bubble wrap, shrink wrap and strong tape. Fragile items are wrapped individually and boxes are filled so nothing can shift. Heavy items go in small boxes, light items in large ones, so every box is easy and safe to carry.</p>',
                'width' => 'narrow',
                'background' => 'light',
            ]],
        ],
        'faqs' => [
            ['Do I need to buy boxes?', '<p>No. When our crew packs, all boxes and materials are included. If you pack yourself, ask us about buying moving boxes and supplies.</p>'],
            ['How long does packing take?', '<p>Most homes are packed in one day, usually the day before moving day. Larger homes may need two days.</p>'],
            ['Can you pack just the kitchen?', '<p>Yes. Partial packing is popular for kitchens, china cabinets, artwork and other fragile or time-consuming rooms.</p>'],
            ['What items will you not pack?', '<p>For safety we do not pack flammable items, propane, paint, cleaning chemicals or perishable food. We will give you a full list before your move.</p>'],
        ],
    ],
    [
        'slug' => 'storage-services',
        'title' => 'Storage Services',
        'icon' => 'warehouse',
        'short' => 'Short- and long-term storage between homes, during renovations or for business inventory, with pickup and delivery handled by our crew.',
        'meta_title' => 'Moving & Storage in Dallas–Fort Worth | Secure Storage',
        'meta_description' => 'Short- and long-term storage between moves, during renovations or for business inventory. We pick up, store and deliver your items when you are ready.',
        'hero' => [
            'eyebrow' => 'Storage services',
            'heading' => 'Storage That Moves With You',
            'subheading' => 'When your new place is not ready yet, we store your belongings safely and deliver them when you are.',
            'bullets' => ['Short- and long-term storage', 'Pickup and delivery included', 'Inventory of every item'],
        ],
        'sections' => [
            ['type' => 'rich_text', 'data' => [
                'heading' => 'When moving day and move-in day are not the same',
                'content' => '<p>Closing dates slip, renovations run long and leases do not always line up. {company} storage bridges the gap. We pick up your belongings, keep them in a secure storage facility and deliver them to your new home when you are ready, with the same careful crew that moved them out.</p><h3>Storage for every situation</h3><ul><li>Between selling one home and buying the next</li><li>During a remodel or flooring project</li><li>While you relocate for work or school</li><li>For business inventory, records or seasonal stock</li></ul><h3>Organized and documented</h3><p>Everything that goes into storage is listed on an inventory, so you know exactly what we are keeping for you. Need one box back early? Just call.</p>',
                'width' => 'narrow',
                'background' => 'light',
            ]],
            ['type' => 'cards_grid', 'data' => [
                'heading' => 'Why store with your mover',
                'columns' => '3',
                'cards' => [
                    ['icon' => 'truck', 'title' => 'One move, not two', 'text' => 'No renting a truck to a storage unit. We pick up and deliver.'],
                    ['icon' => 'clipboard-list', 'title' => 'Item inventory', 'text' => 'Every piece is listed, so nothing is lost in storage.'],
                    ['icon' => 'calendar-days', 'title' => 'Flexible terms', 'text' => 'Store for a week or a year, and change dates as plans change.'],
                ],
                'background' => 'muted',
            ]],
            ['type' => 'steps', 'data' => [
                'heading' => 'How moving and storage works',
                'steps' => [
                    ['title' => 'Pickup', 'text' => 'We pack (if you like), wrap and load your belongings at your current home.'],
                    ['title' => 'Storage', 'text' => 'Your items are stored in a secure facility with a full inventory.'],
                    ['title' => 'Delivery', 'text' => 'When you are ready, we deliver and set up everything at your new address.'],
                ],
                'background' => 'light',
            ]],
        ],
        'faqs' => [
            ['How long can I store my belongings?', '<p>As long as you need. Storage is available for a few days, a few weeks or many months, and you can change your delivery date as your plans change.</p>'],
            ['Can I get something out of storage early?', '<p>Yes. Call us and we will arrange access or a partial delivery of the items you need.</p>'],
            ['Is my furniture protected in storage?', '<p>Furniture stays wrapped in moving pads, and every item is recorded on an inventory. Ask us about valuation coverage for stored goods.</p>'],
            ['Do you store business inventory?', '<p>Yes. We store office furniture, records, retail stock and seasonal displays for local businesses.</p>'],
        ],
    ],
];
