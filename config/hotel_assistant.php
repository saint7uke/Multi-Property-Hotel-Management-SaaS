<?php

return [
    'welcome' => 'Welcome to M.A Group of Hotels. I can help with room bookings, group stays, contact information, and general hotel questions.',

    'fallback' => 'I could not find an exact answer for that yet. You can send our team an inquiry or continue to the booking page for immediate options.',

    'faqs' => [
        [
            'id' => 'book-room',
            'question' => 'How can I book a room?',
            'answer' => 'Open the booking page, select your property, dates, guest count, and room preference, then review and submit your reservation details.',
            'keywords' => ['book', 'booking', 'reserve', 'reservation', 'room', 'stay', 'availability'],
            'actions' => [
                ['label' => 'Book a Room', 'route' => 'book.now', 'fragment' => 'booking'],
                ['label' => 'View Rooms', 'route' => 'home', 'fragment' => 'rooms'],
            ],
        ],
        [
            'id' => 'group-event',
            'question' => 'Do you accept group or event bookings?',
            'answer' => 'Yes. Choose Event or Group on the booking page and share your dates, guest count, venue, room, and service requirements. A room selection is not required for the initial inquiry.',
            'keywords' => ['group', 'event', 'events', 'wedding', 'conference', 'meeting', 'celebration', 'corporate', 'venue'],
            'actions' => [
                ['label' => 'Plan an Event', 'route' => 'book.now', 'fragment' => 'booking'],
                ['label' => 'Contact Us', 'route' => 'contact'],
            ],
        ],
        [
            'id' => 'contact-details',
            'question' => 'What are your contact details?',
            'answer' => 'Call 0183-12345678 or email reservations@magroupofhotels.com. You can also use the Contact Us form and select the property you want to reach.',
            'keywords' => ['contact', 'phone', 'telephone', 'call', 'email', 'address', 'location', 'reach', 'support'],
            'actions' => [
                ['label' => 'Contact Us', 'route' => 'contact'],
            ],
        ],
        [
            'id' => 'business-hours',
            'question' => 'What are your business hours?',
            'answer' => 'Online booking and inquiry forms are available 24 hours a day. Front desk and reservations assistance can vary by property, so contact your selected hotel for its current service hours.',
            'keywords' => ['hours', 'hour', 'open', 'opening', 'close', 'closing', 'time', 'schedule', '24 hours', 'business hours'],
            'actions' => [
                ['label' => 'Contact Us', 'route' => 'contact'],
            ],
        ],
    ],

    'quick_actions' => [
        ['label' => 'Book a Room', 'route' => 'book.now', 'fragment' => 'booking'],
        ['label' => 'Contact Us', 'route' => 'contact'],
        ['label' => 'View Rooms', 'route' => 'home', 'fragment' => 'rooms'],
        ['label' => 'View FAQs', 'action' => 'show-faqs'],
    ],

    'fallback_actions' => [
        ['label' => 'Contact Us', 'route' => 'contact'],
        ['label' => 'Book a Room', 'route' => 'book.now', 'fragment' => 'booking'],
    ],

    'staff_action' => ['label' => 'Talk to Our Staff', 'route' => 'contact'],
];
