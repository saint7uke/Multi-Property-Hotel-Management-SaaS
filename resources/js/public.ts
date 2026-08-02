import './bootstrap';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

type JsonResponse = Record<string, any>;

const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });

async function request(url: string, options: RequestInit = {}): Promise<JsonResponse> {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            ...(options.headers ?? {}),
        },
        credentials: 'same-origin',
        ...options,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw data;
    }

    return data;
}

function formData(form: HTMLFormElement): Record<string, any> {
    const data = new FormData(form);
    const payload: Record<string, any> = {};

    data.forEach((value, key) => {
        if (key.endsWith('[]')) {
            const normalizedKey = key.slice(0, -2);
            payload[normalizedKey] = [...(payload[normalizedKey] ?? []), value];
            return;
        }

        if (payload[key] === undefined) {
            payload[key] = value;
        } else {
            payload[key] = Array.isArray(payload[key]) ? [...payload[key], value] : [payload[key], value];
        }
    });

    return payload;
}

function normalizeReferenceNumber(value: string): string {
    return value.replace(/\s+/g, '').toUpperCase();
}

function escapeHtml(value: unknown): string {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character] ?? character));
}

function human(value: string): string {
    return value.replaceAll('_', ' ');
}

function setError(form: HTMLFormElement, field: string, message = ''): void {
    const element = form.querySelector<HTMLElement>(`[data-error-for="${field}"]`);
    if (element) {
        element.textContent = message;
    }
}

function clearErrors(form: HTMLFormElement): void {
    form.querySelectorAll<HTMLElement>('[data-error-for]').forEach((element) => {
        element.textContent = '';
    });
}

function showErrors(form: HTMLFormElement, errors: Record<string, string[]> = {}): void {
    clearErrors(form);
    Object.entries(errors).forEach(([field, messages]) => setError(form, field, messages[0] ?? 'Invalid value.'));
}

function debounce(callback: () => void, delay = 300): () => void {
    let timer: number | undefined;

    return () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(callback, delay);
    };
}

