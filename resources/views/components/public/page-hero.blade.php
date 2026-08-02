@props([
    'title',
    'copy',
    'image',
    'alt',
    'eyebrow' => null,
    'compact' => false,
])

<section {{ $attributes->class(['premium-hero', 'premium-hero--compact' => $compact]) }} data-premium-hero>
    <img
        class="premium-hero__image"
        src="{{ $image }}"
        alt="{{ $alt }}"
        width="2000"
        height="1200"
        fetchpriority="high"
        decoding="async"
        data-premium-hero-image
    >
    <div class="premium-hero__scrim" aria-hidden="true"></div>
    <div class="premium-shell premium-hero__inner">
        <div class="premium-hero__copy" data-premium-hero-copy>
            @if ($eyebrow)
                <p class="premium-kicker">{{ $eyebrow }}</p>
            @endif
            <h1>{{ $title }}</h1>
            <p>{{ $copy }}</p>
            @isset($actions)
                <div class="premium-hero__actions">{{ $actions }}</div>
            @endisset
        </div>
    </div>
</section>
