const normalize = (value) => String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

const timeLabel = (timestamp) => new Intl.DateTimeFormat(undefined, {
    hour: 'numeric',
    minute: '2-digit',
}).format(new Date(timestamp));

export function findClosestFaq(question, faqs) {
    const normalizedQuestion = normalize(question);
    const questionTokens = new Set(normalizedQuestion.split(' ').filter((token) => token.length > 2));

    const ranked = faqs.map((faq) => {
        const normalizedFaq = normalize(faq.question);
        let score = normalizedQuestion === normalizedFaq ? 100 : 0;

        faq.keywords.forEach((keyword) => {
            const normalizedKeyword = normalize(keyword);
            if (normalizedQuestion.includes(normalizedKeyword)) {
                score += normalizedKeyword.includes(' ') ? 8 : 4;
            }
        });

        normalizedFaq.split(' ').forEach((token) => {
            if (token.length > 3 && questionTokens.has(token)) score += 1;
        });

        return { faq, score };
    }).sort((left, right) => right.score - left.score);

    return ranked[0]?.score >= 3 ? ranked[0].faq : null;
}

class HotelAssistant {
    constructor(root) {
        this.root = root;
        this.config = this.readConfig();
        this.storageKey = 'ma-hotel-assistant-history';
        this.stateKey = 'ma-hotel-assistant-open';
        this.history = this.readHistory();
        this.refs = this.collectRefs();
        this.bind();
        this.renderSuggestions();
        this.renderQuickActions();
        this.refs.staffLink.href = this.config.staffAction.url;
        this.refs.staffLink.textContent = this.config.staffAction.label;

        if (!this.history.length) {
            this.history.push(this.message('assistant', this.config.welcome));
            this.saveHistory();
        }

        this.renderHistory();
        const queryOpen = new URLSearchParams(window.location.search).get('assistant') === 'open';
        this.root.classList.toggle('is-initial-open', queryOpen);
        this.setOpen(queryOpen || this.readSession(this.stateKey) === 'true', false);
    }

    readConfig() {
        const script = this.root.querySelector('[data-assistant-config]');
        return JSON.parse(script?.textContent || '{}');
    }

    collectRefs() {
        const get = (selector) => this.root.querySelector(selector);
        return {
            launcher: get('[data-assistant-launcher]'),
            panel: get('[data-assistant-panel]'),
            close: get('[data-assistant-close]'),
            messages: get('[data-assistant-messages]'),
            suggestions: get('[data-assistant-suggestions]'),
            actions: get('[data-assistant-actions]'),
            form: get('[data-assistant-form]'),
            input: get('[data-assistant-input]'),
            staffLink: get('[data-assistant-staff-link]'),
        };
    }