function initBookingForm(): void {
    const form = document.querySelector<HTMLFormElement>('#booking-form');
    if (!form) return;

    const roomSelect = form.elements.namedItem('room_id') as HTMLSelectElement;
    const eventName = form.elements.namedItem('event_name') as HTMLInputElement;
    const propertySelect = form.elements.namedItem('property_id') as HTMLSelectElement;
    const checkIn = form.elements.namedItem('check_in') as HTMLInputElement;
    const checkOut = form.elements.namedItem('check_out') as HTMLInputElement;
    const adults = form.elements.namedItem('adults') as HTMLInputElement;
    const children = form.elements.namedItem('children') as HTMLInputElement;
    const roomCount = form.elements.namedItem('room_count') as HTMLInputElement;
    const preferredArea = form.elements.namedItem('preferred_area') as HTMLInputElement | null;
    const wantsBreakfast = form.elements.namedItem('wants_breakfast') as HTMLInputElement | null;
    const feedback = document.querySelector<HTMLElement>('#booking-feedback');
    const estimate = document.querySelector<HTMLElement>('#booking-estimate');
    const submitButton = form.querySelector<HTMLButtonElement>('[data-booking-submit]');
    const submitButtonText = submitButton?.textContent?.trim() || 'Submit booking request';
    const roomField = form.querySelector<HTMLElement>('[data-booking-room-field]');
    const eventField = form.querySelector<HTMLElement>('[data-booking-event-field]');
    const breakfastField = form.querySelector<HTMLElement>('[data-booking-breakfast-field]');

    const bookingType = () => String(new FormData(form).get('booking_type') ?? 'personal');

    const syncBookingType = () => {
        const type = bookingType();
        const selectedProperty = propertySelect.selectedOptions[0];
        const breakfastOffered = selectedProperty?.dataset.breakfast === '1';

        roomSelect.required = type === 'personal';
        roomSelect.disabled = type === 'event';
        eventName.required = type === 'event';
        roomField?.classList.toggle('hidden', type === 'event');
        eventField?.classList.toggle('hidden', type !== 'event');
        breakfastField?.classList.toggle('hidden', type !== 'personal' || !breakfastOffered);

        form.querySelectorAll<HTMLInputElement>('input[name="booking_type"]').forEach((input) => {
            input.closest('label')?.classList.toggle('bg-white', input.checked);
            input.closest('label')?.classList.toggle('shadow-sm', input.checked);
        });

        if (type === 'event') {
            roomSelect.value = '';
            roomSelect.innerHTML = '<option value="">Room not required for event requests</option>';
            if (wantsBreakfast) wantsBreakfast.checked = false;
            estimate!.textContent = money.format(0);
            feedback!.textContent = 'Event requests are reviewed by staff before pricing is finalized.';
        } else {
            eventName.value = '';
            feedback!.textContent = '';
            if (!breakfastOffered && wantsBreakfast) wantsBreakfast.checked = false;
            if (roomSelect.options.length === 1 && roomSelect.options[0]?.textContent === 'Room not required for event requests') {
                roomSelect.innerHTML = '<option value="">Choose property and dates first</option>';
            }
        }
    };

    const syncAvailability = debounce(async () => {
        if (bookingType() === 'event') return;

        if (!propertySelect.value || !checkIn.value || !checkOut.value) {
            roomSelect.innerHTML = '<option value="">Choose property and dates first</option>';
            return;
        }

        try {
            const guests = Number(adults.value || 1) + Number(children.value || 0);
            const params = new URLSearchParams({
                property_id: propertySelect.value,
                check_in: checkIn.value,
                check_out: checkOut.value,
                guests: String(guests),
            });
            if (preferredArea?.value) params.set('preferred_area', preferredArea.value);
            const data = await request(`/api/public/rooms/availability?${params.toString()}`);
            roomSelect.innerHTML = data.data.length ? '<option value="">Select room</option>' : '<option value="">No rooms available</option>';
            data.data.forEach((room: any) => {
                const option = document.createElement('option');
                option.value = String(room.id);
                option.textContent = `${room.room_number} - ${room.type} - ${money.format(Number(room.rate))}`;
                roomSelect.appendChild(option);
            });
            feedback!.textContent = data.data.length ? `${data.data.length} room option(s) available.` : 'No matching rooms are available for those dates.';
        } catch {
            feedback!.textContent = 'Could not load availability. Please try again.';
        }
    });

    const syncEstimate = debounce(async () => {
        if (bookingType() === 'event') return;
        if (!roomSelect.value || !checkIn.value || !checkOut.value) return;

        try {
            const params = new URLSearchParams({
                room_id: roomSelect.value,
                check_in: checkIn.value,
                check_out: checkOut.value,
                room_count: roomCount?.value || '1',
                guests: String(Number(adults.value || 1) + Number(children.value || 0)),
            });
            if (wantsBreakfast?.checked) params.set('wants_breakfast', '1');
            form.querySelectorAll<HTMLInputElement>('input[name="addons[]"]:checked').forEach((input) => {
                params.append('addons[]', input.value);
            });
            const data = await request(`/api/public/booking/estimate?${params.toString()}`);
            estimate!.textContent = money.format(Number(data.estimated_total));
        } catch {
            estimate!.textContent = money.format(0);
        }
    });

    form.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea').forEach((field) => {
        field.addEventListener('blur', () => {
            if (field.required && !field.value) {
                setError(form, field.name, 'This field is required.');
            } else {
                setError(form, field.name);
            }
        });
        field.addEventListener('input', () => setError(form, field.name));
    });

    form.querySelectorAll<HTMLInputElement>('input[name="booking_type"]').forEach((input) => input.addEventListener('change', () => {
        syncBookingType();
        syncAvailability();
        syncEstimate();
    }));
    [propertySelect, checkIn, checkOut, adults, children, roomCount, preferredArea].filter(Boolean).forEach((field) => field.addEventListener('change', () => {
        syncBookingType();
        syncAvailability();
        syncEstimate();
    }));
    [roomSelect, checkIn, checkOut, roomCount, wantsBreakfast].filter(Boolean).forEach((field) => field.addEventListener('change', syncEstimate));
    form.querySelectorAll<HTMLInputElement>('input[name="addons[]"]').forEach((field) => field.addEventListener('change', syncEstimate));
    syncBookingType();

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors(form);
        feedback!.textContent = 'Submitting your request...';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';
        }

        try {
            const payload = formData(form);
            const data = await request('/api/public/bookings', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            feedback!.textContent = `Submitted. Reference number: ${data.reservation.reference_number}`;
            const statusForm = document.querySelector<HTMLFormElement>('#status-form');
            const statusReference = statusForm?.elements.namedItem('reference_number') as HTMLInputElement | null;
            const statusEmail = statusForm?.elements.namedItem('email') as HTMLInputElement | null;
            if (statusReference) statusReference.value = data.reservation.reference_number;
            if (statusEmail) statusEmail.value = String(payload.email ?? '');
            form.reset();
            roomSelect.innerHTML = '<option value="">Choose dates first</option>';
            estimate!.textContent = money.format(0);
            syncBookingType();
        } catch (error: any) {
            showErrors(form, error.errors);
            feedback!.textContent = error.message ?? 'Please review the highlighted fields.';
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = submitButtonText;
            }
        }
    });
}

