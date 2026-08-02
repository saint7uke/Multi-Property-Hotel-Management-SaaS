import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

const echo = import.meta.env.VITE_REVERB_APP_KEY
    ? new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: { headers: { 'X-CSRF-TOKEN': csrfToken } },
    })
    : null;

const request = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            ...(options.headers || {}),
        },
        ...options,
    });

    if (!response.ok) {
        const payload = await response.json().catch(() => ({}));
        const validationMessage = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(validationMessage || payload.message || 'Chat could not complete that request.');
    }

    return response.json();
};

const formatTime = (value) => new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
}).format(new Date(value));

const truncate = (value, length = 36) => value && value.length > length ? `${value.slice(0, length - 1)}...` : value;

class ChatWidget {
    constructor(root) {
        this.root = root;
        this.userId = Number(root.dataset.userId);
        this.conversations = [];
        this.selectedId = Number(localStorage.getItem('ma-chat-conversation')) || null;
        this.messages = [];
        this.subscribed = new Set();
        this.isOpen = new URLSearchParams(window.location.search).get('chat') === 'open'
            || localStorage.getItem('ma-chat-open') === 'true';
        this.lastTypingSignal = 0;
        this.typingTimer = null;
        this.pollTimer = null;
        this.lastMarkedRead = 0;
        this.refs = this.collectRefs();
        this.bind();
        this.setOpen(this.isOpen, false);
        this.connectStatus();
        this.refresh(false);
        this.heartbeat();
    }

    collectRefs() {
        const get = (selector) => this.root.querySelector(selector);
        return {
            launcher: get('[data-chat-launcher]'),
            panel: get('[data-chat-panel]'),
            close: get('[data-chat-close]'),
            connection: get('[data-chat-connection]'),
            totalUnread: get('[data-chat-total-unread]'),
            search: get('[data-chat-search]'),
            conversations: get('[data-chat-conversations]'),
            title: get('[data-chat-title]'),
            status: get('[data-chat-status]'),
            scopeMark: get('[data-chat-scope-mark]'),
            messages: get('[data-chat-messages]'),
            typing: get('[data-chat-typing]'),
            error: get('[data-chat-error]'),
            form: get('[data-chat-form]'),
            input: get('[data-chat-input]'),
            send: get('[data-chat-send]'),
            attachment: get('[data-chat-attachment]'),
            attachmentPreview: get('[data-chat-attachment-preview]'),
            attachmentName: get('[data-chat-attachment-name]'),
            removeAttachment: get('[data-chat-remove-attachment]'),
        };
    }

