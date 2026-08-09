@extends('layouts.public', [
    'title' => $property->meta_title ?: $property->name.' | M.A Group of Hotels',
    'description' => $property->meta_description ?: ($property->tagline ?: 'Book rooms, events, and group stays at '.$property->name.'.'),
])

@php
    $galleryImages = $property->galleryImageUrls();
    $roomFallbackImages = [
        asset('images/home/room-single.jpg'),
        asset('images/home/room-family.jpg'),
        asset('images/home/room-deluxe.jpg'),
    ];
    $displayGallery = $galleryImages ?: array_values(array_unique([
        $property->heroImageUrl(),
        ...$roomFallbackImages,
    ]));
    $reviewScore = $reviews->count() ? number_format($reviews->avg('rating'), 1) : null;
@endphp

@section('content')
<div class="premium-page hotel-premium responsive-page" data-hotel-page>
    <section class="hotel-hero" aria-labelledby="hotel-title">
        <img
            class="hotel-hero__image"
            src="{{ $property->heroImageUrl() }}"
            alt="{{ $property->name }} hotel exterior"
            width="2000"
            height="1200"
            fetchpriority="high"
            decoding="async"
            data-premium-hero-image
        >
        <div class="hotel-hero__scrim" aria-hidden="true"></div>
        <div class="home-shell hotel-hero__inner">
            <div class="hotel-hero__copy" data-premium-hero-copy>
                <p class="home-kicker">{{ $property->city }}, {{ $property->country }}</p>
                <h1 id="hotel-title">{{ $property->name }}</h1>
                <p>{{ $property->tagline ?: 'Book a room or send an event request directly to this property.' }}</p>
                <div class="hotel-hero__actions">
                    <a href="#booking" class="home-button home-button--accent">Book this hotel</a>
                    <a href="#rooms" class="home-button home-button--ghost">Explore rooms</a>
                </div>
            </div>
        </div>
        <dl class="home-shell hotel-hero__facts" data-premium-stagger>
            <div><dt>Location</dt><dd>{{ $property->address }}, {{ $property->city }}</dd></div>
            <div><dt>Rooms ready to book</dt><dd>{{ $rooms->count() }}</dd></div>
            <div><dt>Guest rating</dt><dd>{{ $reviewScore ? $reviewScore.' / 5' : 'New property' }}</dd></div>
            <div><dt>Check-in</dt><dd>{{ $property->check_in_time ? date('g:i A', strtotime($property->check_in_time)) : 'Confirm with hotel' }}</dd></div>
            <div><dt>Check-out</dt><dd>{{ $property->check_out_time ? date('g:i A', strtotime($property->check_out_time)) : 'Confirm with hotel' }}</dd></div>
        </dl>
    </section>

    <section class="hotel-overview home-section" aria-labelledby="overview-title">
        <div class="home-shell hotel-overview__grid">
            <div class="hotel-overview__copy" data-premium-reveal>
                <x-public.section-heading
                    id="overview-title"
                    eyebrow="Property overview"
                    :title="'Experience '.$property->city.'.'"
                    :copy="$property->description ?: 'A welcoming M.A property with direct booking support for room stays, events, and groups.'"
                />
                @if ($property->amenities)
                    <div class="hotel-amenities">
                        <h3>Included amenities</h3>
                        <ul aria-label="Property amenities" data-premium-stagger>
                            @foreach ($property->amenities as $amenity)
                                <li>{{ $amenity }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($property->contact_email || $property->contact_phone)
                    <address class="hotel-property-contact">
                        <p>Property desk</p>
                        @if ($property->contact_email)
                            <a href="mailto:{{ $property->contact_email }}">{{ $property->contact_email }}</a>
                        @endif
                        @if ($property->contact_phone)
                            <a href="tel:{{ preg_replace('/[^+\d]/', '', $property->contact_phone) }}">{{ $property->contact_phone }}</a>
                        @endif
                    </address>
                @endif
            </div>
            <div class="hotel-gallery" data-hotel-gallery data-premium-stagger aria-label="{{ $property->name }} photo gallery">
                @foreach (array_slice($displayGallery, 0, 4) as $image)
                    <button
                        class="hotel-gallery__item {{ $loop->first ? 'hotel-gallery__item--featured' : '' }}"
                        type="button"
                        data-hotel-gallery-item
                        data-image="{{ $image }}"
                        data-alt="{{ $property->name }} property photo {{ $loop->iteration }}"
                        aria-label="Open property photo {{ $loop->iteration }}"
                    >
                        <img src="{{ $image }}" alt="{{ $property->name }} property photo {{ $loop->iteration }}" width="1200" height="800" loading="lazy" decoding="async" data-premium-parallax>
                    </button>
                @endforeach
            </div>
        </div>
        @if ($property->highlights)
            <div class="home-shell hotel-highlights" data-premium-stagger>
                @foreach ($property->highlights as $highlight)
                    <article>
                        <h3>{{ $highlight['title'] }}</h3>
                        <p>{{ $highlight['description'] }}</p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section id="rooms" class="hotel-rooms" aria-labelledby="rooms-title">
        <div class="home-shell">
            <div class="home-heading-row" data-premium-reveal>
                <x-public.section-heading
                    id="rooms-title"
                    light
                    :title="'Rooms at '.$property->city"
                    copy="Compare capacity, included amenities, nightly pricing, and current booking status before choosing your dates."
                />
                <a class="home-text-link home-text-link--light" href="#booking">Check your dates <span>&rarr;</span></a>
            </div>
            <div class="hotel-room-grid" data-premium-stagger>
                @forelse ($rooms as $room)
                    @php
                        $roomImage = $displayGallery
                            ? $displayGallery[$loop->index % count($displayGallery)]
                            : $roomFallbackImages[$loop->index % count($roomFallbackImages)];
                    @endphp
                    <article class="hotel-room-card">
                        <div class="hotel-room-card__media">
                            <img src="{{ $roomImage }}" alt="{{ $room->type }} room at {{ $property->name }}" width="900" height="650" loading="lazy" decoding="async">
                            <span class="hotel-room-card__status">Available</span>
                        </div>
                        <div class="hotel-room-card__body">
                            <div class="hotel-room-card__heading">
                                <div><p>Room {{ $room->room_number }}</p><h3>{{ $room->type }}</h3></div>
                                <p class="hotel-room-card__price"><strong>PHP {{ number_format((float) $room->rate, 0) }}</strong><span>per night</span></p>
                            </div>
                            <p class="hotel-room-card__capacity">Comfortably accommodates up to {{ $room->capacity }} guest{{ $room->capacity === 1 ? '' : 's' }}.</p>
                            @if ($room->amenities)
                                <ul class="hotel-room-card__amenities" aria-label="{{ $room->type }} amenities">
                                    @foreach (array_slice($room->amenities, 0, 3) as $amenity)
                                        <li>{{ $amenity }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            <a class="home-button home-button--outline" href="#booking">Choose this room</a>
                        </div>
                    </article>
                @empty
                    <div class="hotel-room-empty">
                        <h3>No rooms are open for public booking.</h3>
                        <p>Use the event or group option and the hotel team can review your request manually.</p>
                        <a class="home-button home-button--ghost" href="#booking">Plan an event or group stay</a>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <dialog class="hotel-gallery-dialog" data-hotel-gallery-dialog aria-label="Property photo viewer">
        <button type="button" data-gallery-close aria-label="Close photo viewer">Close</button>
        <button type="button" data-gallery-previous aria-label="Previous photo">Previous</button>
        <figure><img src="" alt="" data-gallery-image></figure>
        <button type="button" data-gallery-next aria-label="Next photo">Next</button>
    </dialog>

    <section id="booking" class="hotel-booking home-section">
        <div class="home-shell hotel-booking__grid">
            <div class="hotel-booking__intro" data-premium-reveal>
                <x-public.section-heading
                    eyebrow="Direct booking"
                    :title="'Book '.$property->name"
                    copy="Personal room requests use live room availability. Event and group requests go directly to the property team for review."
                />
                <div class="hotel-booking__estimate">
                    <p class="text-sm font-semibold text-ma-blue">Estimated total</p>
                    <p id="booking-estimate" class="mt-2 text-3xl font-bold tabular-nums text-ma-ink">PHP 0.00</p>
                    <p id="booking-feedback" class="mt-3 text-sm font-medium text-black/65" aria-live="polite"></p>
                </div>
            </div>

            <form id="booking-form" class="hotel-form grid gap-5 sm:grid-cols-2" novalidate data-premium-reveal>
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div class="sm:col-span-2">
                    <span class="ma-label">Booking type</span>
                    <div class="mt-2 grid grid-cols-2 gap-2 rounded-md bg-ma-cream p-1" role="radiogroup" aria-label="Booking type">
                        <label class="cursor-pointer rounded-md bg-white px-3 py-2 text-center text-sm font-semibold">
                            <input class="sr-only" type="radio" name="booking_type" value="personal" checked>
                            Personal room
                        </label>
                        <label class="cursor-pointer rounded-md px-3 py-2 text-center text-sm font-semibold">
                            <input class="sr-only" type="radio" name="booking_type" value="event">
                            Event or group
                        </label>
                    </div>
                </div>

                <label>
                    <span class="ma-label">Property</span>
                    <select class="ma-input mt-1" name="property_id" required>
                        <option value="{{ $property->id }}" data-breakfast="{{ $property->offers_breakfast ? '1' : '0' }}" selected>{{ $property->name }}</option>
                    </select>
                    <span class="ma-error" data-error-for="property_id"></span>
                </label>

                <label data-booking-room-field>
                    <span class="ma-label">Available room</span>
                    <select class="ma-input mt-1" name="room_id">
                        <option value="">Choose dates first</option>
                    </select>
                    <span class="ma-error" data-error-for="room_id"></span>
                </label>

                <label>
                    <span class="ma-label">Preferred Area / Location</span>
                    <input class="ma-input mt-1" name="preferred_area" value="{{ $property->city }}">
                    <span class="ma-error" data-error-for="preferred_area"></span>
                </label>

                <label>
                    <span class="ma-label">Number of Rooms</span>
                    <input class="ma-input mt-1" type="number" min="1" max="10" name="room_count" value="1" required>
                    <span class="ma-error" data-error-for="room_count"></span>
                </label>

                <label class="hidden rounded-md border border-black/10 bg-ma-cream/60 p-4 sm:col-span-2" data-booking-breakfast-field>
                    <span class="flex items-start gap-3">
                        <input class="mt-1 h-4 w-4 accent-ma-blue" type="checkbox" name="wants_breakfast" value="1">
                        <span>
                            <span class="ma-label block">With Breakfast</span>
                            <span class="mt-1 block text-xs font-medium text-black/55">Shown only when this property offers breakfast.</span>
                        </span>
                    </span>
                </label>

                <label>
                    <span class="ma-label">Check in</span>
                    <input class="ma-input mt-1" type="date" name="check_in" required>
                    <span class="ma-error" data-error-for="check_in"></span>
                </label>

                <label>
                    <span class="ma-label">Check out</span>
                    <input class="ma-input mt-1" type="date" name="check_out" required>
                    <span class="ma-error" data-error-for="check_out"></span>
                </label>

                <label>
                    <span class="ma-label">Guest name</span>
                    <input class="ma-input mt-1" name="guest_name" required autocomplete="name">
                    <span class="ma-error" data-error-for="guest_name"></span>
                </label>

                <label>
                    <span class="ma-label">Email</span>
                    <input class="ma-input mt-1" type="email" name="email" required autocomplete="email">
                    <span class="ma-error" data-error-for="email"></span>
                </label>

                <label>
                    <span class="ma-label">Phone</span>
                    <input class="ma-input mt-1" name="phone" required autocomplete="tel">
                    <span class="ma-error" data-error-for="phone"></span>
                </label>

                <label class="sm:col-span-2">
                    <span class="ma-label">Home Address</span>
                    <input class="ma-input mt-1" name="home_address" required autocomplete="street-address">
                    <span class="ma-error" data-error-for="home_address"></span>
                </label>

                <label class="hidden" data-booking-event-field>
                    <span class="ma-label">Event name</span>
                    <input class="ma-input mt-1" name="event_name">
                    <span class="ma-error" data-error-for="event_name"></span>
                </label>

                <label>
                    <span class="ma-label">Adults</span>
                    <input class="ma-input mt-1" type="number" min="1" max="20" name="adults" value="2" required>
                    <span class="ma-error" data-error-for="adults"></span>
                </label>

                <label>
                    <span class="ma-label">Children</span>
                    <input class="ma-input mt-1" type="number" min="0" max="20" name="children" value="0">
                    <span class="ma-error" data-error-for="children"></span>
                </label>

                <label class="sm:col-span-2">
                    <span class="ma-label">Special request</span>
                    <textarea class="ma-input mt-1 min-h-28" name="special_request"></textarea>
                    <span class="ma-error" data-error-for="special_request"></span>
                </label>

                <fieldset class="grid gap-3 rounded-md border border-black/10 bg-ma-cream/45 p-4 sm:col-span-2">
                    <legend class="ma-label px-1">Add-ons (Optional)</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'extra_bed' => 'Extra Bed',
                            'extra_pax' => 'Extra Pax',
                            'additional_breakfast' => 'Additional Breakfast',
                            'early_check_in' => 'Early Check-in',
                            'late_check_out' => 'Late Check-out',
                        ] as $value => $label)
                            <label class="flex items-center gap-3 rounded-md bg-white px-3 py-2 text-sm font-semibold text-ma-ink">
                                <input class="h-4 w-4 accent-ma-blue" type="checkbox" name="addons[]" value="{{ $value }}">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <span class="ma-error" data-error-for="addons"></span>
                </fieldset>

                <label class="sm:col-span-2">
                    <span class="ma-label">Payment Method</span>
                    <select class="ma-input mt-1" name="payment_method" required>
                        <option value="">Select payment method</option>
                        <option value="credit_card">Credit/Debit Card</option>
                        <option value="gcash">GCash</option>
                        <option value="maya">Maya</option>
                        <option value="online_banking">Online Banking</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="digital_wallet">Digital Wallets</option>
                    </select>
                    <span class="ma-error" data-error-for="payment_method"></span>
                    <span class="mt-1 block text-xs font-medium text-black/55">Payment is recorded as pending until verified by staff or a connected payment gateway.</span>
                </label>

                <label class="flex items-start gap-3 rounded-md border border-black/10 p-4 sm:col-span-2">
                    <input class="mt-1 h-4 w-4 accent-ma-blue" type="checkbox" name="terms_accepted" value="1" required>
                    <span>
                        <span class="ma-label block">I agree to the cancellation policy, terms and conditions, and privacy policy.</span>
                        <span class="ma-error" data-error-for="terms_accepted"></span>
                    </span>
                </label>

                <button class="home-button home-button--dark sm:col-span-2" type="submit" data-booking-submit>Confirm Booking</button>
            </form>
        </div>
    </section>

    <section id="status" class="hotel-status home-section">
        <div class="home-shell hotel-status__grid">
            <div data-premium-reveal>
                <x-public.section-heading
                    eyebrow="Reservation lookup"
                    title="Check your booking status"
                    copy="Use the reference number and email from any M.A booking request to securely view its latest status."
                />
            </div>
            <form id="status-form" class="hotel-form hotel-form--compact grid gap-4" novalidate data-premium-reveal>
                <label>
                    <span class="ma-label">Reference number</span>
                    <input class="ma-input mt-1 uppercase" name="reference_number" placeholder="MAH-YYYYMMDD-ABC123" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                    <span class="ma-error" data-error-for="reference_number"></span>
                    <span class="mt-1 block text-xs font-medium text-black/55">Email is required to protect booking details.</span>
                </label>
                <label>
                    <span class="ma-label">Email</span>
                    <input class="ma-input mt-1" type="email" name="email" autocomplete="email" required>
                    <span class="ma-error" data-error-for="email"></span>
                </label>
                <button class="home-button home-button--outline" type="submit" data-status-submit>Look up booking</button>
                <div id="status-result" class="rounded-md bg-ma-cream/70 p-4 text-sm leading-6 text-ma-ink" aria-live="polite"></div>
            </form>
        </div>
    </section>

    <section class="hotel-reviews home-section" aria-labelledby="hotel-reviews-title">
        <div class="home-shell">
            <div class="home-heading-row" data-premium-reveal>
                <x-public.section-heading
                    id="hotel-reviews-title"
                    light
                    :title="'Guest notes from '.$property->city"
                    copy="Published reviews are tied to this property and shown only after the hotel team has checked them."
                />
                @if ($reviewScore)
                    <p class="hotel-reviews__score"><strong>{{ $reviewScore }}</strong><span>average rating</span></p>
                @endif
            </div>
            <div class="hotel-reviews__grid">
                <div class="hotel-review-list" data-premium-stagger>
                    @forelse ($reviews as $review)
                        <article class="hotel-review-card">
                            <div class="hotel-review-card__top">
                                <p>{{ $review->rating }}/5</p>
                                @if ($review->reservation_id)
                                    <span>Verified stay</span>
                                @endif
                            </div>
                            <blockquote>&ldquo;{{ $review->message }}&rdquo;</blockquote>
                            <p class="hotel-review-card__guest">{{ $review->guest->name }}</p>
                        </article>
                    @empty
                        <div class="hotel-review-empty">
                            <h3>Be the first approved guest review.</h3>
                            <p>Reviews appear after the hotel team has checked and published them.</p>
                        </div>
                    @endforelse
                </div>

            <form id="review-form" class="hotel-form hotel-review-form grid gap-4 text-ma-ink md:grid-cols-2" novalidate data-premium-reveal>
                <div class="md:col-span-2">
                    <p class="home-kicker">Share your stay</p>
                    <h3>Leave a review</h3>
                    <p>Verified stays can include the reservation reference number.</p>
                </div>
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <label>
                    <span class="ma-label">Property</span>
                    <select class="ma-input mt-1" name="property_id">
                        <option value="{{ $property->id }}" selected>{{ $property->name }}</option>
                    </select>
                    <span class="ma-error" data-error-for="property_id"></span>
                    <span class="mt-1 block text-xs font-medium text-black/55">Use a reference number for verified stay reviews.</span>
                </label>
                <label>
                    <span class="ma-label">Reference number</span>
                    <input class="ma-input mt-1 uppercase" name="reference_number" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Optional for verified stay">
                    <span class="ma-error" data-error-for="reference_number"></span>
                </label>
                <label>
                    <span class="ma-label">Name</span>
                    <input class="ma-input mt-1" name="guest_name" required>
                    <span class="ma-error" data-error-for="guest_name"></span>
                </label>
                <label>
                    <span class="ma-label">Email</span>
                    <input class="ma-input mt-1" type="email" name="email" required>
                    <span class="ma-error" data-error-for="email"></span>
                </label>
                <label>
                    <span class="ma-label">Rating</span>
                    <select class="ma-input mt-1" name="rating" required>
                        <option value="5">5</option>
                        <option value="4">4</option>
                        <option value="3">3</option>
                        <option value="2">2</option>
                        <option value="1">1</option>
                    </select>
                    <span class="ma-error" data-error-for="rating"></span>
                </label>
                <label class="md:col-span-2">
                    <span class="ma-label">Review</span>
                    <textarea class="ma-input mt-1 min-h-24" name="message" required></textarea>
                    <span class="ma-error" data-error-for="message"></span>
                </label>
                <button class="home-button home-button--dark md:col-span-2" type="submit" data-review-submit>Submit review for moderation</button>
                <p id="review-feedback" class="md:col-span-2 text-sm font-medium" aria-live="polite"></p>
            </form>
            </div>
        </div>
    </section>
</div>
@endsection
