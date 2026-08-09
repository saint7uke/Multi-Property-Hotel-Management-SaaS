@props(['review'])

<article {{ $attributes->class('home-review min-w-0') }}>
    <div class="flex items-center justify-between gap-4">
        <p class="home-kicker">Verified guest</p>
        <p class="home-review__rating" aria-label="Rated {{ $review->rating }} out of 5">{{ $review->rating }}/5</p>
    </div>
    <blockquote>&ldquo;{{ $review->message }}&rdquo;</blockquote>
    <footer>
        <p>{{ $review->guest->name }}</p>
        @if ($review->property)
            <span>{{ $review->property->name }}</span>
        @endif
    </footer>
</article>