function renderStatusResult(reservation: any): string {
    const property = reservation.property
        ? `${reservation.property.name}${reservation.property.city ? `, ${reservation.property.city}` : ''}`
        : reservation.room?.property ?? 'Property review pending';
    const request = reservation.booking_type === 'event'
        ? (reservation.event_name || 'Event or group request')
        : (reservation.room ? `Room ${reservation.room.room_number} - ${reservation.room.type}` : 'Room assignment pending');
    const payments = Array.isArray(reservation.payments) && reservation.payments.length
        ? reservation.payments.map((payment: any) => `
            <div class="flex justify-between gap-3 border-t border-black/10 pt-2">
                <span>${escapeHtml(payment.method)} - ${escapeHtml(human(payment.status))}</span>
                <span class="font-semibold tabular-nums">${money.format(Number(payment.amount))}</span>
            </div>
        `).join('')
        : '<p class="text-black/55">No payment records posted yet.</p>';

    return `
        <div class="grid gap-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.12em] text-black/55">Reference</p>
                    <p class="mt-1 text-lg font-bold text-ma-blue">${escapeHtml(reservation.reference_number)}</p>
                </div>
                <span class="rounded-md bg-ma-blue/10 px-3 py-1 text-xs font-bold capitalize text-ma-blue">${escapeHtml(human(reservation.status))}</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.12em] text-black/55">Request</p>
                    <p class="mt-1 font-semibold">${escapeHtml(request)}</p>
                    <p class="text-black/60">${escapeHtml(property)}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.12em] text-black/55">Stay dates</p>
                    <p class="mt-1 font-semibold">${escapeHtml(reservation.check_in)} to ${escapeHtml(reservation.check_out)}</p>
                    <p class="text-black/60">${escapeHtml(reservation.adults)} adult(s), ${escapeHtml(reservation.children)} child(ren)</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.12em] text-black/55">Payment</p>
                    <p class="mt-1 font-semibold capitalize">${escapeHtml(human(reservation.payment_status))}</p>
                    <p class="text-black/60">Estimated total: ${money.format(Number(reservation.estimated_total))}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[.12em] text-black/55">Guest</p>
                    <p class="mt-1 font-semibold">${escapeHtml(reservation.guest_name)}</p>
                </div>
            </div>
            <div class="rounded-md bg-white/70 p-3">
                <p class="text-xs font-semibold uppercase tracking-[.12em] text-black/55">Next step</p>
                <p class="mt-1">${escapeHtml(reservation.next_step)}</p>
            </div>
            <div class="grid gap-2">
                <p class="text-xs font-semibold uppercase tracking-[.12em] text-black/55">Payments</p>
                ${payments}
            </div>
        </div>
    `;
}

function initStatusForm(): void {
    const form = document.querySelector<HTMLFormElement>('#status-form');
    const result = document.querySelector<HTMLElement>('#status-result');
    const referenceInput = form?.elements.namedItem('reference_number') as HTMLInputElement | null;
    const submitButton = form?.querySelector<HTMLButtonElement>('[data-status-submit]');
    if (!form || !result) return;

    referenceInput?.addEventListener('input', () => {
        referenceInput.value = normalizeReferenceNumber(referenceInput.value);
        setError(form, 'reference_number');
    });

    form.querySelectorAll<HTMLInputElement>('input').forEach((field) => {
        field.addEventListener('input', () => setError(form, field.name));
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors(form);
        if (referenceInput) referenceInput.value = normalizeReferenceNumber(referenceInput.value);
        result.textContent = 'Looking up booking...';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Looking up...';
        }

        try {
            const data = await request('/api/public/booking/lookup', {
                method: 'POST',
                body: JSON.stringify(formData(form)),
            });
            result.innerHTML = renderStatusResult(data.reservation);
        } catch (error: any) {
            showErrors(form, error.errors);
            result.textContent = error.message ?? 'No booking matched those details.';
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Look up booking';
            }
        }
    });
}

