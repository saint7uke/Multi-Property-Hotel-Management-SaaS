<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? 'Book rooms, events, and group stays with M.A Group of Hotels.' }}">
    <title>{{ $title ?? 'M.A Group of Hotels' }}</title>
    <x-brand.favicon />
    <style>
        html,body{margin:0;max-width:100%;overflow-x:hidden;background:#f7f8fa;color:#1b2432;font-family:Poppins,Helvetica,Arial,sans-serif}
        img,video,canvas,svg{max-width:100%;height:auto}
        .sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}
        .sr-only:focus{position:fixed;left:1rem;top:1rem;z-index:99999;width:auto;height:auto;clip:auto;border-radius:.375rem;background:#d4a24c;padding:.55rem .8rem;color:#101a2e}
        header.sticky{position:sticky;top:0;z-index:40;border-bottom:1px solid rgba(0,0,0,.1);background:rgba(255,255,255,.96);backdrop-filter:blur(12px)}
        .ma-site-header__inner{display:flex;height:4.75rem;max-width:80rem;align-items:center;justify-content:space-between;gap:1rem;margin-inline:auto;padding-inline:1rem}
        .ma-nav-brand{display:inline-flex;align-items:center;min-width:0}
        .ma-global-logo,.ma-nav-brand__logo{display:block;width:clamp(2.75rem,8vw,3.5rem)!important;height:clamp(2.75rem,8vw,3.5rem)!important;max-width:100%;border-radius:9999px;object-fit:contain}
        .ma-nav-link{color:#1b2432;text-decoration:none;font-size:.9rem;font-weight:600}
        .ma-btn-primary,.ma-btn-secondary,.ma-btn-accent,.home-button,.premium-button{display:inline-flex;min-height:2.75rem;align-items:center;justify-content:center;border-radius:.375rem;padding:.7rem 1rem;text-decoration:none;font-size:.82rem;font-weight:700;line-height:1.2}
        .ma-btn-primary,.home-button--dark{background:#16233f;color:#fff}
        .ma-btn-secondary{border:1px solid #d9dee6;background:#fff;color:#16233f}
        .ma-btn-accent,.home-button--accent{background:#d4a24c;color:#101a2e}
        [data-mobile-menu].hidden,.hidden{display:none}
        [data-mobile-menu]{border-top:1px solid rgba(0,0,0,.1);background:#fff;padding:1rem}
        [data-mobile-menu] div{display:grid;gap:.4rem}
        [data-mobile-menu] a{border-radius:.375rem;padding:.65rem .75rem;color:#1b2432;text-decoration:none;font-size:.9rem;font-weight:700}
        .home-shell,.premium-shell{width:min(100% - 2rem,82rem);margin-inline:auto}
        .home-hero,.premium-hero,.hotel-hero{position:relative;isolation:isolate;min-height:31rem;overflow:hidden;background:#111d32;color:#fff}
        .home-hero__image,.premium-hero__image,.hotel-hero__image{position:absolute;inset:0;z-index:-2;width:100%;height:100%;object-fit:cover}
        .home-hero__scrim,.premium-hero__scrim,.hotel-hero__scrim{position:absolute;inset:0;z-index:-1;background:linear-gradient(90deg,rgba(9,18,32,.88),rgba(9,18,32,.42))}
        .home-hero__inner,.premium-hero__inner,.hotel-hero__inner{display:flex;min-height:31rem;align-items:flex-end;padding-block:5rem 3.25rem}
        .home-hero h1,.premium-hero h1,.hotel-hero h1{max-width:11ch;margin:.9rem 0 0;color:#fff;font-size:clamp(2.45rem,12vw,4.5rem);font-weight:600;line-height:1.05}
        .home-hero p,.premium-hero p,.hotel-hero p{max-width:38rem;font-size:1rem;line-height:1.65}
        .home-search,.home-service-strip,.home-property-grid,.premium-contact-strip{display:grid;grid-template-columns:1fr;gap:0;width:min(100% - 1.5rem,82rem);margin-inline:auto}
        .home-search{overflow:hidden;border:1px solid #d9dee6;border-radius:.5rem;background:#fff;box-shadow:0 12px 34px rgba(17,29,50,.09)}
        .home-search>*{min-width:0;border-bottom:1px solid #d9dee6;padding:1rem}
        .home-search input,.home-search select,.ma-input{width:100%;max-width:100%;min-width:0;font-size:16px}
        @media (min-width:1024px){.ma-site-header__inner{height:5rem;padding-inline:2rem}.ma-mobile-menu-toggle{display:none!important}nav.lg\:flex{display:flex!important;align-items:center;gap:1.75rem}[data-mobile-menu]{display:none!important}.home-hero,.premium-hero,.hotel-hero{min-height:34rem}.home-hero__inner,.premium-hero__inner,.hotel-hero__inner{align-items:center;min-height:34rem}.home-search{grid-template-columns:1.2fr repeat(4,minmax(0,1fr)) auto}.home-service-strip{grid-template-columns:repeat(3,1fr)}}
        @media (max-width:1023px){nav.lg\:flex{display:none!important}.ma-mobile-menu-toggle{display:inline-flex!important}}
        @media (max-width:767px){
            .ma-site-header__inner{height:4.5rem;padding-inline:.875rem}
            .ma-mobile-menu-toggle{min-height:2.75rem;min-width:2.75rem}
            [data-mobile-menu]{max-height:calc(100dvh - 4.5rem);overflow-y:auto}
            [data-mobile-menu] a{display:flex;min-height:2.75rem;align-items:center}
            .home-shell,.premium-shell{width:calc(100% - 1.75rem)}
            .home-hero,.premium-hero,.hotel-hero,.home-hero__inner,.premium-hero__inner,.hotel-hero__inner{min-height:31rem;height:auto}
            .home-hero__inner,.premium-hero__inner,.hotel-hero__inner{padding-block:4rem 3rem}
            .home-hero h1,.premium-hero h1,.hotel-hero h1{max-width:100%;font-size:clamp(2.25rem,11vw,3rem);overflow-wrap:anywhere}
            .home-hero p,.premium-hero p,.hotel-hero p{max-width:100%;font-size:1rem}
            .home-hero__actions,.premium-hero__actions,.hotel-hero__actions{display:grid;width:100%;grid-template-columns:1fr;gap:.75rem}
            .home-hero__actions>* ,.premium-hero__actions>* ,.hotel-hero__actions>*{width:100%}
            input,select,textarea{max-width:100%;min-width:0;font-size:16px}
            footer section{min-width:0}footer p,footer li,footer a{overflow-wrap:anywhere}
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/public.ts'])
</head>
<body class="public-site min-h-dvh bg-ma-white font-sans text-ma-ink antialiased" data-responsive-shell @if($autoScroll ?? false) data-auto-scroll="true" @endif>
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

    <main id="main" class="min-w-0">
        @yield('content')
    </main>

    <footer class="ma-ocean-panel ma-torn-top mt-0 min-w-0 pt-16 text-white" style="--ma-footer-image: url('{{ asset('images/home/getaway.jpg') }}')">
        <div class="absolute inset-0 bg-ma-blue/50"></div>
        <div class="relative mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-[1.15fr_.85fr_.85fr_1fr] lg:px-8" data-gsap-reveal>
            <section class="min-w-0">
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
            <section class="min-w-0">
                <h2 class="font-display font-semibold">Reservations</h2>
                <ul class="mt-3 space-y-2 text-sm text-white/75">
                    <li>Tel 0183-12345678</li>
                    <li class="break-all">reservations@magroupofhotels.com</li>
                    <li>Book online anytime</li>
                </ul>
            </section>
            <section class="min-w-0">
                <h2 class="font-display font-semibold">Guest Services</h2>
                <ul class="mt-3 space-y-2 text-sm text-white/75">
                    <li>Room Reservations</li>
                    <li>Events &amp; Celebrations</li>
                    <li>Corporate Accommodation</li>
                </ul>
            </section>
            <section class="min-w-0">
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
