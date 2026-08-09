@extends('layouts.public', [
    'title' => 'Booking Request | M.A Group of Hotels',
    'description' => 'Check room availability, submit personal or group booking requests, and look up an M.A reservation status.',
])

@section('content')
<div class="premium-page responsive-page">
    <x-public.page-hero
        eyebrow="Reservations"
        title="Booking Request"
        copy="Choose a room stay, event, or group request. Our Reservations Team will review and contact you."
        :image="asset('images/home/journey-personal.jpg')"
        alt="Prepared M.A hotel room for a guest reservation"
        compact
    >
        <x-slot:actions>
            <a class="premium-button premium-button--gold" href="#booking">Start booking</a>
            <a class="premium-button premium-button--ghost" href="#status">Check status</a>
        </x-slot:actions>
    </x-public.page-hero>

    <section id="booking" class="premium-section bg-[#eef1f5]">
        <div class="premium-shell grid gap-10 lg:grid-cols-[.72fr_1.28fr]">
            <aside class="lg:sticky lg:top-28 lg:self-start" data-premium-reveal>
                <x-public.page-section-heading
                    eyebrow="Rooms and events"
                    title="Submit Reservation"
                    copy="Available rooms load by selected property, dates, and guest count. Event and group requests do not require a room because staff reviews venue needs first."
                />
                <div class="mt-7 border-y border-[#d9dee6] py-5">
                    <p class="premium-kicker">Estimated Total</p>
                    <p id="booking-estimate" class="mt-2 text-3xl font-bold tabular-nums text-ma-ink">PHP 0.00</p>
                    <p id="booking-feedback" class="mt-3 text-sm font-medium text-black/65" aria-live="polite"></p>
                    <p class="mt-3 text-xs font-medium text-black/55">Final amount may be reviewed by Reservations Team.</p>
                </div>
                <ol class="premium-rule-list mt-7 text-sm leading-6 text-black/62">
                    <li><strong class="font-sans text-ma-blue">Personal booking:</strong> choose an available room for your dates.</li>
                    <li><strong class="font-sans text-ma-blue">Event or group:</strong> send requirements for staff review without selecting a room.</li>
                    <li><strong class="font-sans text-ma-blue">Confirmation:</strong> keep the reference number shown after submission.</li>
                </ol>
            </aside>

            <form id="booking-form" class="premium-form grid gap-5 p-6 sm:grid-cols-2 lg:p-8" novalidate data-premium-reveal>
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                <div class="sm:col-span-2">
                    <span class="ma-label">Booking type</span>
                    <div class="mt-2 grid grid-cols-2 gap-2 rounded-md bg-[#eef1f5] p-1" role="radiogroup" aria-label="Booking type">
                        <label class="cursor-pointer rounded-md bg-white px-3 py-3 text-center font-sans text-sm font-semibold">
                            <input class="sr-only" type="radio" name="booking_type" value="personal" checked>
                            Personal / Room Booking
                        </label>
                        <label class="cursor-pointer rounded-md px-3 py-3 text-center font-sans text-sm font-semibold">
                            <input class="sr-only" type="radio" name="booking_type" value="event">
                            Events / Group Booking
                        </label>
                    </div>
                </div>

                <label>
                    <span class="ma-label">Hotel Property</span>
                    <select class="ma-input mt-1" name="property_id" required>
                        <option value="">Select property</option>
                        @foreach ($properties as $property)
                            <option value="{{ $property->id }}" data-breakfast="{{ $property->offers_breakfast ? '1' : '0' }}">{{ $property->name }}</option>
                        @endforeach
                    </select>
                    <span class="ma-error" data-error-for="property_id"></span>
                </label>

                <label>
                    <span class="ma-label">Preferred Area / Location</span>
                    <input class="ma-input mt-1" name="preferred_area" placeholder="City, area, or landmark">
                    <span class="ma-error" data-error-for="preferred_area"></span>
                </label>

                <label>
                    <span class="ma-label">Number of Rooms</span>
                    <input class="ma-input mt-1" type="number" min="1" max="10" name="room_count" value="1" required>
                    <span class="ma-error" data-error-for="room_count"></span>
                </label>

                <label class="hidden border-y border-black/10 bg-[#f7f8fa] p-4 sm:col-span-2" data-booking-breakfast-field>
                    <span class="flex items-start gap-3">
                        <input class="mt-1 h-4 w-4 accent-ma-blue" type="checkbox" name="wants_breakfast" value="1">
                        <span>
                            <span class="ma-label block">With Breakfast</span>
                            <span class="mt-1 block text-xs font-medium text-black/55">Shown only when the selected property offers breakfast.</span>
                        </span>
                    </span>
                </label>

                <label data-booking-room-field>
                    <span class="ma-label">Available Room</span>
                    <select class="ma-input mt-1" name="room_id">
                        <option value="">Choose property and dates first</option>
                    </select>
                    <span class="ma-error" data-error-for="room_id"></span>
                </label>

                <label>
                    <span class="ma-label">Full Name</span>
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
                    <span class="ma-label">Event / Group Name</span>
                    <input class="ma-input mt-1" name="event_name">
                    <span class="ma-error" data-error-for="event_name"></span>
                </label>

                <label>
                    <span class="ma-label">Check-in</span>
                    <input class="ma-input mt-1" type="date" name="check_in" required>
                    <span class="ma-error" data-error-for="check_in"></span>
                </label>

                <label>
                    <span class="ma-label">Check-out</span>
                    <input class="ma-input mt-1" type="date" name="check_out" required>
                    <span class="ma-error" data-error-for="check_out"></span>
                </label>

                <label>
                    <span class="ma-label">Adults</span>
                    <input class="ma-input mt-1" type="number" min="1" max="6" name="adults" value="2" required>
                    <span class="ma-error" data-error-for="adults"></span>
                </label>

                <label>
                    <span class="ma-label">Children</span>
                    <input class="ma-input mt-1" type="number" min="0" max="4" name="children" value="0">
                    <span class="ma-error" data-error-for="children"></span>
                </label>

                <label class="sm:col-span-2">
                    <span class="ma-label">Special Request</span>
                    <textarea class="ma-input mt-1 min-h-28" name="special_request"></textarea>
                    <span class="ma-error" data-error-for="special_request"></span>
                </label>

                <fieldset class="grid gap-3 border-y border-black/10 bg-[#f7f8fa] p-4 sm:col-span-2">
                    <legend class="ma-label px-1">Add-ons (Optional)</legend>
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'extra_bed' => 'Extra Bed',
                            'extra_pax' => 'Extra Pax',
                            'additional_breakfast' => 'Additional Breakfast',
                            'early_check_in' => 'Early Check-in',
                            'late_check_out' => 'Late Check-out',
                        ] as $value => $label)
                            <label class="flex items-center gap-3 border-b border-black/8 px-1 py-2 font-sans text-sm font-semibold text-ma-ink">
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

                <label class="flex items-start gap-3 border-y border-black/10 bg-[#f7f8fa] p-4 sm:col-span-2">
                    <input class="mt-1 h-4 w-4 accent-ma-blue" type="checkbox" name="terms_accepted" value="1" required>
                    <span>
                        <span class="ma-label block">I agree to the cancellation policy, terms and conditions, and privacy policy.</span>
                        <span class="ma-error" data-error-for="terms_accepted"></span>
                    </span>
                </label>

                <button class="premium-button premium-button--navy sm:col-span-2" type="submit" data-booking-submit>Confirm Booking</button>
                <p class="sm:col-span-2 text-sm font-medium text-black/60">A reference number appears after submission. Full confirmation follows staff or payment gateway verification.</p>
            </form>
        </div>
    </section>

    <section id="status" class="premium-section premium-dark-band">
        <div class="premium-shell grid gap-10 lg:grid-cols-[.82fr_1.18fr]">
            <x-public.page-section-heading
                light
                eyebrow="Reservation lookup"
                title="Check a booking status."
                copy="Use your reference number and email to view the latest reservation and payment state."
                data-premium-reveal
            />
            <form id="status-form" class="premium-form grid gap-5 p-6 text-ma-ink lg:p-8" novalidate data-premium-reveal>
                <label>
                    <span class="ma-label">Reference number</span>
                    <input class="ma-input mt-1 uppercase" name="reference_number" placeholder="MAH-YYYYMMDD-ABC123" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                    <span class="ma-error" data-error-for="reference_number"></span>
                    <span class="mt-1 block text-xs font-medium text-black/55">Use the reference shown after booking. Email is required to protect your details.</span>
                </label>
                <label>
                    <span class="ma-label">Email</span>
                    <input class="ma-input mt-1" type="email" name="email" autocomplete="email" required>
                    <span class="ma-error" data-error-for="email"></span>
                </label>
                <button class="premium-button premium-button--navy" type="submit" data-status-submit>Look up booking</button>
                <div id="status-result" class="min-h-16 border-l-2 border-[#d4a24c] bg-[#f7f8fa] p-4 text-sm leading-6 text-ma-ink" aria-live="polite"></div>
            </form>
        </div>
    </section>
</div>
@endsection
