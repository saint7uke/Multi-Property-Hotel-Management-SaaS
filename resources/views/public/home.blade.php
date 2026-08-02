@extends('layouts.public', ['title' => 'M.A Group of Hotels | Premium Hospitality'])

@php
    $propertyImages = [
        asset('images/home/hotel-hero.jpg'),
        asset('images/home/property-suite.jpg'),
        asset('images/home/property-lobby.jpg'),
        asset('images/home/property-resort.jpg'),
    ];

    $rooms = [
        [
            'name' => 'Single Room',
            'copy' => 'A calm, efficient retreat for solo guests and focused business travel.',
            'image' => asset('images/home/room-single.jpg'),
        ],
        [
            'name' => 'Family Room',
            'copy' => 'Comfortable space to settle in, reconnect, and enjoy an easy family stay.',
            'image' => asset('images/home/room-family.jpg'),
        ],
        [
            'name' => 'Deluxe Room',
            'copy' => 'A polished room experience with more space and elevated details.',
            'image' => asset('images/home/room-deluxe.jpg'),
        ],
    ];

    $journeys = [
        [
            'number' => '01',
            'title' => 'Personal room stays',
            'copy' => 'Choose a property, check real room availability, review your stay, and submit guest details in one guided request.',
            'link' => route('book.now').'#booking',
            'linkLabel' => 'Find a room',
            'image' => asset('images/home/journey-personal.jpg'),
        ],
        [
            'number' => '02',
            'title' => 'Events and group bookings',
            'copy' => 'Tell the Reservations and Sales Team about your venue, guest count, schedule, and service needs. A room selection is not required for the initial inquiry.',
            'link' => route('book.now').'#booking',
            'linkLabel' => 'Plan a group stay',
            'image' => asset('images/home/journey-event.jpg'),
        ],
        [
            'number' => '03',
            'title' => 'Booking status, kept simple',
            'copy' => 'Use your reference number and booking email to view the latest reservation status securely from the booking page.',
            'link' => route('book.now').'#status',
            'linkLabel' => 'Check booking status',
            'image' => asset('images/home/journey-status.jpg'),
        ],
    ];
@endphp

