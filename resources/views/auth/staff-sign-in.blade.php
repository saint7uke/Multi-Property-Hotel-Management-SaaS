<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Staff Sign In | M.A Group of Hotels</title>
    <x-brand.favicon />
    @vite(['resources/css/app.css', 'resources/js/staff-auth.ts'])
</head>
<body class="premium-auth" data-staff-auth data-responsive-shell>
    <img
        class="premium-auth__image"
        src="{{ asset('images/home/property-lobby.jpg') }}"
        alt=""
        width="1600"
        height="1067"
        fetchpriority="high"
    >
    <div class="premium-auth__scrim" aria-hidden="true"></div>

    <main class="premium-auth__shell min-w-0">
        <a class="premium-auth__brand" href="{{ route('home') }}" aria-label="M.A Group of Hotels home">
            <x-brand.logo class="premium-auth__brand-logo" />
        </a>

        <section class="premium-auth__copy min-w-0" data-auth-copy aria-labelledby="staff-welcome">
            <p class="premium-kicker">Staff operations</p>
            <h1 id="staff-welcome">One secure entrance to your workspace.</h1>
            <p>Your account opens the correct hotel operations panel automatically, with access limited to your assigned role and property.</p>
            <span class="premium-auth__note">Authorized personnel only</span>
        </section>

        <section class="premium-auth__form min-w-0" data-auth-form aria-labelledby="sign-in-title">
            <p class="premium-kicker">Secure staff access</p>
            <h2 id="sign-in-title">Sign in</h2>
            <p class="premium-auth__intro">Use the staff account issued by your system administrator or hotel manager.</p>

            <form class="mt-7 grid gap-5" method="POST" action="{{ route('staff.authenticate') }}">
                @csrf

                <label class="grid gap-2" for="email">
                    <span class="ma-label">Email address</span>
                    <input
                        id="email"
                        class="ma-input"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        inputmode="email"
                        required
                        autofocus
                    >
                </label>

                <label class="grid gap-2" for="password">
                    <span class="ma-label">Password</span>
                    <input
                        id="password"
                        class="ma-input"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        minlength="8"
                        required
                    >
                </label>

                <label class="flex min-h-11 items-center gap-3 font-sans text-sm font-medium text-[#26334a]">
                    <input class="h-4 w-4 accent-[#111d32]" type="checkbox" name="remember" value="1">
                    <span>Keep me signed in on this device</span>
                </label>

                @if ($errors->any())
                    <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <button class="premium-button premium-button--navy w-full" type="submit">Sign in securely</button>
            </form>

            <div class="premium-auth__help">
                <a href="{{ route('home') }}">Back to website</a>
                <span>Need access? Contact your manager.</span>
            </div>
        </section>
    </main>
</body>
</html>
