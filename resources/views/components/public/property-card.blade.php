@props([
    'property',
    'image',
    'featured' => false,
])

<article {{ $attributes->class(['home-property group min-w-0', 'home-property--featured' => $featured]) }}>
    <a class="block" href="{{ route('hotels.show', $property) }}" aria-label="View {{ $property->name }}">
        <div class="home-property__media">
            <img
                src="{{ $image }}"
                alt="Guest experience at {{ $property->name }}"
                width="1200"
                height="900"
                loading="lazy"
                decoding="async"
            >
            @if ($property->offers_breakfast)
                <span class="home-property__flag">Breakfast available</span>
            @endif
        </div>
        <div class="home-property__body">
            <div>
                <p class="home-kicker">{{ $property->city }}, {{ $property->country }}</p>
                <h3>{{ $property->name }}</h3>
                <p>{{ $property->tagline ?: $property->address }}</p>
            </div>
            <span class="home-text-link" aria-hidden="true">View property <span>→</span></span>
        </div>
    </a>
</article>