@section('content')
<div class="home-premium overflow-clip bg-[#f7f8fa] text-ma-ink" data-home-page>
    <section class="home-hero" aria-labelledby="home-title">
        <img
            class="home-hero__image"
            src="{{ asset('images/home/hotel-hero.jpg') }}"
            alt="Resort pool and hotel surrounded by tropical greenery"
            width="2000"
            height="1333"
            fetchpriority="high"
            decoding="async"
            data-home-hero-image
        >
        <div class="home-hero__scrim" aria-hidden="true"></div>
        <div class="home-shell home-hero__inner">
            <div class="home-hero__copy" data-home-hero-copy>
                <p class="home-kicker text-[#e7bd69]">Premium Hospitality</p>
                <h1 id="home-title" class="font-display">M.A Group of Hotels</h1>
                <p class="font-sans">Restful rooms, thoughtful service, and considered stays for business, family, and special occasions.</p>
                <div class="home-hero__actions">
                    <a class="home-button home-button--accent" href="{{ route('book.now') }}">Book now</a>
                    <a class="home-button home-button--ghost" href="#properties">Explore properties</a>
                </div>
            </div>
        </div>
    </section>

    <section class="home-search-wrap" aria-labelledby="availability-title" data-home-search-wrap>
        <div class="home-shell">
            <form class="home-search" action="{{ route('book.now') }}" method="get" data-home-search>
                <div class="home-search__intro">
                    <p class="home-kicker">Plan your stay</p>
                    <h2 id="availability-title">Search availability</h2>
                </div>
                <label class="home-search__field">
                    <span>Property</span>
                    <select name="property">
                        <option value="">Choose hotel</option>
                        @foreach ($properties as $property)
                            <option value="{{ $property->slug }}">{{ $property->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="home-search__field">
                    <span>Check-in</span>
                    <input type="date" name="check_in" min="{{ today()->format('Y-m-d') }}" required>
                </label>
                <label class="home-search__field">
                    <span>Check-out</span>
                    <input type="date" name="check_out" min="{{ today()->addDay()->format('Y-m-d') }}" required>
                </label>
                <label class="home-search__field">
                    <span>Booking type</span>
                    <select name="type">
                        <option value="personal">Personal room</option>
                        <option value="event">Event or group</option>
                    </select>
                </label>
                <button class="home-button home-button--dark home-search__submit" type="submit">Check availability</button>
            </form>
        </div>
    </section>

    <section class="home-service-strip" aria-label="Booking services" data-home-stagger>
        <a href="{{ route('book.now') }}#booking"><span>01</span><strong>Personal stays</strong><small>Live room selection</small></a>
        <a href="{{ route('book.now') }}#booking"><span>02</span><strong>Events and groups</strong><small>Team-assisted planning</small></a>
        <a href="{{ route('book.now') }}#status"><span>03</span><strong>Booking status</strong><small>Reference lookup</small></a>
    </section>

    <section id="properties" class="home-section home-properties" aria-labelledby="properties-title">
        <div class="home-shell">
            <div class="home-heading-row" data-home-reveal>
                <x-public.section-heading
                    id="properties-title"
                    eyebrow="Popular stays"
                    title="Explore M.A Properties"
                    copy="Each property has its own character, with the same direct path to rooms, services, and local support."
                />
                <a class="home-text-link" href="{{ route('book.now') }}">View booking options <span>→</span></a>
            </div>

            <div class="home-property-grid" data-home-stagger>
                @forelse ($properties as $property)
                    <x-public.property-card
                        :property="$property"
                        :image="$property->hero_image_path ? $property->heroImageUrl() : $propertyImages[$loop->index % count($propertyImages)]"
                        :featured="$loop->first"
                    />
                @empty
                    <div class="home-empty-state">
                        <h3>Property details are being prepared.</h3>
                        <p>Contact the M.A reservations team for current locations and room options.</p>
                        <a class="home-text-link" href="{{ route('contact') }}">Contact Us <span>→</span></a>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="home-story" aria-labelledby="journey-title" data-home-story>
        <div class="home-story__media" data-home-story-media aria-hidden="true">
            @foreach ($journeys as $journey)
                <img
                    class="home-story__image {{ $loop->first ? 'is-active' : '' }}"
                    src="{{ $journey['image'] }}"
                    alt=""
                    width="1400"
                    height="1000"
                    loading="lazy"
                    decoding="async"
                    data-home-story-image
                >
            @endforeach
            <div class="home-story__media-label">
                <span>M.A Guest Journey</span>
                <strong>Designed around the way you travel.</strong>
            </div>
        </div>
        <div class="home-story__content">
            <div class="home-story__title" data-home-reveal>
                <p class="home-kicker">From search to confirmation</p>
                <h2 id="journey-title">One clear booking journey, shaped for every stay.</h2>
            </div>
            @foreach ($journeys as $journey)
                <article class="home-story__step" data-home-story-step data-story-index="{{ $loop->index }}">
                    <img class="home-story__mobile-image" src="{{ $journey['image'] }}" alt="{{ $journey['title'] }}" width="1000" height="720" loading="lazy" decoding="async">
                    <span>{{ $journey['number'] }}</span>
                    <h3>{{ $journey['title'] }}</h3>
                    <p>{{ $journey['copy'] }}</p>
                    <a class="home-text-link" href="{{ $journey['link'] }}">{{ $journey['linkLabel'] }} <span>→</span></a>
                </article>
            @endforeach
        </div>
    </section>

    <section class="home-section home-amenities" aria-labelledby="amenities-title">
        <div class="home-shell">
            <div class="home-heading-row" data-home-reveal>
                <x-public.section-heading
                    id="amenities-title"
                    title="Relaxing Amenities"
                    copy="Calm social spaces, comfortable rooms, and responsive service make the practical parts of travel feel effortless."
                />
                <a class="home-text-link" href="{{ route('contact') }}">Ask about amenities <span>→</span></a>
            </div>
            <div class="home-amenity-grid" data-home-stagger>
                <figure class="home-amenity home-amenity--large">
                    <div class="home-parallax-frame"><img src="{{ asset('images/home/amenity-lounge.jpg') }}" alt="Comfortable hotel lounge" width="1500" height="1000" loading="lazy" decoding="async" data-home-parallax></div>
                    <figcaption><strong>Spaces to slow down</strong><span>Comfortable lounges and calm guest areas.</span></figcaption>
                </figure>
                <figure class="home-amenity">
                    <div class="home-parallax-frame"><img src="{{ asset('images/home/amenity-wellness.jpg') }}" alt="Hotel wellness and pool amenity" width="1100" height="900" loading="lazy" decoding="async" data-home-parallax></div>
                    <figcaption><strong>Restorative moments</strong><span>Property amenities vary by location.</span></figcaption>
                </figure>
                <figure class="home-amenity">
                    <div class="home-parallax-frame"><img src="{{ asset('images/home/amenity-dining.jpg') }}" alt="Hotel breakfast and dining service" width="1100" height="900" loading="lazy" decoding="async" data-home-parallax></div>
                    <figcaption><strong>Thoughtful dining</strong><span>Breakfast is shown when offered by a property.</span></figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section id="rooms" class="home-rooms" aria-labelledby="rooms-title">
        <div class="home-shell">
            <div class="home-heading-row" data-home-reveal>
                <x-public.section-heading
                    id="rooms-title"
                    light
                    title="Featured Rooms"
                    copy="Room types and live availability are confirmed for the property and dates you select."
                />
                <a class="home-text-link home-text-link--light" href="{{ route('book.now') }}">Request a room <span>→</span></a>
            </div>
            <div class="home-room-gallery" data-home-room-gallery>
                @foreach ($rooms as $room)
                    <article class="home-room {{ $loop->first ? 'is-active' : '' }}" tabindex="0">
                        <img src="{{ $room['image'] }}" alt="{{ $room['name'] }} at M.A Group of Hotels" width="1200" height="900" loading="lazy" decoding="async">
                        <div class="home-room__scrim"></div>
                        <div class="home-room__body">
                            <span>0{{ $loop->iteration }}</span>
                            <h3>{{ $room['name'] }}</h3>
                            <p>{{ $room['copy'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="home-section home-why" aria-labelledby="why-title">
        <div class="home-shell home-why__grid">
            <div class="home-why__image" data-home-reveal>
                <img src="{{ asset('images/home/hotel-team.jpg') }}" alt="Hotel team welcoming a guest at reception" width="1400" height="1000" loading="lazy" decoding="async">
            </div>
            <div class="home-why__content" data-home-reveal>
                <x-public.section-heading
                    id="why-title"
                    eyebrow="Why stay with us"
                    title="Hospitality that stays personal."
                    copy="M.A Group of Hotels brings together welcoming service, restful rooms, and convenient booking support for different travel needs."
                />
                <dl class="home-why__list" data-home-stagger>
                    <div><dt>Flexible stays</dt><dd>Personal rooms, family visits, events, and group requests.</dd></div>
                    <div><dt>Clear availability</dt><dd>Room choices are tied to the selected property, dates, and guest count.</dd></div>
                    <div><dt>Direct support</dt><dd>Reservations staff can review special requests and property details.</dd></div>
                </dl>
                <a class="home-button home-button--outline" href="{{ route('about') }}">About M.A</a>
            </div>
        </div>
    </section>

    <section class="home-reviews" aria-labelledby="reviews-title">
        <div class="home-shell">
            <div class="home-heading-row" data-home-reveal>
                <x-public.section-heading
                    id="reviews-title"
                    light
                    title="Guest Reviews"
                    copy="Published reviews are shown only after the hotel team has checked and approved them."
                />
                <a class="home-text-link home-text-link--light" href="{{ route('contact') }}#review">Rate your stay <span>→</span></a>
            </div>
            <div class="home-review-grid" data-home-stagger>
                @forelse ($reviews as $review)
                    <x-public.review-card :review="$review" />
                @empty
                    <div class="home-empty-state home-empty-state--dark">
                        <h3>No reviews yet</h3>
                        <p>Approved guest reviews will appear here once the staff team publishes them.</p>
                        <a class="home-text-link home-text-link--light" href="{{ route('contact') }}#review">Rate or review a stay <span>→</span></a>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

</div>
@endsection