function initReviewForm(): void {
    const form = document.querySelector<HTMLFormElement>('#review-form');
    const feedback = document.querySelector<HTMLElement>('#review-feedback');
    const referenceInput = form?.elements.namedItem('reference_number') as HTMLInputElement | null;
    const submitButton = form?.querySelector<HTMLButtonElement>('[data-review-submit]');
    if (!form || !feedback) return;

    referenceInput?.addEventListener('input', () => {
        referenceInput.value = normalizeReferenceNumber(referenceInput.value);
        setError(form, 'reference_number');
    });

    form.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea').forEach((field) => {
        field.addEventListener('input', () => setError(form, field.name));
        field.addEventListener('change', () => setError(form, field.name));
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors(form);
        if (referenceInput) referenceInput.value = normalizeReferenceNumber(referenceInput.value);
        feedback.textContent = 'Submitting review...';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';
        }

        try {
            const data = await request('/api/public/reviews', {
                method: 'POST',
                body: JSON.stringify(formData(form)),
            });
            feedback.textContent = data.message;
            form.reset();
            if (referenceInput) referenceInput.value = '';
        } catch (error: any) {
            showErrors(form, error.errors);
            feedback.textContent = error.message ?? 'Please check your review details.';
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = 'Submit review for moderation';
            }
        }
    });
}

function initMobileMenu(): void {
    const button = document.querySelector<HTMLButtonElement>('[data-mobile-menu-button]');
    const menu = document.querySelector<HTMLElement>('[data-mobile-menu]');
    if (!button || !menu) return;

    button.addEventListener('click', () => {
        const isOpen = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', String(!isOpen));
        menu.classList.toggle('hidden', isOpen);
    });

    menu.querySelectorAll<HTMLAnchorElement>('a').forEach((link) => {
        link.addEventListener('click', () => {
            button.setAttribute('aria-expanded', 'false');
            menu.classList.add('hidden');
        });
    });
}

function initStaticFeedbackForms(): void {
    document.querySelectorAll<HTMLFormElement>('[data-newsletter-form], [data-inquiry-form]').forEach((form) => {
        const feedback = form.querySelector<HTMLElement>('[data-newsletter-feedback], [data-inquiry-feedback]');
        const submitButton = form.querySelector<HTMLButtonElement>('[data-form-submit]');
        const submitLabel = submitButton?.textContent?.trim() ?? 'Submit';
        const endpoint = form.dataset.endpoint;

        form.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input, select, textarea').forEach((field) => {
            field.addEventListener('input', () => setError(form, field.name));
            field.addEventListener('change', () => setError(form, field.name));
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors(form);
            feedback?.removeAttribute('data-state');

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            if (!endpoint) return;

            if (feedback) feedback.textContent = form.matches('[data-newsletter-form]') ? 'Subscribing...' : 'Sending inquiry...';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = form.matches('[data-newsletter-form]') ? 'Subscribing...' : 'Sending...';
            }

            try {
                const data = await request(endpoint, {
                    method: 'POST',
                    body: JSON.stringify(formData(form)),
                });

                if (feedback) {
                    feedback.textContent = data.reference_number
                        ? `${data.message} Reference: ${data.reference_number}`
                        : data.message;
                    feedback.dataset.state = 'success';
                }
                form.reset();
            } catch (error: any) {
                showErrors(form, error.errors);
                if (feedback) {
                    feedback.textContent = error.message ?? 'We could not submit the form. Please review your details and try again.';
                    feedback.dataset.state = 'error';
                }
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = submitLabel;
                }
            }
        });
    });
}

