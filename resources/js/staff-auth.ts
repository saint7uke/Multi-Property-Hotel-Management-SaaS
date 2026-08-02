import { gsap } from 'gsap';

document.addEventListener('DOMContentLoaded', () => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const page = document.querySelector<HTMLElement>('[data-staff-auth]');
    if (!page) return;

    const context = gsap.context(() => {
        gsap.from('[data-auth-copy] > *', {
            opacity: .78,
            y: 28,
            duration: .75,
            stagger: .09,
            ease: 'power3.out',
            clearProps: 'opacity,transform',
        });

        gsap.from('[data-auth-form]', {
            opacity: .86,
            x: 28,
            duration: .8,
            delay: .15,
            ease: 'power3.out',
            clearProps: 'opacity,transform',
        });
    }, page);

    window.addEventListener('pagehide', () => context.revert(), { once: true });
});