    bind() {
        this.refs.launcher.addEventListener('click', () => this.setOpen(true));
        this.refs.close.addEventListener('click', () => this.setOpen(false));
        this.refs.search.addEventListener('input', () => this.renderConversations());
        this.refs.form.addEventListener('submit', (event) => this.send(event));
        this.refs.input.addEventListener('input', () => this.onInput());
        this.refs.input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                this.refs.form.requestSubmit();
            }
        });
        this.refs.attachment.addEventListener('change', () => this.showAttachment());
        this.refs.removeAttachment.addEventListener('click', () => this.clearAttachment());
        window.addEventListener('online', () => this.refresh(true));
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.refresh(true);
        });
    }

    connectStatus() {
        if (!echo?.connector?.pusher?.connection) {
            this.setConnection(false, 'Live fallback');
            return;
        }

        const connection = echo.connector.pusher.connection;
        connection.bind('connected', () => this.setConnection(true, 'Live'));
        connection.bind('disconnected', () => this.setConnection(false, 'Reconnecting'));
        connection.bind('unavailable', () => this.setConnection(false, 'Live fallback'));
    }

    setConnection(live, label) {
        this.refs.connection.textContent = label;
        this.refs.connection.classList.toggle('is-live', live);
    }

    setOpen(open, focus = true) {
        this.isOpen = open;
        this.refs.panel.hidden = !open;
        this.refs.launcher.hidden = open;
        this.refs.launcher.setAttribute('aria-expanded', String(open));
        localStorage.setItem('ma-chat-open', String(open));

        if (open) {
            this.refresh(true);
            if (focus) setTimeout(() => this.refs.input.focus(), 80);
        }
    }

    async refresh(includeMessages = true) {
        try {
            const payload = await request('/api/chat/conversations');
            this.conversations = payload.conversations || [];
            this.ensureSelection();
            this.renderConversations();
            this.renderHeader();
            this.updateUnread();
            this.subscribe();
            this.clearError();

            if (includeMessages && this.selectedId && this.isOpen) {
                await this.loadMessages();
            }
        } catch (error) {
            this.showError(error.message);
            this.setConnection(false, navigator.onLine ? 'Live fallback' : 'Offline');
        } finally {
            this.schedulePoll();
        }
    }

    ensureSelection() {
        if (!this.conversations.some((conversation) => conversation.id === this.selectedId)) {
            this.selectedId = this.conversations[0]?.id ?? null;
        }

        if (this.selectedId) localStorage.setItem('ma-chat-conversation', String(this.selectedId));
        this.refs.input.disabled = !this.selectedId;
        this.refs.send.disabled = !this.selectedId;
    }

    schedulePoll() {
        clearTimeout(this.pollTimer);
        const delay = this.isOpen ? 4000 : 12000;
        this.pollTimer = setTimeout(() => this.refresh(this.isOpen), delay);
    }

    subscribe() {
        if (!echo) return;

        this.conversations.forEach((conversation) => {
            if (this.subscribed.has(conversation.id)) return;

            echo.private(`chat.conversation.${conversation.id}`)
                .listen('.chat.message', (event) => {
                    const message = this.normalizeMessage(event.message);
                    if (conversation.id === this.selectedId && this.isOpen) {
                        if (!this.messages.some((item) => item.id === message.id)) {
                            this.messages.push(message);
                            this.renderMessages();
                            this.markRead();
                        }
                    }
                    this.refresh(false);
                })
                .listen('.chat.state', () => this.refresh(this.isOpen));

            this.subscribed.add(conversation.id);
        });
    }

    async selectConversation(id) {
        if (id === this.selectedId && this.messages.length) return;
        this.selectedId = id;
        this.messages = [];
        this.lastMarkedRead = 0;
        localStorage.setItem('ma-chat-conversation', String(id));
        this.renderConversations();
        this.renderHeader();
        this.renderMessages(true);
        await this.loadMessages();
        this.refs.input.focus();
    }

    renderConversations() {
        const query = this.refs.search.value.trim().toLowerCase();
        const filtered = this.conversations.filter((conversation) => conversation.name.toLowerCase().includes(query));
        this.refs.conversations.replaceChildren();

        if (!filtered.length) {
            const empty = document.createElement('div');
            empty.className = 'ma-chat__empty';
            empty.textContent = query ? 'No conversations match your search.' : 'No chat channels are available.';
            this.refs.conversations.append(empty);
            return;
        }

        filtered.forEach((conversation) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `ma-chat__conversation-button${conversation.id === this.selectedId ? ' is-active' : ''}`;
            button.setAttribute('aria-current', conversation.id === this.selectedId ? 'true' : 'false');
            button.addEventListener('click', () => this.selectConversation(conversation.id));

            const mark = document.createElement('span');
            mark.className = 'ma-chat__conversation-mark';
            mark.textContent = conversation.scope === 'global' ? 'G' : 'P';

            const copy = document.createElement('span');
            copy.className = 'ma-chat__conversation-copy';
            const name = document.createElement('strong');
            name.textContent = conversation.name;
            const preview = document.createElement('span');
            preview.textContent = conversation.last_message
                ? `${conversation.last_message.sender}: ${truncate(conversation.last_message.body || 'Attachment')}`
                : conversation.description;
            copy.append(name, preview);
            button.append(mark, copy);

            if (conversation.unread_count > 0) {
                const badge = document.createElement('span');
                badge.className = 'ma-chat__conversation-badge';
                badge.textContent = conversation.unread_count > 99 ? '99+' : String(conversation.unread_count);
                button.append(badge);
            }

            this.refs.conversations.append(button);
        });
    }

    renderHeader() {
        const conversation = this.currentConversation();
        if (!conversation) return;
        this.refs.title.textContent = conversation.name;
        this.refs.scopeMark.textContent = conversation.scope === 'global' ? 'G' : 'P';
        const online = `${conversation.online_count} online`;
        this.refs.status.textContent = conversation.scope === 'global' ? `${online} across all properties` : `${online} at this property`;
        const typingNames = conversation.typing.map((user) => user.name);
        this.refs.typing.textContent = typingNames.length
            ? `${typingNames.slice(0, 2).join(' and ')} ${typingNames.length === 1 ? 'is' : 'are'} typing...`
            : '';
    }

    updateUnread() {
        const total = this.conversations.reduce((sum, conversation) => sum + conversation.unread_count, 0);
        this.refs.totalUnread.hidden = total === 0;
        this.refs.totalUnread.textContent = total > 99 ? '99+' : String(total);
        document.title = total > 0 && !document.title.startsWith('(') ? `(${total}) ${document.title}` : document.title.replace(/^\(\d+\)\s/, '');
    }

    async loadMessages() {
        if (!this.selectedId) return;
        const payload = await request(`/api/chat/conversations/${this.selectedId}/messages`);
        this.messages = (payload.messages || []).map((message) => this.normalizeMessage(message));
        this.renderMessages();
        await this.markRead();
    }

    normalizeMessage(message) {
        return { ...message, is_mine: Number(message.user.id) === this.userId };
    }

    renderMessages(loading = false) {
        this.refs.messages.replaceChildren();

        if (loading) {
            const skeleton = document.createElement('div');
            skeleton.className = 'ma-chat__skeleton';
            skeleton.setAttribute('aria-label', 'Loading messages');
            skeleton.append(document.createElement('span'), document.createElement('span'));
            this.refs.messages.append(skeleton);
            return;
        }

        if (!this.messages.length) {
            const empty = document.createElement('div');
            empty.className = 'ma-chat__empty';
            const strong = document.createElement('strong');
            strong.textContent = 'No messages yet.';
            const detail = document.createElement('span');
            detail.textContent = 'Start the conversation with your team.';
            empty.append(strong, detail);
            this.refs.messages.append(empty);
            return;
        }

        this.messages.forEach((message) => this.refs.messages.append(this.messageNode(message)));
        this.refs.messages.scrollTop = this.refs.messages.scrollHeight;
    }

    messageNode(message) {
        const row = document.createElement('article');
        row.className = `ma-chat__message${message.is_mine ? ' is-mine' : ''}`;

        if (!message.is_mine) {
            const avatar = document.createElement('span');
            avatar.className = `ma-chat__avatar${message.user.online ? ' is-online' : ''}`;
            avatar.textContent = message.user.initials;
            avatar.title = `${message.user.name}${message.user.online ? ' (online)' : ' (offline)'}`;
            row.append(avatar);
        }

        const column = document.createElement('div');
        column.className = 'ma-chat__message-column';
        if (!message.is_mine) {
            const sender = document.createElement('p');
            sender.className = 'ma-chat__sender';
            sender.textContent = message.user.name;
            column.append(sender);
        }

        const bubble = document.createElement('div');
        bubble.className = 'ma-chat__bubble';
        if (message.body) {
            const body = document.createElement('span');
            body.textContent = message.body;
            bubble.append(body);
        }

        if (message.attachment) {
            const link = document.createElement('a');
            link.className = 'ma-chat__attachment';
            link.href = message.attachment.url;
            link.target = '_blank';
            link.rel = 'noopener';
            if (message.attachment.mime?.startsWith('image/')) {
                const image = document.createElement('img');
                image.className = 'ma-chat__attachment-image';
                image.src = message.attachment.url;
                image.alt = message.attachment.name || 'Chat image attachment';
                image.loading = 'lazy';
                link.append(image);
            } else {
                link.textContent = message.attachment.name || 'Download attachment';
            }
            bubble.append(link);
        }

        const meta = document.createElement('div');
        meta.className = 'ma-chat__meta';
        const time = document.createElement('time');
        time.dateTime = message.created_at;
        time.textContent = formatTime(message.created_at);
        meta.append(time);
        if (message.is_mine) {
            const receipt = document.createElement('span');
            receipt.textContent = message.read_by_count > 0 ? 'Seen' : 'Sent';
            meta.append(receipt);
        }
        column.append(bubble, meta);
        row.append(column);
        return row;
    }

    async markRead() {
        if (!this.isOpen || !this.selectedId || !this.messages.length) return;
        const lastMessage = this.messages[this.messages.length - 1];
        if (lastMessage.id <= this.lastMarkedRead) return;
        this.lastMarkedRead = lastMessage.id;
        await request(`/api/chat/conversations/${this.selectedId}/read`, {
            method: 'POST',
            body: JSON.stringify({ message_id: lastMessage.id }),
        }).catch(() => {});
    }

    async send(event) {
        event.preventDefault();
        const body = this.refs.input.value.trim();
        const file = this.refs.attachment.files[0];
        if (!this.selectedId || (!body && !file)) return;

        const data = new FormData();
        if (body) data.append('body', body);
        if (file) data.append('attachment', file);
        this.refs.send.disabled = true;

        try {
            const payload = await request(`/api/chat/conversations/${this.selectedId}/messages`, {
                method: 'POST',
                body: data,
            });
            this.messages.push(this.normalizeMessage(payload.message));
            this.refs.input.value = '';
            this.refs.input.style.height = '';
            this.clearAttachment();
            this.renderMessages();
            this.clearError();
            this.refresh(false);
        } catch (error) {
            this.showError(error.message);
        } finally {
            this.refs.send.disabled = false;
        }
    }

    onInput() {
        this.refs.input.style.height = 'auto';
        this.refs.input.style.height = `${Math.min(this.refs.input.scrollHeight, 96)}px`;
        if (!this.selectedId) return;

        const now = Date.now();
        if (now - this.lastTypingSignal > 1800) {
            this.lastTypingSignal = now;
            this.sendTyping(true);
        }

        clearTimeout(this.typingTimer);
        this.typingTimer = setTimeout(() => this.sendTyping(false), 1400);
    }

    sendTyping(typing) {
        request(`/api/chat/conversations/${this.selectedId}/typing`, {
            method: 'POST',
            body: JSON.stringify({ typing }),
        }).catch(() => {});
    }

    showAttachment() {
        const file = this.refs.attachment.files[0];
        if (!file) return this.clearAttachment();
        if (file.size > 5 * 1024 * 1024) {
            this.showError('Attachments must be 5 MB or smaller.');
            return this.clearAttachment();
        }
        this.refs.attachmentName.textContent = file.name;
        this.refs.attachmentPreview.hidden = false;
    }

    clearAttachment() {
        this.refs.attachment.value = '';
        this.refs.attachmentPreview.hidden = true;
        this.refs.attachmentName.textContent = '';
    }

    async heartbeat() {
        await request('/api/chat/presence', { method: 'POST', body: '{}' }).catch(() => {});
        setTimeout(() => this.heartbeat(), 25000);
    }

    currentConversation() {
        return this.conversations.find((conversation) => conversation.id === this.selectedId);
    }

    showError(message) {
        this.refs.error.textContent = message;
        this.refs.error.hidden = false;
    }

    clearError() {
        this.refs.error.hidden = true;
        this.refs.error.textContent = '';
    }
}

const bootChatWidgets = () => {
    document.querySelectorAll('[data-chat-widget]:not([data-chat-ready])').forEach((root) => {
        root.dataset.chatReady = 'true';
        new ChatWidget(root);
    });
};

document.addEventListener('DOMContentLoaded', bootChatWidgets);
document.addEventListener('livewire:navigated', bootChatWidgets);
bootChatWidgets();
