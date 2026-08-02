@if(auth()->check() && auth()->user()->status === 'active' && (auth()->user()->can('chat.global') || auth()->user()->can('chat.property')))
    @vite(['resources/css/chat-widget.css', 'resources/js/chat-widget.js'])

    <section
        class="ma-chat"
        data-chat-widget
        data-user-id="{{ auth()->id() }}"
        data-user-name="{{ auth()->user()->name }}"
        aria-label="Staff chat"
    >
        <button class="ma-chat__launcher" type="button" data-chat-launcher aria-expanded="false" aria-controls="ma-chat-panel">
            <span class="ma-chat__launcher-icon" aria-hidden="true">
                <img src="{{ asset('MALogo.png') }}" alt="" width="64" height="64" decoding="async">
            </span>
            <span class="ma-chat__launcher-label">Team chat</span>
            <span class="ma-chat__launcher-badge" data-chat-total-unread hidden>0</span>
        </button>

        <div class="ma-chat__panel" id="ma-chat-panel" data-chat-panel hidden>
            <header class="ma-chat__header">
                <div>
                    <p class="ma-chat__eyebrow">M.A Hotels</p>
                    <h2 class="ma-chat__title">Team chat</h2>
                </div>
                <div class="ma-chat__header-actions">
                    <span class="ma-chat__connection" data-chat-connection>Connecting</span>
                    <button class="ma-chat__icon-button" type="button" data-chat-close aria-label="Minimize chat" title="Minimize chat">&minus;</button>
                </div>
            </header>

            <div class="ma-chat__body">
                <aside class="ma-chat__sidebar" aria-label="Conversations">
                    <label class="ma-chat__search">
                        <span class="ma-chat__sr-only">Search conversations</span>
                        <input type="search" data-chat-search placeholder="Search conversations" autocomplete="off">
                    </label>
                    <div class="ma-chat__conversation-list" data-chat-conversations>
                        <div class="ma-chat__skeleton" aria-label="Loading conversations">
                            <span></span><span></span><span></span>
                        </div>
                    </div>
                </aside>

                <section class="ma-chat__conversation" aria-label="Selected conversation">
                    <header class="ma-chat__conversation-header">
                        <div class="ma-chat__scope-mark" data-chat-scope-mark aria-hidden="true">G</div>
                        <div class="ma-chat__conversation-heading">
                            <h3 data-chat-title>Select a conversation</h3>
                            <p data-chat-status>Property and global channels</p>
                        </div>
                    </header>

                    <div class="ma-chat__messages" data-chat-messages role="log" aria-live="polite" aria-relevant="additions">
                        <div class="ma-chat__empty">
                            <strong>Your team conversations appear here.</strong>
                            <span>Choose a channel to begin.</span>
                        </div>
                    </div>

                    <div class="ma-chat__typing" data-chat-typing aria-live="polite"></div>
                    <div class="ma-chat__error" data-chat-error role="alert" hidden></div>

                    <form class="ma-chat__composer" data-chat-form>
                        <div class="ma-chat__attachment-preview" data-chat-attachment-preview hidden>
                            <span data-chat-attachment-name></span>
                            <button type="button" data-chat-remove-attachment aria-label="Remove attachment">Remove</button>
                        </div>
                        <div class="ma-chat__composer-row">
                            <label class="ma-chat__attach-button" title="Attach a file">
                                <span>Attach</span>
                                <input
                                    class="ma-chat__sr-only"
                                    type="file"
                                    data-chat-attachment
                                    accept="image/jpeg,image/png,image/webp,application/pdf,.doc,.docx,text/plain"
                                >
                            </label>
                            <label class="ma-chat__message-input">
                                <span class="ma-chat__sr-only">Message</span>
                                <textarea data-chat-input rows="1" maxlength="4000" placeholder="Write a message" disabled></textarea>
                            </label>
                            <button class="ma-chat__send-button" type="submit" disabled data-chat-send>Send</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </section>
@endif
