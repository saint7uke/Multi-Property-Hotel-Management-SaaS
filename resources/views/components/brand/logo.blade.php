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
    style="display:block;width:clamp(2.75rem,8vw,3.5rem);height:clamp(2.75rem,8vw,3.5rem);max-width:100%;border-radius:9999px;object-fit:contain;"
    {{ $attributes->class(['ma-global-logo', $class]) }}
>
