@props([
    'title',
    'copy' => null,
    'eyebrow' => null,
    'light' => false,
])

<header {{ $attributes->class(['premium-section-heading min-w-0', 'premium-section-heading--light' => $light]) }}>
    @if ($eyebrow)
        <p class="premium-kicker">{{ $eyebrow }}</p>
    @endif
    <h2>{{ $title }}</h2>
    @if ($copy)
        <p>{{ $copy }}</p>
    @endif
</header>
