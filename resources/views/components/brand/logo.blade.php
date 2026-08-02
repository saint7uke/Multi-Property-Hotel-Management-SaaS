@props([
    'class' => '',
    'loading' => 'eager',
])

<img
    src="{{ asset('MALogo.png') }}"
    alt="M.A Group of Hotels"
    width="500"
    height="500"
    loading="{{ $loading }}"
    decoding="async"
    {{ $attributes->class(['ma-global-logo', $class]) }}
>