function initGsapAnimations(): void {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion) return;

    gsap.registerPlugin(ScrollTrigger);

    document.querySelectorAll<HTMLElement>('[data-animate-hero]').forEach((hero) => {
        const children = Array.from(hero.children);
        gsap.from(children, {
            autoAlpha: 0,
            y: 28,
            duration: 0.7,
            stagger: 0.08,
            ease: 'power2.out',
        });
    });

    document.querySelectorAll<HTMLElement>('[data-gsap-reveal]').forEach((section) => {
        gsap.from(section, {
            y: 32,
            duration: 0.65,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: section,
                start: 'top 82%',
                once: true,
            },
        });
    });

    document.querySelectorAll<HTMLElement>('[data-gsap-stagger]').forEach((group) => {
        const items = Array.from(group.children);
        if (!items.length) return;

        gsap.from(items, {
            y: 22,
            duration: 0.55,
            stagger: 0.07,
            ease: 'power2.out',
            scrollTrigger: {
                trigger: group,
                start: 'top 84%',
                once: true,
            },
        });
    });

    document.querySelectorAll<HTMLElement>('[data-hero-image]').forEach((image) => {
        gsap.to(image, {
            scale: 1.06,
            yPercent: 4,
            ease: 'none',
            scrollTrigger: {
                trigger: image.closest('section') ?? image,
                start: 'top top',
                end: 'bottom top',
                scrub: true,
            },
        });
    });

    document.querySelectorAll<HTMLElement>('[data-gsap-float]').forEach((element, index) => {
        gsap.to(element, {
            y: index % 2 === 0 ? -10 : 10,
            duration: 2.8 + (index * 0.18),
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        });
    });

    document.querySelectorAll<HTMLElement>('[data-gsap-card]').forEach((card) => {
        gsap.fromTo(card,
            { y: 34, scale: 0.96 },
            {
                y: 0,
                scale: 1,
                ease: 'none',
                scrollTrigger: {
                    trigger: card,
                    start: 'top 92%',
                    end: 'top 58%',
                    scrub: 0.7,
                },
            });
    });

    window.addEventListener('load', () => ScrollTrigger.refresh(), { once: true });
}

function initPremiumHome(): void {
    const page = document.querySelector<HTMLElement>('[data-home-page]');
    if (!page) return;

    const search = page.querySelector<HTMLFormElement>('[data-home-search]');
    const checkIn = search?.elements.namedItem('check_in') as HTMLInputElement | null;
    const checkOut = search?.elements.namedItem('check_out') as HTMLInputElement | null;

    const syncCheckoutDate = () => {
        if (!checkIn || !checkOut || !checkIn.value) return;

        const nextDay = new Date(`${checkIn.value}T00:00:00`);
        nextDay.setDate(nextDay.getDate() + 1);
        const minimum = nextDay.toISOString().slice(0, 10);
        checkOut.min = minimum;

        if (!checkOut.value || checkOut.value < minimum) {
            checkOut.value = minimum;
        }
    };

    checkIn?.addEventListener('change', syncCheckoutDate);

    const rooms = Array.from(page.querySelectorAll<HTMLElement>('.home-room'));
    const activateRoom = (activeRoom: HTMLElement) => {
        rooms.forEach((room) => room.classList.toggle('is-active', room === activeRoom));
    };

    rooms.forEach((room) => {
        room.addEventListener('mouseenter', () => activateRoom(room));
        room.addEventListener('focusin', () => activateRoom(room));
    });

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    gsap.registerPlugin(ScrollTrigger);
    const media = gsap.matchMedia();
    const context = gsap.context(() => {
        const heroCopy = page.querySelector<HTMLElement>('[data-home-hero-copy]');
        const heroImage = page.querySelector<HTMLElement>('[data-home-hero-image]');
        const searchWrap = page.querySelector<HTMLElement>('[data-home-search-wrap]');

        if (heroCopy) {
            gsap.from(Array.from(heroCopy.children), {
                autoAlpha: 0,
                y: 34,
                duration: .85,
                stagger: .1,
                ease: 'power3.out',
                clearProps: 'opacity,visibility,transform',
            });
        }

        if (heroImage) {
            gsap.fromTo(heroImage,
                { scale: 1.02, yPercent: 0 },
                {
                    scale: 1.08,
                    yPercent: 5,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: page.querySelector('.home-hero'),
                        start: 'top top',
                        end: 'bottom top',
                        scrub: .8,
                    },
                });
        }

        if (searchWrap) {
            gsap.from(searchWrap, {
                autoAlpha: 0,
                y: 24,
                duration: .75,
                delay: .35,
                ease: 'power3.out',
                clearProps: 'opacity,visibility,transform',
            });
        }

        page.querySelectorAll<HTMLElement>('[data-home-reveal]').forEach((element) => {
            gsap.from(element, {
                autoAlpha: 0,
                y: 38,
                duration: .8,
                ease: 'power3.out',
                clearProps: 'opacity,visibility,transform',
                scrollTrigger: {
                    trigger: element,
                    start: 'top 84%',
                    once: true,
                },
            });
        });

        page.querySelectorAll<HTMLElement>('[data-home-stagger]').forEach((group) => {
            const children = Array.from(group.children);
            if (!children.length) return;

            gsap.from(children, {
                autoAlpha: 0,
                y: 30,
                duration: .7,
                stagger: .09,
                ease: 'power3.out',
                clearProps: 'opacity,visibility,transform',
                scrollTrigger: {
                    trigger: group,
                    start: 'top 84%',
                    once: true,
                },
            });
        });

        page.querySelectorAll<HTMLElement>('[data-home-parallax]').forEach((image) => {
            gsap.fromTo(image,
                { yPercent: -4 },
                {
                    yPercent: 4,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: image.parentElement ?? image,
                        start: 'top bottom',
                        end: 'bottom top',
                        scrub: .8,
                    },
                });
        });

        media.add('(min-width: 1024px)', () => {
            const story = page.querySelector<HTMLElement>('[data-home-story]');
            const storyMedia = page.querySelector<HTMLElement>('[data-home-story-media]');
            const steps = Array.from(page.querySelectorAll<HTMLElement>('[data-home-story-step]'));
            const images = Array.from(page.querySelectorAll<HTMLElement>('[data-home-story-image]'));
            if (!story || !storyMedia || !steps.length || !images.length) return;

            ScrollTrigger.create({
                trigger: story,
                start: 'top top',
                end: 'bottom bottom',
                pin: storyMedia,
                pinSpacing: false,
                anticipatePin: 1,
            });

            const showStoryImage = (index: number) => {
                images.forEach((image, imageIndex) => {
                    image.classList.toggle('is-active', imageIndex === index);
                    gsap.to(image, {
                        autoAlpha: imageIndex === index ? 1 : 0,
                        scale: imageIndex === index ? 1 : 1.025,
                        duration: .55,
                        ease: 'power2.out',
                        overwrite: true,
                    });
                });
            };

            steps.forEach((step, index) => {
                ScrollTrigger.create({
                    trigger: step,
                    start: 'top 56%',
                    end: 'bottom 44%',
                    onEnter: () => showStoryImage(index),
                    onEnterBack: () => showStoryImage(index),
                });
            });
        });
    }, page);

    const cleanUp = () => {
        media.revert();
        context.revert();
    };

    window.addEventListener('pagehide', cleanUp, { once: true });
    window.addEventListener('load', () => ScrollTrigger.refresh(), { once: true });
}

