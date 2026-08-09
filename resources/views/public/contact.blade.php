@extends('layouts.public', [
    'title' => 'Contact Us | M.A Group of Hotels',
    'description' => 'Contact M.A Group of Hotels for room reservations, events, group stays, guest services, partnerships, and reviews.',
])

@section('content')
<div class="premium-page responsive-page">
    <x-public.page-hero
        eyebrow="Guest support"
        title="Contact Us"
        copy="For rooms, events, group stays, guest services, and partnerships, send the team your details."
        :image="asset('images/home/property-lobby.jpg')"
        alt="M.A Group of Hotels reservations and guest service lobby"
        compact
    >
        <x-slot:actions>
            <a class="premium-button premium-button--gold" href="#inquiry">Send an inquiry</a>
            <a class="premium-button premium-button--ghost" href="{{ route('book.now') }}">Book now</a>
        </x-slot:actions>
    </x-public.page-hero>

    <dl class="premium-contact-strip premium-shell" data-premium-stagger>
        <div>
            <dt>Address</dt>
            <dd>M.A Group of Hotels, Philippines</dd>
        </div>
        <div>
            <dt>Telephone</dt>
            <dd>0183-12345678</dd>
        </div>
        <div>
            <dt>Email</dt>
            <dd>reservations@magroupofhotels.com</dd>
        </div>
    </dl>

    <section id="inquiry" class="premium-section bg-white">
        <div class="premium-shell grid gap-12 lg:grid-cols-[.82fr_1.18fr]">
            <div data-premium-reveal>
                <x-public.page-section-heading
                    eyebrow="Reservations and inquiries"
                    title="Tell us how we can help."
                    copy="Use this form for general questions and coordination. Room pricing and reservation tracking remain in the dedicated booking experience."
                />
                <figure class="premium-media mt-8 aspect-[4/3]">
                    <img src="{{ asset('images/home/property-resort.jpg') }}" alt="Travel planning for an M.A hotel destination" width="1200" height="900" loading="lazy" decoding="async" data-premium-parallax>
                </figure>
                <div class="mt-6 border-l-2 border-[#d4a24c] pl-4">
                    <h3 class="text-lg font-medium text-ma-blue">Location Map</h3>
                    <p class="mt-2 text-sm leading-6 text-black/58">Official map location will be updated once the final address and map embed are provided.</p>
                </div>
            </div>

            <form id="inquiry-form" class="premium-form grid gap-5 p-6 md:grid-cols-2 lg:p-8" novalidate data-inquiry-form data-endpoint="/api/public/contact-inquiries" data-premium-reveal>
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div class="premium-form-section__heading">
                    <h3>General inquiry</h3>
                    <p>Fields marked by the browser as required must be completed before sending.</p>
                </div>
                <label>
                    <span class="ma-label">Full Name</span>
                    <input class="ma-input mt-2" name="full_name" required autocomplete="name">
                    <span class="ma-error" data-error-for="full_name"></span>
                </label>
                <label>
                    <span class="ma-label">Email</span>
                    <input class="ma-input mt-2" type="email" name="email" required autocomplete="email">
                    <span class="ma-error" data-error-for="email"></span>
                </label>
                <label>
                    <span class="ma-label">Contact Number <span class="font-normal text-black/45">(optional)</span></span>
                    <input class="ma-input mt-2" type="tel" name="phone" autocomplete="tel">
                    <span class="ma-error" data-error-for="phone"></span>
                </label>
                <label>
                    <span class="ma-label">Preferred Property <span class="font-normal text-black/45">(optional)</span></span>
                    <select class="ma-input mt-2" name="property_id">
                        <option value="">M.A Group / Not sure yet</option>
                        @foreach ($properties as $property)
                            <option value="{{ $property->id }}">{{ $property->name }}</option>
                        @endforeach
                    </select>
                    <span class="ma-error" data-error-for="property_id"></span>
                </label>
                <label class="md:col-span-2">
                    <span class="ma-label">Inquiry Type</span>
                    <select class="ma-input mt-2" name="inquiry_type" required>
                        <option value="">Select inquiry</option>
                        <option value="room_reservation">Room Reservation</option>
                        <option value="events">Events</option>
                        <option value="group_booking">Group Booking</option>
                        <option value="guest_services">Guest Services</option>
                        <option value="partnership">Partnership</option>
                        <option value="other">Other Inquiry</option>
                    </select>
                    <span class="ma-error" data-error-for="inquiry_type"></span>
                </label>
                <label class="md:col-span-2">
                    <span class="ma-label">Message</span>
                    <textarea class="ma-input mt-2 min-h-36" name="message" required></textarea>
                    <span class="ma-error" data-error-for="message"></span>
                </label>
                <button class="premium-button premium-button--navy md:col-span-2" type="submit" data-form-submit>Send Inquiry</button>
                <p class="md:col-span-2 text-sm leading-6 text-black/58">This form is for inquiry only. For room pricing and reservation tracking, use Book Now.</p>
                <p class="public-form-feedback md:col-span-2 min-h-5 text-sm font-semibold" data-inquiry-feedback aria-live="polite" role="status"></p>
            </form>
        </div>
    </section>

    <section id="review" class="premium-section premium-dark-band">
        <div class="premium-shell grid gap-12 lg:grid-cols-[.78fr_1.22fr]">
            <div data-premium-reveal>
                <x-public.page-section-heading
                    light
                    eyebrow="Guest experience"
                    title="Rate / Review"
                    copy="Guest Review Checking sends new reviews to staff moderation before they appear on public pages."
                />
                <div class="mt-8 border-t border-white/15 pt-6 text-sm leading-7 text-white/62">
                    <p>Use a booking reference for a verified stay review. Reviews without a reference remain tied to the selected property and still enter moderation.</p>
                </div>
            </div>

            <form id="review-form" class="premium-form grid gap-5 p-6 text-ma-ink md:grid-cols-2 lg:p-8" novalidate data-premium-reveal>
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div class="premium-form-section__heading">
                    <h3>Share your stay</h3>
                    <p>Reviews are moderated by staff before public display.</p>
                </div>
                <label>
                    <span class="ma-label">Full Name</span>
                    <input class="ma-input mt-2" name="guest_name" required autocomplete="name">
                    <span class="ma-error" data-error-for="guest_name"></span>
                </label>
                <label>
                    <span class="ma-label">Email</span>
                    <input class="ma-input mt-2" type="email" name="email" required autocomplete="email">
                    <span class="ma-error" data-error-for="email"></span>
                </label>
                <label>
                    <span class="ma-label">Rating</span>
                    <select class="ma-input mt-2" name="rating" required>
                        <option value="5">5 stars</option>
                        <option value="4">4 stars</option>
                        <option value="3">3 stars</option>
                        <option value="2">2 stars</option>
                        <option value="1">1 star</option>
                    </select>
                    <span class="ma-error" data-error-for="rating"></span>
                </label>
                <label>
                    <span class="ma-label">Stay / Booking Type</span>
                    <select class="ma-input mt-2" name="stay_type">
                        <option value="personal">Personal / Room Booking</option>
                        <option value="event_group">Events / Group Booking</option>
                        <option value="guest_inquiry">Guest Inquiry</option>
                    </select>
                    <span class="ma-error" data-error-for="stay_type"></span>
                </label>
                <label>
                    <span class="ma-label">Property</span>
                    <select class="ma-input mt-2" name="property_id">
                        <option value="">Select property</option>
                        @foreach ($properties as $property)
                            <option value="{{ $property->id }}">{{ $property->name }}</option>
                        @endforeach
                    </select>
                    <span class="ma-error" data-error-for="property_id"></span>
                    <span class="mt-1 block text-xs text-black/52">Required unless you enter a booking reference.</span>
                </label>
                <label>
                    <span class="ma-label">Reference Number</span>
                    <input class="ma-input mt-2 uppercase" name="reference_number" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="Optional for verified stay">
                    <span class="ma-error" data-error-for="reference_number"></span>
                    <span class="mt-1 block text-xs text-black/52">Checked-out bookings can be marked verified after staff review.</span>
                </label>
                <label class="md:col-span-2">
                    <span class="ma-label">Review Message</span>
                    <textarea class="ma-input mt-2 min-h-32" name="message" required></textarea>
                    <span class="ma-error" data-error-for="message"></span>
                </label>
                <button class="premium-button premium-button--navy md:col-span-2" type="submit" data-review-submit>Submit Review for Checking</button>
                <p id="review-feedback" class="md:col-span-2 min-h-5 text-sm font-semibold text-ma-blue" aria-live="polite"></p>
            </form>
        </div>
    </section>
</div>
@endsection
