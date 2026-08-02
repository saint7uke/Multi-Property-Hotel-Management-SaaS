@props([
    'title',
    'copy' => null,
    'eyebrow' => null,
    'light' => false,
])

<header {{ $attributes->class(['home-section-heading', 'home-section-heading--light' => $light]) }}>
    @if ($eyebrow)
        <p class="home-kicker">{{ $eyebrow }}</p>
    @endif
    <h2>{{ $title }}</h2>
    @if ($copy)
        <p class="home-section-heading__copy">{{ $copy }}</p>
    @endif
</header>
