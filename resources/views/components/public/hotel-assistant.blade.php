@guest
    @vite(['resources/css/hotel-assistant.css', 'resources/js/hotel-assistant.js'])

    <section class="hotel-assistant" data-hotel-assistant aria-label="Virtual hotel assistant">
        <script type="application/json" data-assistant-config>@json([...$assistant, 'logoUrl' => asset('MALogo.png')])</script>

        <button
            class="hotel-assistant__launcher"
            type="button"
            data-assistant-launcher
            aria-expanded="false"
            aria-controls="hotel-assistant-panel"
        >
            <span class="hotel-assistant__launcher-mark" aria-hidden="true">
                <img src="{{ asset('MALogo.png') }}" alt="" width="64" height="64">
            </span>
            <span class="hotel-assistant__launcher-copy">
                <strong>Need help?</strong>
                <small>Ask our hotel assistant</small>
            </span>
        </button>

        <div class="hotel-assistant__panel" id="hotel-assistant-panel" data-assistant-panel hidden>
            <header class="hotel-assistant__header">
                <div class="hotel-assistant__identity">
                    <span class="hotel-assistant__avatar" aria-hidden="true">
                        <img src="{{ asset('MALogo.png') }}" alt="" width="64" height="64">
                    </span>
                    <div>
                        <h2>M.A Hotel Assistant</h2>
                        <p><span aria-hidden="true"></span> Instant guest support</p>
                    </div>
                </div>
                <button class="hotel-assistant__close" type="button" data-assistant-close aria-label="Minimize assistant" title="Minimize assistant">&minus;</button>
            </header>

            <div class="hotel-assistant__messages" data-assistant-messages role="log" aria-live="polite" aria-relevant="additions"></div>

            <div class="hotel-assistant__suggestions" aria-label="Frequently asked questions">
                <p>Suggested questions</p>
                <div data-assistant-suggestions></div>
            </div>

            <nav class="hotel-assistant__actions" data-assistant-actions aria-label="Quick actions"></nav>

            <form class="hotel-assistant__composer" data-assistant-form>
                <label>
                    <span class="hotel-assistant__sr-only">Ask a hotel question</span>
                    <input data-assistant-input type="text" maxlength="240" placeholder="Type your question" autocomplete="off">
                </label>
                <button type="submit">Send</button>
            </form>

            <a class="hotel-assistant__staff-link" data-assistant-staff-link>Talk to Our Staff</a>
        </div>
    </section>
@endguest
