<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? 'Book rooms, events, and group stays with M.A Group of Hotels.' }}">
    <title>{{ $title ?? 'M.A Group of Hotels' }}</title>
    <x-brand.favicon />
    @vite(['resources/css/app.css', 'resources/js/public.ts'])
</head>
<body class="public-site min-h-dvh bg-ma-white font-sans text-ma-ink antialiased" @if($autoScroll ?? false) data-auto-scroll="true" @endif>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-ma-gold focus:px-4 focus:py-2 focus:text-ma-ink">Skip to content</a>

    <header class="sticky top-0 z-40 border-b border-black/10 bg-ma-white/95 backdrop-blur">
        <div class="ma-site-header__inner mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="ma-nav-brand" aria-label="M.A Group of Hotels home">
                <x-brand.logo class="ma-nav-brand__logo" />
            </a>
            <nav class="hidden items-center gap-7 lg:flex" aria-label="Primary navigation">
                <a class="ma-nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}" href="{{ route('home') }}">Home</a>
                <a class="ma-nav-link {{ request()->routeIs('about') ? 'is-active' : '' }}" href="{{ route('about') }}">About us</a>
                <a class="ma-nav-link {{ request()->routeIs('blog') ? 'is-active' : '' }}" href="{{ route('blog') }}">Blog</a>
                <a class="ma-nav-link {{ request()->routeIs('contact') ? 'is-active' : '' }}" href="{{ route('contact') }}">Contact Us</a>
                <a class="ma-btn-primary" href="{{ route('book.now') }}">Book Now</a>
                <a class="ma-btn-secondary" href="{{ route('staff.login') }}">Staff Login</a>
            </nav>
            <button
                type="button"
                class="ma-btn-secondary ma-mobile-menu-toggle"
                data-mobile-menu-button
                aria-expanded="false"
                aria-controls="mobile-menu"
            >
                Menu
            </button>
        </div>
        <nav id="mobile-menu" class="hidden border-t border-black/10 bg-ma-white px-4 py-4 lg:hidden" data-mobile-menu aria-label="Mobile navigation">
            <div class="mx-auto grid max-w-7xl gap-2 text-sm font-semibold">
                <a class="rounded-md px-3 py-2 hover:bg-ma-cream/70" href="{{ route('home') }}">Home</a>
                <a class="rounded-md px-3 py-2 hover:bg-ma-cream/70" href="{{ route('about') }}">About us</a>
                <a class="rounded-md px-3 py-2 hover:bg-ma-cream/70" href="{{ route('blog') }}">Blog</a>
                <a class="rounded-md px-3 py-2 hover:bg-ma-cream/70" href="{{ route('contact') }}">Contact Us</a>
                <a class="ma-btn-primary mt-2" href="{{ route('book.now') }}">Book Now</a>
                <a class="ma-btn-secondary" href="{{ route('staff.login') }}">Staff Login</a>
            </div>
        </nav>
    </header>

    <main id="main">
        @yield('content')
    </main>

    <footer class="ma-ocean-panel ma-torn-top mt-0 pt-16 text-white" style="--ma-footer-image: url('{{ asset('images/home/getaway.jpg') }}')">
        <div class="absolute inset-0 bg-ma-blue/50"></div>
        <div class="relative mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.15fr_.85fr_.85fr_1fr] lg:px-8" data-gsap-reveal>
            <section>
                <h2 class="font-display text-2xl font-bold">Our Address</h2>
                <p class="mt-3 text-sm leading-6 text-white/75">M.A Group of Hotels, Philippines</p>
                <p class="text-sm leading-6 text-white/75">Hospitality and Accommodation</p>
                <div class="mt-5 grid gap-2 text-sm text-white/75">
                    @forelse ($footerProperties ?? [] as $footerProperty)
                        <a class="hover:text-ma-gold" href="{{ route('hotels.show', $footerProperty) }}">{{ $footerProperty->name }}</a>
                    @empty
                        <span>Properties coming soon</span>
                    @endforelse
                </div>
            </section>
            <section>
                <h2 class="font-display font-semibold">Reservations</h2>
                <ul class="mt-3 space-y-2 text-sm text-white/75">
                    <li>Tel 0183-12345678</li>
                    <li>reservations@magroupofhotels.com</li>
                    <li>Book online anytime</li>
                </ul>
            </section>
            <section>
                <h2 class="font-display font-semibold">Guest Services</h2>
                <ul class="mt-3 space-y-2 text-sm text-white/75">
                    <li>Room Reservations</li>
                    <li>Events &amp; Celebrations</li>
                    <li>Corporate Accommodation</li>
                </ul>
            </section>
            <section>
                <h2 class="font-display font-semibold">Member Getaway Rates</h2>
                <p class="mt-2 text-sm leading-6 text-white/70">Selected rate updates, property announcements, and seasonal stay ideas.</p>
                <form class="mt-3 grid gap-3" novalidate data-newsletter-form data-endpoint="/api/public/newsletter-subscriptions">
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                    <label class="sr-only" for="newsletter-email">Email address</label>
                    <input id="newsletter-email" class="ma-input border-white/20 bg-white/95" type="email" name="email" placeholder="Email address" autocomplete="email" required>
                    <span class="text-xs text-rose-200" data-error-for="email"></span>
                    <button class="ma-btn-accent" type="submit" data-form-submit>Subscribe</button>
                    <p class="public-form-feedback min-h-5 text-sm" data-newsletter-feedback aria-live="polite" role="status"></p>
                </form>
            </section>
        </div>
        <div class="relative border-t border-white/15">
            <div class="mx-auto grid max-w-7xl gap-4 px-4 py-6 text-sm text-white/75 sm:px-6 lg:grid-cols-[1fr_1.2fr] lg:px-8">
                <p>Awards and partners: Certificate of Excellence, Commitment to Excellence, Guest Choice Award.</p>
                <p>Partner logos and brand details will be updated once provided by M.A Team.</p>
                <p class="lg:col-span-2">Copyright &copy; 2026 All rights reserved | M.A Group of Hotels</p>
            </div>
        </div>
    </footer>
    <x-public.hotel-assistant />
    <x-chat.widget />
</body>
</html>
