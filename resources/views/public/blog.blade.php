@extends('layouts.public', [
    'title' => 'Stories & Guest Guide - Hotel Journal',
    'description' => 'Hotel stories, booking guidance, and stay planning notes from M.A Group of Hotels.',
])

@section('content')
    @php
        $posts = [
            [
                'title' => 'How to Plan a Comfortable Staycation with M.A Group of Hotels',
                'category' => 'Guest Tips',
                'read' => '2 min read',
                'excerpt' => 'Choose the right room, plan nearby meals, and give yourself enough time to enjoy the hotel spaces.',
                'image' => asset('images/home/property-suite.jpg'),
                'day' => '24',
                'month' => 'May',
            ],
            [
                'title' => 'Room Reservation Guide for First-Time Guests',
                'category' => 'Booking',
                'read' => '3 min read',
                'excerpt' => 'A simple walkthrough of property selection, available rooms, guest details, and reference number tracking.',
                'image' => asset('images/home/journey-personal.jpg'),
                'day' => '18',
                'month' => 'Jun',
            ],
            [
                'title' => 'Celebrate Special Moments in a Premium Hotel Setting',
                'category' => 'Celebrations',
                'read' => '2 min read',
                'excerpt' => 'From family gatherings to group requests, event bookings help staff review space needs before pricing.',
                'image' => asset('images/home/journey-event.jpg'),
                'day' => '07',
                'month' => 'Jul',
            ],
        ];
    @endphp

<div class="premium-page">
    <x-public.page-hero
        eyebrow="Stories and guest guide"
        title="Stories & Guest Guide - Hotel Journal"
        copy="Hotel stories, guest guides, stay reminders, and event updates for M.A Group of Hotels guests."
        :image="asset('images/home/property-resort.jpg')"
        alt="M.A resort destination and travel journal setting"
        compact
    >
        <x-slot:actions>
            <a class="premium-button premium-button--gold" href="#journal">Browse journal</a>
            <a class="premium-button premium-button--ghost" href="{{ route('book.now') }}">Plan a stay</a>
        </x-slot:actions>
    </x-public.page-hero>

    <section id="journal" class="premium-section bg-white">
        <div class="premium-shell">
            <div class="premium-heading-row" data-premium-reveal>
                <x-public.page-section-heading
                    eyebrow="Latest notes"
                    title="Thoughtful guidance for easier stays."
                    copy="Practical booking notes, room guidance, and celebration ideas for every kind of M.A guest."
                />
                <form class="w-full max-w-sm" data-journal-search role="search">
                    <label class="ma-label" for="journal-search">Search journal</label>
                    <div class="mt-2 flex gap-2">
                        <input id="journal-search" class="ma-input" type="search" name="search" placeholder="Search posts">
                        <button class="premium-button premium-button--navy" type="submit">Search</button>
                    </div>
                    <p class="mt-2 min-h-5 text-sm text-black/55" data-journal-feedback aria-live="polite"></p>
                </form>
            </div>

            <div class="mt-12 grid gap-8 lg:grid-cols-[1fr_18rem]">
                <div class="grid gap-6 md:grid-cols-2" data-premium-stagger>
                    @foreach ($posts as $post)
                        <article
                            class="group {{ $loop->first ? 'md:col-span-2 md:grid md:grid-cols-[1.15fr_.85fr]' : '' }} overflow-hidden border border-[#d9dee6] bg-white"
                            data-journal-post
                            data-search-text="{{ strtolower($post['title'].' '.$post['category'].' '.$post['excerpt']) }}"
                        >
                            <div class="premium-media relative {{ $loop->first ? 'aspect-[16/10] md:aspect-auto' : 'aspect-[16/10]' }} rounded-none">
                                <img src="{{ $post['image'] }}" alt="{{ $post['title'] }}" width="1200" height="800" loading="lazy" decoding="async">
                                <time class="absolute left-4 top-4 rounded-sm bg-[#d4a24c] px-3 py-2 text-center font-sans text-xs font-semibold leading-tight text-[#111d32]">{{ $post['day'] }}<br>{{ $post['month'] }}</time>
                            </div>
                            <div class="flex flex-col justify-center p-6 lg:p-8">
                                <p class="premium-kicker">{{ $post['category'] }} &middot; {{ $post['read'] }}</p>
                                <h2 class="mt-3 text-2xl font-medium leading-tight text-ma-blue {{ $loop->first ? 'lg:text-3xl' : '' }}">{{ $post['title'] }}</h2>
                                <p class="mt-4 text-sm leading-7 text-black/62">{{ $post['excerpt'] }}</p>
                                <a class="premium-text-link mt-6" href="{{ route('contact') }}">Read More <span>&rarr;</span></a>
                            </div>
                        </article>
                    @endforeach
                    <div class="hidden border border-[#d9dee6] p-6 md:col-span-2" data-journal-empty>
                        <h2 class="text-xl font-medium text-ma-blue">No journal articles found.</h2>
                        <p class="mt-2 text-sm text-black/60">Try a broader topic such as booking, rooms, or celebrations.</p>
                    </div>
                </div>

                <aside class="content-start lg:sticky lg:top-28 lg:self-start" data-premium-reveal>
                    <section class="border-t border-[#d9dee6] py-5">
                        <h2 class="text-lg font-medium text-ma-blue">Recent Posts</h2>
                        <ol class="premium-rule-list mt-3">
                            @foreach ($posts as $post)
                                <li class="text-sm font-semibold leading-6 text-black/68">{{ $post['title'] }}</li>
                            @endforeach
                        </ol>
                    </section>
                    <section class="border-t border-[#d9dee6] py-5">
                        <p class="premium-kicker">Featured Stay</p>
                        <h2 class="mt-2 text-xl font-medium text-ma-blue">Family Room</h2>
                        <p class="mt-3 text-sm leading-6 text-black/60">Space to rest, reconnect, and settle into a comfortable family visit.</p>
                        <a class="premium-text-link mt-5" href="{{ route('book.now') }}">Book Now <span>&rarr;</span></a>
                    </section>
                </aside>
            </div>
        </div>
    </section>
</div>
@endsection
