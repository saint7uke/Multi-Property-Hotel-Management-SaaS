@extends('layouts.public', [
    'title' => 'M.A Group of Hotels - About Us',
    'description' => 'Learn about the mission, vision, hospitality standards, and service network behind M.A Group of Hotels.',
])

@section('content')
<div class="premium-page responsive-page">
    <x-public.page-hero
        eyebrow="Our story"
        title="M.A Group of Hotels - About Us"
        copy="Comfort, privacy, convenience, and warm hospitality guide every guest stay and property experience."
        :image="asset('images/home/hotel-team.jpg')"
        alt="M.A Group of Hotels team welcoming guests"
    >
        <x-slot:actions>
            <a class="premium-button premium-button--gold" href="{{ route('book.now') }}">Book a stay</a>
            <a class="premium-button premium-button--ghost" href="#our-purpose">Our purpose</a>
        </x-slot:actions>
    </x-public.page-hero>

    <section id="our-purpose" class="premium-section bg-white">
        <div class="premium-shell">
            <div class="grid items-start gap-12 lg:grid-cols-[1.08fr_.92fr]" data-premium-reveal>
                <figure class="premium-media aspect-[5/4] lg:sticky lg:top-28">
                    <img src="{{ asset('images/home/property-lobby.jpg') }}" alt="Elegant M.A hotel lobby and guest seating" width="1400" height="1100" loading="lazy" decoding="async" data-premium-parallax>
                </figure>

                <div>
                    <x-public.page-section-heading
                        eyebrow="Hospitality with comfort and style"
                        title="A considered place to rest, work, and celebrate."
                        copy="A single view of who M.A Group of Hotels is, what guides the team, and the service network behind each guest stay."
                    />
                    <div class="mt-7 space-y-5 text-base leading-8 text-black/68">
                        <h3 class="font-display text-2xl font-medium text-ma-blue">Quick Overview</h3>
                        <p>M.A Group of Hotels is built for guests who want a comfortable place to rest, work, and celebrate with reliable support from the hotel team.</p>
                        <p>Our properties prioritize privacy, convenient access, clean rooms, and a booking process that stays easy from inquiry to confirmation.</p>
                        <p>Every stay is handled with warm hospitality, whether the request is a personal room, family visit, business accommodation, event, or group booking.</p>
                    </div>
                </div>
            </div>

            <div class="mt-20 grid border-y border-[#d9dee6] lg:grid-cols-3" data-premium-stagger>
                @foreach ([
                    ['number' => '01', 'title' => 'Mission', 'copy' => 'To provide relaxing accommodation, attentive service, and dependable reservation support for guests traveling for work, family, or special occasions.'],
                    ['number' => '02', 'title' => 'Vision', 'copy' => 'To be a trusted hotel group known for thoughtful stays, organized service, and accessible hospitality across partner properties.'],
                    ['number' => '03', 'title' => 'Others', 'copy' => 'Recognition, partner references, and final brand assets can be updated as M.A Team provides official material.'],
                ] as $purpose)
                    <article class="px-0 py-8 lg:px-8 lg:first:pl-0 lg:last:pr-0 lg:[&+&]:border-l lg:[&+&]:border-[#d9dee6]">
                        <span class="premium-kicker">{{ $purpose['number'] }}</span>
                        <h3 class="mt-3 text-2xl font-medium text-ma-blue">{{ $purpose['title'] }}</h3>
                        <p class="mt-4 text-base leading-7 text-black/65">{{ $purpose['copy'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="premium-section premium-dark-band">
        <div class="premium-shell">
            <div class="grid gap-12 lg:grid-cols-[.72fr_1.28fr]">
                <div data-premium-reveal>
                    <x-public.page-section-heading
                        light
                        eyebrow="Recognition and network"
                        title="Standards supported by trusted travel connections."
                        copy="Recognition and partner references help guests understand the service network around every M.A stay."
                    />
                    <div class="mt-8 border-t border-white/15 pt-6">
                        <h3 class="text-lg font-medium text-white">Recognition</h3>
                        <ul class="mt-4 grid gap-2 text-sm text-white/68">
                            <li>Certificate of Excellence</li>
                            <li>Commitment to Excellence</li>
                            <li>Guest Choice Award</li>
                        </ul>
                    </div>
                </div>

                <div data-premium-stagger>
                    <h3 class="text-lg font-medium text-white">Network Partners</h3>
                    <div class="mt-5 grid grid-cols-2 border-l border-t border-white/15 sm:grid-cols-3">
                        @foreach (['Cebu Pacific', 'Philippines Tourism', 'Klook', 'Traveloka', 'Agoda', 'Expedia', 'KKday', 'Via.com', 'Red Planet'] as $partner)
                            <div class="grid min-h-24 place-items-center border-b border-r border-white/15 p-4 text-center font-sans text-sm font-medium text-white/78">{{ $partner }}</div>
                        @endforeach
                    </div>
                    <p class="mt-5 text-sm leading-6 text-white/52">Official partner logos and final brand details will be updated once provided by M.A Team.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="premium-section--compact bg-[#eef1f5]">
        <div class="premium-shell flex flex-col gap-6 md:flex-row md:items-center md:justify-between" data-premium-reveal>
            <div>
                <h2 class="text-3xl font-medium text-ma-blue">Ready to experience M.A hospitality?</h2>
                <p class="mt-3 text-base leading-7 text-black/62">Send a reservation request and let the team review your stay details.</p>
            </div>
            <a class="premium-button premium-button--navy shrink-0" href="{{ route('book.now') }}">Book Now</a>
        </div>
    </section>
</div>
@endsection
