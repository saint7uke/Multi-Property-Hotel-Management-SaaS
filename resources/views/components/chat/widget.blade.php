@if(auth()->check() && auth()->user()->status === 'active' && (auth()->user()->can('chat.global') || auth()->user()->can('chat.property')))
    @php
        $chatManifestPath = public_path('build/manifest.json');
        $chatManifest = file_exists($chatManifestPath)
            ? json_decode((string) file_get_contents($chatManifestPath), true)
            : [];
        $chatCssAsset = $chatManifest['resources/css/chat-widget.css']['file'] ?? null;
        $chatJsAsset = $chatManifest['resources/js/chat-widget.js']['file'] ?? null;
    @endphp

    @if($chatCssAsset)
        <link rel="stylesheet" href="{{ asset('build/'.$chatCssAsset) }}">
    @endif

    <style>
        .ma-chat{--chat-navy:#16233f;--chat-gold:#d4a24c;--chat-border:#dfe4eb;--chat-text:#1b2432;--chat-muted:#667085;position:fixed;right:1.25rem;bottom:1.25rem;z-index:10000;color:var(--chat-text);font-family:Poppins,Helvetica,Arial,sans-serif;letter-spacing:0}
        .ma-chat *,.ma-chat *:before,.ma-chat *:after{box-sizing:border-box}
        .ma-chat [hidden]{display:none!important}
        .ma-chat button,.ma-chat input,.ma-chat textarea{font:inherit}
        .ma-chat__launcher{position:relative;display:flex;min-height:3.25rem;align-items:center;gap:.6rem;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:var(--chat-navy);padding:.55rem 1rem .55rem .6rem;color:#fff;box-shadow:0 12px 30px rgba(16,26,46,.24);cursor:pointer}
        .ma-chat__launcher-icon{display:block;width:2.15rem;height:2.15rem;flex:0 0 2.15rem;overflow:hidden;border:1px solid rgba(212,162,76,.85);border-radius:50%;background:#fff;padding:.08rem}
        .ma-chat__launcher-icon img{display:block;width:100%;height:100%;border-radius:50%;object-fit:contain}
        .ma-chat__launcher-label{white-space:nowrap;font-size:.82rem;font-weight:600}
        .ma-chat__launcher-badge,.ma-chat__conversation-badge{display:grid;min-width:1.25rem;height:1.25rem;place-items:center;border-radius:999px;background:#c63f4a;padding:0 .3rem;color:#fff;font-size:.68rem;font-weight:700}
        .ma-chat__launcher-badge{position:absolute;top:-.35rem;right:-.2rem}
        .ma-chat__panel{width:min(46rem,calc(100vw - 2.5rem));height:min(39rem,calc(100dvh - 6.5rem));overflow:hidden;border:1px solid var(--chat-border);border-radius:.5rem;background:#fff;box-shadow:0 22px 60px rgba(16,26,46,.25)}
        .ma-chat__header{display:flex;height:4.25rem;align-items:center;justify-content:space-between;background:var(--chat-navy);padding:.75rem 1rem;color:#fff}
        .ma-chat__eyebrow{margin:0 0 .15rem;color:var(--chat-gold);font-size:.66rem;font-weight:700;text-transform:uppercase}
        .ma-chat__title{margin:0;color:#fff;font-size:1rem;font-weight:600;line-height:1.2}
        .ma-chat__header-actions{display:flex;align-items:center;gap:.65rem}
        .ma-chat__connection{color:rgba(255,255,255,.72);font-size:.7rem}
        .ma-chat__icon-button{display:grid;width:2rem;height:2rem;place-items:center;border:1px solid rgba(255,255,255,.2);border-radius:.4rem;background:transparent;color:#fff;font-size:1.25rem;cursor:pointer}
        .ma-chat__body{display:grid;height:calc(100% - 4.25rem);grid-template-columns:15.5rem minmax(0,1fr)}
        .ma-chat__sidebar{min-width:0;border-right:1px solid var(--chat-border);background:#fafbfc;padding:.85rem}
        .ma-chat__search input{width:100%;height:2.5rem;border:1px solid var(--chat-border);border-radius:.4rem;background:#fff;padding:0 .75rem;color:var(--chat-text);font-size:.78rem;outline:none}
        .ma-chat__conversation-list{display:grid;gap:.35rem;margin-top:.75rem}
        .ma-chat__conversation-button{display:grid;width:100%;grid-template-columns:2.25rem minmax(0,1fr) auto;align-items:center;gap:.65rem;border:1px solid transparent;border-radius:.45rem;background:transparent;padding:.65rem;text-align:left;cursor:pointer}
        .ma-chat__conversation-mark,.ma-chat__scope-mark,.ma-chat__avatar{display:grid;place-items:center;border-radius:50%;background:var(--chat-navy);color:#fff;font-size:.72rem;font-weight:700}
        .ma-chat__conversation-mark,.ma-chat__scope-mark{width:2.25rem;height:2.25rem}
        .ma-chat__conversation-copy{min-width:0}
        .ma-chat__conversation-copy strong,.ma-chat__conversation-copy span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .ma-chat__conversation-copy strong{font-size:.76rem;font-weight:600}
        .ma-chat__conversation-copy span{margin-top:.15rem;color:var(--chat-muted);font-size:.67rem}
        .ma-chat__conversation{display:grid;min-width:0;min-height:0;grid-template-rows:auto minmax(0,1fr) auto auto auto}
        .ma-chat__conversation-header{display:flex;min-height:4rem;align-items:center;gap:.7rem;border-bottom:1px solid var(--chat-border);padding:.65rem 1rem}
        .ma-chat__scope-mark{background:#e7edf5;color:var(--chat-navy)}
        .ma-chat__conversation-heading h3{margin:0;font-size:.85rem;font-weight:600}
        .ma-chat__conversation-heading p{margin:.12rem 0 0;color:var(--chat-muted);font-size:.68rem}
        .ma-chat__messages{min-height:0;overflow-y:auto;padding:1rem}
        .ma-chat__message{display:flex;align-items:flex-end;gap:.5rem;margin-bottom:.8rem}
        .ma-chat__message.is-mine{justify-content:flex-end}
        .ma-chat__message-column{max-width:78%}
        .ma-chat__bubble{border-radius:.45rem;background:#edf1f6;padding:.55rem .7rem;color:var(--chat-text);font-size:.76rem;line-height:1.45;overflow-wrap:anywhere}
        .ma-chat__message.is-mine .ma-chat__bubble{background:var(--chat-navy);color:#fff}
        .ma-chat__meta{display:flex;justify-content:flex-end;gap:.35rem;margin-top:.2rem;color:var(--chat-muted);font-size:.6rem}
        .ma-chat__empty{display:grid;height:100%;min-height:10rem;place-content:center;gap:.3rem;padding:1.5rem;color:var(--chat-muted);text-align:center}
        .ma-chat__empty strong{color:var(--chat-text);font-size:.82rem}
        .ma-chat__empty span{font-size:.72rem}
        .ma-chat__typing{min-height:1.35rem;padding:.15rem 1rem;color:var(--chat-muted);font-size:.67rem}
        .ma-chat__error{margin:0 .75rem .5rem;border-radius:.35rem;background:#fff0f1;padding:.5rem .65rem;color:#9d2630;font-size:.68rem}
        .ma-chat__composer{border-top:1px solid var(--chat-border);padding:.65rem}
        .ma-chat__composer-row{display:flex;align-items:flex-end;gap:.45rem}
        .ma-chat__message-input{flex:1;min-width:0}
        .ma-chat__message-input textarea{display:block;width:100%;max-height:6rem;resize:none;border:1px solid var(--chat-border);border-radius:.4rem;background:#fff;padding:.58rem .65rem;color:var(--chat-text);font-size:.74rem;line-height:1.4;outline:none}
        .ma-chat__attach-button,.ma-chat__send-button{display:grid;min-height:2.35rem;place-items:center;border-radius:.4rem;padding:0 .65rem;font-size:.68rem;font-weight:600}
        .ma-chat__attach-button{border:1px solid var(--chat-border);background:#fff;color:var(--chat-navy);cursor:pointer}
        .ma-chat__send-button{border:1px solid var(--chat-navy);background:var(--chat-navy);color:#fff}
        .ma-chat__sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}
        @media (max-width:640px){.ma-chat{right:max(.75rem,env(safe-area-inset-right));bottom:max(.75rem,env(safe-area-inset-bottom));max-width:calc(100dvw - 1.5rem)}.ma-chat__panel{position:fixed;right:0;bottom:0;left:0;width:100dvw;max-width:100dvw;height:min(88dvh,42rem);padding-bottom:env(safe-area-inset-bottom);border-right:0;border-bottom:0;border-left:0;border-radius:.5rem .5rem 0 0}.ma-chat__body{grid-template-columns:1fr;grid-template-rows:auto minmax(0,1fr)}.ma-chat__sidebar{border-right:0;border-bottom:1px solid var(--chat-border);padding:.6rem}.ma-chat__search{display:none}.ma-chat__conversation-list{display:flex;gap:.45rem;margin-top:0;overflow-x:auto}.ma-chat__conversation-button{min-width:11rem;padding:.45rem}.ma-chat__launcher-label{display:none}.ma-chat__launcher{padding-right:.55rem}.ma-chat__message-input textarea{font-size:1rem}}
        @media (max-width:380px){.ma-chat__composer-row{display:grid;grid-template-columns:auto minmax(0,1fr)}.ma-chat__send-button{grid-column:1/-1;min-height:2.75rem}}
    </style>

    <section
        class="ma-chat"
        data-chat-widget
        data-responsive-widget
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

    <script>
        (() => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            const chatRequest = async (url, options = {}) => {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                    },
                    ...options,
                });

                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(payload.message || 'Chat is temporarily unavailable.');
                }

                return payload;
            };

            const text = (value) => document.createTextNode(String(value ?? ''));

            const bootFallback = () => {
                document.querySelectorAll('[data-chat-widget]:not([data-chat-ready]):not([data-chat-fallback-ready])').forEach((root) => {
                    const launcher = root.querySelector('[data-chat-launcher]');
                    const panel = root.querySelector('[data-chat-panel]');
                    const close = root.querySelector('[data-chat-close]');
                    const connection = root.querySelector('[data-chat-connection]');
                    const conversationList = root.querySelector('[data-chat-conversations]');
                    const messages = root.querySelector('[data-chat-messages]');
                    const title = root.querySelector('[data-chat-title]');
                    const status = root.querySelector('[data-chat-status]');
                    const scopeMark = root.querySelector('[data-chat-scope-mark]');
                    const form = root.querySelector('[data-chat-form]');
                    const input = root.querySelector('[data-chat-input]');
                    const send = root.querySelector('[data-chat-send]');
                    const error = root.querySelector('[data-chat-error]');
                    const attachment = root.querySelector('[data-chat-attachment]');

                    if (!launcher || !panel || !conversationList || !messages || !form || !input || !send) return;

                    root.dataset.chatFallbackReady = 'true';
                    if (connection) connection.textContent = 'Basic mode';

                    let conversations = [];
                    let selectedId = null;
                    let pollTimer = null;

                    const showError = (message) => {
                        if (!error) return;
                        error.textContent = message;
                        error.hidden = false;
                    };

                    const clearError = () => {
                        if (!error) return;
                        error.textContent = '';
                        error.hidden = true;
                    };

                    const setOpen = (open) => {
                        panel.hidden = !open;
                        launcher.hidden = open;
                        launcher.setAttribute('aria-expanded', String(open));
                        if (open) refresh(true);
                    };

                    const renderConversations = () => {
                        conversationList.replaceChildren();

                        if (!conversations.length) {
                            const empty = document.createElement('div');
                            empty.className = 'ma-chat__empty';
                            empty.append(text('No chat channels are available.'));
                            conversationList.append(empty);
                            input.disabled = true;
                            send.disabled = true;
                            return;
                        }

                        conversations.forEach((conversation) => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'ma-chat__conversation-button';
                            if (conversation.id === selectedId) button.classList.add('is-active');

                            const mark = document.createElement('span');
                            mark.className = 'ma-chat__conversation-mark';
                            mark.append(text(conversation.scope === 'global' ? 'G' : 'P'));

                            const copy = document.createElement('span');
                            copy.className = 'ma-chat__conversation-copy';
                            const strong = document.createElement('strong');
                            strong.append(text(conversation.name));
                            const small = document.createElement('span');
                            small.append(text(conversation.description || 'Team conversation'));
                            copy.append(strong, small);

                            button.append(mark, copy);
                            button.addEventListener('click', () => selectConversation(conversation.id));
                            conversationList.append(button);
                        });
                    };

                    const renderMessages = (items = []) => {
                        messages.replaceChildren();

                        if (!items.length) {
                            const empty = document.createElement('div');
                            empty.className = 'ma-chat__empty';
                            const strong = document.createElement('strong');
                            strong.append(text('No messages yet.'));
                            const span = document.createElement('span');
                            span.append(text('Start the conversation with your team.'));
                            empty.append(strong, span);
                            messages.append(empty);
                            return;
                        }

                        items.forEach((message) => {
                            const row = document.createElement('article');
                            row.className = 'ma-chat__message';
                            if (Number(message.user?.id) === Number(root.dataset.userId)) row.classList.add('is-mine');

                            const column = document.createElement('div');
                            column.className = 'ma-chat__message-column';

                            const bubble = document.createElement('div');
                            bubble.className = 'ma-chat__bubble';
                            bubble.append(text(message.body || 'Attachment'));

                            const meta = document.createElement('div');
                            meta.className = 'ma-chat__meta';
                            meta.append(text(message.created_at ? new Date(message.created_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : 'Sent'));

                            column.append(bubble, meta);
                            row.append(column);
                            messages.append(row);
                        });

                        messages.scrollTop = messages.scrollHeight;
                    };

                    const syncHeader = () => {
                        const conversation = conversations.find((item) => item.id === selectedId);
                        if (!conversation) return;

                        if (title) title.textContent = conversation.name;
                        if (scopeMark) scopeMark.textContent = conversation.scope === 'global' ? 'G' : 'P';
                        if (status) status.textContent = conversation.scope === 'global' ? 'Global team channel' : 'Property team channel';
                    };

                    const selectConversation = async (id) => {
                        selectedId = id;
                        input.disabled = false;
                        send.disabled = false;
                        renderConversations();
                        syncHeader();

                        try {
                            const payload = await chatRequest(`/api/chat/conversations/${id}/messages`);
                            renderMessages(payload.messages || []);
                            clearError();
                        } catch (failure) {
                            showError(failure.message);
                        }
                    };

                    const refresh = async (includeMessages = false) => {
                        window.clearTimeout(pollTimer);

                        try {
                            const payload = await chatRequest('/api/chat/conversations');
                            conversations = payload.conversations || [];
                            selectedId = selectedId && conversations.some((item) => item.id === selectedId)
                                ? selectedId
                                : conversations[0]?.id || null;

                            renderConversations();
                            syncHeader();
                            clearError();

                            if (selectedId && includeMessages) {
                                await selectConversation(selectedId);
                            }
                        } catch (failure) {
                            input.disabled = true;
                            send.disabled = true;
                            showError(failure.message);
                        } finally {
                            pollTimer = window.setTimeout(() => refresh(panel.hidden === false), panel.hidden ? 12000 : 5000);
                        }
                    };

                    form.addEventListener('submit', async (event) => {
                        event.preventDefault();
                        const body = input.value.trim();
                        const file = attachment?.files?.[0];

                        if (!selectedId || (!body && !file)) return;

                        const data = new FormData();
                        if (body) data.append('body', body);
                        if (file) data.append('attachment', file);

                        send.disabled = true;

                        try {
                            await chatRequest(`/api/chat/conversations/${selectedId}/messages`, {
                                method: 'POST',
                                body: data,
                            });
                            input.value = '';
                            if (attachment) attachment.value = '';
                            await selectConversation(selectedId);
                            clearError();
                        } catch (failure) {
                            showError(failure.message);
                        } finally {
                            send.disabled = false;
                            input.disabled = false;
                        }
                    });

                    launcher.addEventListener('click', () => setOpen(true));
                    close?.addEventListener('click', () => setOpen(false));
                    refresh(false);
                });
            };

            window.setTimeout(bootFallback, 900);
            document.addEventListener('livewire:navigated', () => window.setTimeout(bootFallback, 900));
        })();
    </script>

    @if($chatJsAsset)
        <script type="module" src="{{ asset('build/'.$chatJsAsset) }}"></script>
    @endif
@endif