    bind() {
        this.refs.launcher.addEventListener('click', () => this.setOpen(true));
        this.refs.close.addEventListener('click', () => this.setOpen(false));
        this.refs.form.addEventListener('submit', (event) => this.submit(event));
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !this.refs.panel.hidden) this.setOpen(false);
        });
    }

    setOpen(open, focus = true) {
        this.refs.panel.hidden = !open;
        this.refs.launcher.hidden = open;
        this.refs.launcher.setAttribute('aria-expanded', String(open));
        this.writeSession(this.stateKey, String(open));

        if (open) {
            this.scrollToLatest();
            if (focus) window.setTimeout(() => this.refs.input.focus(), 100);
        } else if (focus) {
            this.refs.launcher.focus();
        }
    }

    submit(event) {
        event.preventDefault();
        const question = this.refs.input.value.trim();
        if (!question) return;

        this.history.push(this.message('user', question));
        const faq = this.closestFaq(question);
        this.history.push(faq
            ? this.message('assistant', faq.answer, faq.actions, faq.id)
            : this.message('assistant', this.config.fallback, this.config.fallbackActions));
        this.history = this.history.slice(-50);
        this.refs.input.value = '';
        this.saveHistory();
        this.renderHistory();
    }

    closestFaq(question) {
        return findClosestFaq(question, this.config.faqs);
    }

    answerFaq(faq) {
        this.history.push(this.message('user', faq.question));
        this.history.push(this.message('assistant', faq.answer, faq.actions, faq.id));
        this.history = this.history.slice(-50);
        this.saveHistory();
        this.renderHistory();
    }

    showFaqs() {
        this.history.push(this.message('assistant', 'Choose any suggested question below for an instant answer.'));
        this.saveHistory();
        this.renderHistory();
        this.refs.suggestions.closest('.hotel-assistant__suggestions')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    message(role, text, actions = [], faqId = null) {
        return {
            role,
            text,
            actions,
            faqId,
            timestamp: new Date().toISOString(),
        };
    }

    renderHistory() {
        this.refs.messages.replaceChildren();
        this.history.forEach((message) => this.refs.messages.append(this.messageNode(message)));
        this.scrollToLatest();
    }

    messageNode(message) {
        const row = document.createElement('article');
        row.className = `hotel-assistant__message${message.role === 'user' ? ' is-user' : ''}`;

        if (message.role !== 'user') {
            const mark = document.createElement('span');
            mark.className = 'hotel-assistant__message-mark';
            mark.setAttribute('aria-hidden', 'true');
            const logo = document.createElement('img');
            logo.src = this.config.logoUrl;
            logo.alt = '';
            logo.width = 40;
            logo.height = 40;
            mark.append(logo);
            row.append(mark);
        }

        const content = document.createElement('div');
        content.className = 'hotel-assistant__message-content';
        const bubble = document.createElement('div');
        bubble.className = 'hotel-assistant__bubble';
        bubble.textContent = message.text;
        content.append(bubble);

        if (message.actions?.length) {
            const actions = document.createElement('div');
            actions.className = 'hotel-assistant__message-actions';
            message.actions.forEach((action) => actions.append(this.actionNode(action)));
            content.append(actions);
        }

        const time = document.createElement('time');
        time.className = 'hotel-assistant__message-time';
        time.dateTime = message.timestamp;
        time.textContent = timeLabel(message.timestamp);
        content.append(time);
        row.append(content);
        return row;
    }

    renderSuggestions() {
        this.refs.suggestions.replaceChildren();
        this.config.faqs.forEach((faq) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = faq.question;
            button.addEventListener('click', () => this.answerFaq(faq));
            this.refs.suggestions.append(button);
        });
    }

    renderQuickActions() {
        this.refs.actions.replaceChildren();
        this.config.quickActions.forEach((action) => this.refs.actions.append(this.actionNode(action)));
    }

    actionNode(action) {
        if (action.action === 'show-faqs') {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = action.label;
            button.addEventListener('click', () => this.showFaqs());
            return button;
        }

        const link = document.createElement('a');
        link.href = action.url;
        link.textContent = action.label;
        return link;
    }

    scrollToLatest() {
        window.requestAnimationFrame(() => {
            this.refs.messages.scrollTop = this.refs.messages.scrollHeight;
        });
    }

    readHistory() {
        try {
            const value = JSON.parse(sessionStorage.getItem(this.storageKey) || '[]');
            return Array.isArray(value) ? value : [];
        } catch {
            return [];
        }
    }

    saveHistory() {
        this.writeSession(this.storageKey, JSON.stringify(this.history));
    }

    readSession(key) {
        try {
            return sessionStorage.getItem(key);
        } catch {
            return null;
        }
    }

    writeSession(key, value) {
        try {
            sessionStorage.setItem(key, value);
        } catch {
            // The assistant still works when browser storage is unavailable.
        }
    }
}

const bootHotelAssistant = () => {
    document.querySelectorAll('[data-hotel-assistant]:not([data-assistant-ready])').forEach((root) => {
        root.dataset.assistantReady = 'true';
        new HotelAssistant(root);
    });
};

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', bootHotelAssistant);
    bootHotelAssistant();
}