function initPremiumPublicPages(): void {
    const page = document.querySelector<HTMLElement>('.premium-page');
    if (!page || page.matches('[data-home-page]')) return;

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    gsap.registerPlugin(ScrollTrigger);
    const context = gsap.context(() => {
        const heroCopy = page.querySelector<HTMLElement>('[data-premium-hero-copy]');
        const heroImage = page.querySelector<HTMLElement>('[data-premium-hero-image]');

        if (heroCopy) {
            gsap.from(Array.from(heroCopy.children), {
                opacity: .78,
                y: 30,
                duration: .8,
                stagger: .09,
                ease: 'power3.out',
                clearProps: 'opacity,transform',
            });
        }

        if (heroImage) {
            gsap.fromTo(heroImage,
                { scale: 1.02 },
                {
                    scale: 1.07,
                    yPercent: 4,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: heroImage.closest('section'),
                        start: 'top top',
                        end: 'bottom top',
                        scrub: .8,
                    },
                });
        }

        page.querySelectorAll<HTMLElement>('[data-premium-reveal]').forEach((element) => {
            gsap.from(element, {
                opacity: .82,
                y: 34,
                duration: .75,
                ease: 'power3.out',
                clearProps: 'opacity,transform',
                immediateRender: false,
                scrollTrigger: {
                    trigger: element,
                    start: 'top 95%',
                    once: true,
                },
            });
        });

        page.querySelectorAll<HTMLElement>('[data-premium-stagger]').forEach((group) => {
            const children = Array.from(group.children);
            if (!children.length) return;

            gsap.from(children, {
                opacity: .82,
                y: 26,
                duration: .65,
                stagger: .08,
                ease: 'power3.out',
                clearProps: 'opacity,transform',
                immediateRender: false,
                scrollTrigger: {
                    trigger: group,
                    start: 'top 95%',
                    once: true,
                },
            });
        });

        page.querySelectorAll<HTMLElement>('[data-premium-parallax]').forEach((image) => {
            gsap.fromTo(image,
                { yPercent: -3 },
                {
                    yPercent: 3,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: image.parentElement ?? image,
                        start: 'top bottom',
                        end: 'bottom top',
                        scrub: .8,
                    },
                });
        });
    }, page);

    window.addEventListener('pagehide', () => context.revert(), { once: true });
    window.addEventListener('load', () => ScrollTrigger.refresh(), { once: true });
}

function initJournalSearch(): void {
    const form = document.querySelector<HTMLFormElement>('[data-journal-search]');
    if (!form) return;

    const input = form.elements.namedItem('search') as HTMLInputElement | null;
    const posts = Array.from(document.querySelectorAll<HTMLElement>('[data-journal-post]'));
    const feedback = form.querySelector<HTMLElement>('[data-journal-feedback]');
    const empty = document.querySelector<HTMLElement>('[data-journal-empty]');
    if (!input) return;

    const filterPosts = () => {
        const query = input.value.trim().toLocaleLowerCase();
        let visible = 0;

        posts.forEach((post) => {
            const matches = !query || (post.dataset.searchText ?? '').includes(query);
            post.hidden = !matches;
            if (matches) visible++;
        });

        empty?.classList.toggle('hidden', visible > 0);
        if (feedback) {
            feedback.textContent = query ? `${visible} article${visible === 1 ? '' : 's'} found.` : '';
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        filterPosts();
    });
    input.addEventListener('input', filterPosts);
}

function initHotelGallery(): void {
    const gallery = document.querySelector<HTMLElement>('[data-hotel-gallery]');
    const dialog = document.querySelector<HTMLDialogElement>('[data-hotel-gallery-dialog]');
    if (!gallery || !dialog) return;

    const items = Array.from(gallery.querySelectorAll<HTMLButtonElement>('[data-hotel-gallery-item]'));
    const image = dialog.querySelector<HTMLImageElement>('[data-gallery-image]');
    const closeButton = dialog.querySelector<HTMLButtonElement>('[data-gallery-close]');
    const previousButton = dialog.querySelector<HTMLButtonElement>('[data-gallery-previous]');
    const nextButton = dialog.querySelector<HTMLButtonElement>('[data-gallery-next]');
    if (!items.length || !image) return;

    let activeIndex = 0;
    let trigger: HTMLButtonElement | null = null;

    const showImage = (index: number) => {
        activeIndex = (index + items.length) % items.length;
        image.src = items[activeIndex].dataset.image ?? '';
        image.alt = items[activeIndex].dataset.alt ?? 'Property photo';
    };

    const open = (index: number, button: HTMLButtonElement) => {
        showImage(index);
        trigger = button;

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
            closeButton?.focus();
        } else {
            window.open(image.src, '_blank', 'noopener');
        }
    };

    items.forEach((item, index) => item.addEventListener('click', () => open(index, item)));
    closeButton?.addEventListener('click', () => dialog.close());
    previousButton?.addEventListener('click', () => showImage(activeIndex - 1));
    nextButton?.addEventListener('click', () => showImage(activeIndex + 1));
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => {
        image.src = '';
        trigger?.focus();
    });
}

function initAutoScroll(): void {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!document.body.matches('[data-auto-scroll="true"]') || reduceMotion) return;

    const sections = Array.from(document.querySelectorAll<HTMLElement>('main > section'));
    if (sections.length < 2) return;

    let timer: number | undefined;
    let stopped = false;

    const stop = () => {
        stopped = true;
        window.clearTimeout(timer);
    };

    timer = window.setTimeout(() => {
        if (stopped) return;

        const nextSection = sections.find((section) => section.getBoundingClientRect().top > 80) ?? sections[1];
        nextSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 4500);

    ['wheel', 'touchstart', 'mousedown', 'keydown', 'focusin'].forEach((eventName) => {
        window.addEventListener(eventName, stop, { once: true });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initStaticFeedbackForms();
    initBookingForm();
    initStatusForm();
    initReviewForm();
    initAutoScroll();
    initGsapAnimations();
    initPremiumHome();
    initPremiumPublicPages();
    initJournalSearch();
    initHotelGallery();
});
