// Drives <x-widget.chat>. Keeps the reader's place when a message arrives below while they're scrolled up, and offers a
// button down to it; loads older messages from history-url as the top of the list comes into view; and runs the
// composer: Enter to send, a box that grows with its text, files as chips, and suggested prompts that fill it in.
// Delegated from `document` where it can be; each chat's list is watched for size as it's met (a Livewire render keeps
// the same elements, so the watching carries on).

const CHAT = '[data-chat]';
// How close to an end counts as being there, in pixels.
const NEAR = 32;
// How far from the top the older messages start loading, so they're usually there before the reader reaches it.
const PRELOAD = 240;

const scrollerOf = (chat) => chat.querySelector('[data-chat-scroll]');
const logOf = (chat) => chat.querySelector('[data-chat-log]');
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// The list scrolls in a reversed column: scrollTop is 0 at the newest message and goes negative upwards.
const atBottom = (scroller) => scroller.scrollTop > -NEAR;
const nearTop = (scroller) => scroller.scrollHeight - scroller.clientHeight + scroller.scrollTop < PRELOAD;

// --- Keeping the reader's place --------------------------------------------------------------------------------------

// Per chat: where it was scrolled and how tall its messages were when last seen, and whether the next change in height
// is older messages going in at the top (which moves nothing, being at the far end of the reversed column).
const seen = new WeakMap();

const resized = new ResizeObserver((entries) => {
    entries.forEach(({ target: log }) => {
        const chat = log.closest(CHAT);
        const scroller = chat && scrollerOf(chat);
        const state = chat && seen.get(chat);
        if (!state) {
            return;
        }
        const grew = scroller.scrollHeight - state.height;
        // Something new below (a message, a reply streaming in) while the reader is up the list: the reversed column
        // would carry what they're reading up out of view, so move it back down by as much, and offer the way down.
        if (grew > 0 && !state.older && state.top <= -NEAR) {
            scroller.scrollTop = state.top - grew;
            chat.querySelector('[data-chat-latest]')?.removeAttribute('hidden');
        }
        state.older = false;
        state.top = scroller.scrollTop;
        state.height = scroller.scrollHeight;
    });
});

function watch(chat) {
    if (seen.has(chat)) {
        return;
    }
    const scroller = scrollerOf(chat);
    seen.set(chat, { top: scroller.scrollTop, height: scroller.scrollHeight, older: false });
    resized.observe(logOf(chat));
}

// The server writes each time in its own timezone; shown again here in the reader's, in the page's language. Marked
// once done, so a Livewire render (which puts back the server's) is redone and nothing else twice.
const clock = new Intl.DateTimeFormat(document.documentElement.lang || undefined, { timeStyle: 'short' });
function localTimes(root) {
    root.querySelectorAll('time[data-chat-time]:not([data-chat-local])').forEach((time) => {
        const at = new Date(time.dateTime);
        if (!Number.isNaN(at.getTime())) {
            time.textContent = clock.format(at);
            time.setAttribute('data-chat-local', '');
        }
    });
}

const watchAll = () => document.querySelectorAll(CHAT).forEach((chat) => {
    watch(chat);
    localTimes(chat);
});
watchAll();
document.addEventListener('DOMContentLoaded', watchAll);
// A page brought in by wire:navigate, or a chat a Livewire render added.
document.addEventListener('livewire:navigated', watchAll);
const hook = (Livewire) => {
    Livewire.hook('morphed', watchAll);
    // A message sent with wire:submit has arrived (or failed): the composer can send again.
    Livewire.hook('commit', ({ succeed, fail }) => {
        const done = () => document.querySelectorAll('[data-chat-composer][data-chat-sending]').forEach(sent);
        succeed(done);
        fail(done);
    });
};
// Back to a page kept in the browser's cache, after it was left by sending: it can send again.
window.addEventListener('pageshow', () => document.querySelectorAll('[data-chat-composer][data-chat-sending]').forEach(sent));
if (window.Livewire) {
    hook(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => hook(window.Livewire));
}

document.addEventListener('scroll', (event) => {
    const scroller = event.target instanceof Element && event.target.matches('[data-chat-scroll]') ? event.target : null;
    const chat = scroller?.closest(CHAT);
    if (!chat) {
        return;
    }
    watch(chat);
    seen.get(chat).top = scroller.scrollTop;
    if (atBottom(scroller)) {
        chat.querySelector('[data-chat-latest]')?.setAttribute('hidden', '');
    }
    if (nearTop(scroller)) {
        loadOlder(chat);
    }
}, true);

function toLatest(chat) {
    scrollerOf(chat).scrollTo({ top: 0, behavior: reducedMotion() ? 'instant' : 'smooth' });
    chat.querySelector('[data-chat-latest]')?.setAttribute('hidden', '');
}

// --- Older messages ---------------------------------------------------------------------------------------------------

const loading = new WeakSet();

async function loadOlder(chat) {
    const button = chat.querySelector('[data-chat-older]');
    if (!button || !chat.dataset.chatHistory || loading.has(chat)) {
        return;
    }
    // Only its words change: the spinner beside them stays.
    const label = button.querySelector('[data-chat-older-label]') ?? button;
    // Only this site's HTML goes into the page, as with the table's and the tree's: another site's could carry script.
    let url = null;
    try {
        url = new URL(chat.dataset.chatHistory, window.location.href);
    } catch {
        // Left null: refused below.
    }
    if (url?.origin !== window.location.origin) {
        console.warn('Bladewell chat: history-url must be a URL on this site:', chat.dataset.chatHistory);
        label.textContent = "Couldn't load older messages";

        return;
    }

    watch(chat);
    loading.add(chat);
    const log = logOf(chat);
    button.toggleAttribute('data-busy', true);
    label.textContent = 'Loading older messages…';
    log.setAttribute('aria-busy', 'true');
    try {
        const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        // A redirect (to a login page, say) brings back a page, not messages.
        if (!response.ok || response.redirected) {
            throw new Error(`${response.status}`);
        }
        const html = (await response.text()).replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '');
        // They come back in a chat of their own: its messages go in at the top, and its history-url is where the ones
        // before them are. No history-url: these were the first.
        const answer = new DOMParser().parseFromString(html, 'text/html').querySelector(CHAT);
        const older = [...(answer?.querySelector('[data-chat-log]')?.children ?? [])];
        seen.get(chat).older = true;
        log.prepend(...older.map((node) => document.adoptNode(node)));
        localTimes(log);
        if (answer?.dataset.chatHistory) {
            chat.dataset.chatHistory = answer.dataset.chatHistory;
            label.textContent = 'Load older messages';
        } else {
            delete chat.dataset.chatHistory;
            button.parentElement.remove();
        }
    } catch {
        label.textContent = "Couldn't load older messages. Try again";
    } finally {
        button.removeAttribute('data-busy');
        log.removeAttribute('aria-busy');
        loading.delete(chat);
    }
}

// --- The composer ------------------------------------------------------------------------------------------------------

const inputOf = (form) => form.querySelector('[data-chat-input]');
const filesOf = (form) => form.querySelector('[data-chat-files]');

// field-sizing: content grows the box in browsers that have it; elsewhere, size it to its text here.
const grows = CSS.supports('field-sizing', 'content');
function fit(input) {
    if (!grows) {
        input.style.height = 'auto';
        input.style.height = `${input.scrollHeight}px`;
    }
}

document.addEventListener('input', (event) => {
    if (event.target.matches?.('[data-chat-input]')) {
        fit(event.target);
    }
});

// Enter sends, Shift+Enter is a new line. Not while an input method is composing a character (Japanese, Chinese…):
// Safari reports the Enter that confirms one as not composing, but with keyCode 229. And not on a touch screen, where
// Enter is how a new line is typed and the button sends.
document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing || event.keyCode === 229 || !event.target.matches?.('[data-chat-input]')) {
        return;
    }
    if (window.matchMedia('(pointer: coarse)').matches) {
        return;
    }
    event.preventDefault();
    event.target.closest('form')?.requestSubmit();
});

// Nothing to send (no text, no files), or a message already on its way (Enter twice, a double click): stopped before
// wire:submit or the browser sees it. On its way until the page goes (a plain form), the Livewire request that sent it
// is answered, or, failing both, a few seconds pass.
const SENDING_FOR = 8000;

function sent(form) {
    form.removeAttribute('data-chat-sending');
}

document.addEventListener('submit', (event) => {
    const form = event.target.closest?.('[data-chat-composer]');
    if (!form) {
        return;
    }
    const input = inputOf(form);
    const empty = input && input.value.trim() === '' && !(filesOf(form)?.files.length > 0);
    if (empty || form.hasAttribute('data-chat-sending')) {
        event.preventDefault();
        event.stopPropagation();
        input?.focus();

        return;
    }
    const chat = form.closest(CHAT);
    // async: sent from here, and the page stays. The box empties at once, so a second Enter has nothing to send twice.
    if (chat && sendsHere(form)) {
        event.preventDefault();
        event.stopPropagation();
        sendNow(form, chat);

        return;
    }
    form.setAttribute('data-chat-sending', '');
    setTimeout(() => sent(form), SENDING_FOR);
    if (chat) {
        toLatest(chat);
    }
}, true);

// Files chosen with the paperclip, shown above the text as chips, each with a button that takes it off again.
const plainName = (name) => name.replace(/[\u200E\u200F\u202A-\u202E\u2066-\u2069]/g, '');

const sizeText = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB'];
    const power = bytes > 0 ? Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1) : 0;
    const amount = bytes / 1024 ** power;
    const digits = power === 0 || amount >= 10 ? 0 : 1;

    return `${new Intl.NumberFormat(document.documentElement.lang || undefined, { maximumFractionDigits: digits }).format(amount)} ${units[power]}`;
};

// What the paperclip has picked so far, per file input. A browser's file picker replaces the input's files each time it's
// used, so each pick is added to these instead, and the input given the lot. A file picked twice is only added once.
const chosen = new WeakMap();
const sameFile = (a, b) => a.name === b.name && a.size === b.size && a.lastModified === b.lastModified;

function setFiles(input, files) {
    const list = new DataTransfer();
    files.forEach((file) => list.items.add(file));
    input.files = list.files;
    chosen.set(input, files);
}

// An image's chip shows the image, read straight from the file (a blob: URL, which a strict CSP's img-src must allow),
// and lets the URL go when the image is taken off.
const previews = new WeakMap();
const previewOf = (file) => previews.get(file) ?? previews.set(file, URL.createObjectURL(file)).get(file);
function forgetPreview(file) {
    if (previews.has(file)) {
        URL.revokeObjectURL(previews.get(file));
        previews.delete(file);
    }
}

function drawChips(form) {
    const list = form.querySelector('[data-chat-chips]');
    const files = filesOf(form);
    if (!list || !files) {
        return;
    }
    list.replaceChildren(...[...files.files].map((file, index) => {
        const image = file.type.startsWith('image/');
        const chip = document.createElement('li');
        chip.className = `bg-field flex max-w-56 items-center gap-2 rounded-lg py-1 pe-1 text-xs ${image ? 'ps-1' : 'ps-2.5'}`;
        if (image) {
            // Its name is beside it, so the picture adds nothing to read out.
            chip.append(Object.assign(document.createElement('img'), { src: previewOf(file), alt: '', className: 'bg-surface size-8 shrink-0 rounded-md object-cover' }));
        }
        // Without direction controls, which can make a name read as something else ("invoice‮fdp.exe" as
        // "invoiceexe.pdf"), and in <bdi>, so it can't reorder the size beside it either.
        const name = Object.assign(document.createElement('bdi'), { className: 'min-w-0 truncate font-medium', textContent: plainName(file.name) });
        const size = Object.assign(document.createElement('span'), { className: 'text-foreground/70 shrink-0', textContent: sizeText(file.size) });
        const remove = Object.assign(document.createElement('button'), {
            type: 'button',
            className: 'text-foreground/70 hover:bg-line hover:text-foreground focus-visible:ring-primary grid size-6 shrink-0 place-items-center rounded-md text-base leading-none outline-none focus-visible:ring-2',
            textContent: '×',
        });
        remove.setAttribute('aria-label', `Remove ${plainName(file.name)}`);
        remove.dataset.chatRemove = String(index);
        chip.append(name, size, remove);

        return chip;
    }));
}

function addFiles(input, files) {
    const before = chosen.get(input) ?? [];
    setFiles(input, [...before, ...files.filter((file) => !before.some((old) => sameFile(old, file)))]);
    drawChips(input.closest('form'));
}

document.addEventListener('change', (event) => {
    const input = event.target.matches?.('[data-chat-files]') ? event.target : null;
    if (input) {
        addFiles(input, [...input.files]);
    }
});

// Files pasted into the box (a screenshot) or dropped on the composer join the paperclip's. The picker only offers what
// its accept takes; these never went through it, so they're held to the same list here. Your Form Request still checks.
function accepts(input, file) {
    const rules = (input.getAttribute('accept') ?? '').split(',').map((rule) => rule.trim().toLowerCase()).filter(Boolean);
    const type = file.type.toLowerCase();

    return rules.length === 0 || rules.some((rule) => {
        if (rule.startsWith('.')) {
            return file.name.toLowerCase().endsWith(rule);
        }

        return rule.endsWith('/*') ? type.startsWith(rule.slice(0, -1)) : type === rule;
    });
}

const filesInto = (form, files) => {
    const input = form && filesOf(form);
    if (input) {
        addFiles(input, files.filter((file) => accepts(input, file)));
    }
};

document.addEventListener('paste', (event) => {
    const form = event.target.closest?.('[data-chat-composer]');
    const files = [...(event.clipboardData?.files ?? [])];
    if (form && filesOf(form) && files.length > 0) {
        // The file itself, not its name pasted in as text as well.
        event.preventDefault();
        filesInto(form, files);
    }
});

// A file dragged over the composer lights it up (data-dragging); let go there, it's added.
const draggingFiles = (event) => [...(event.dataTransfer?.types ?? [])].includes('Files');
document.addEventListener('dragover', (event) => {
    const form = event.target.closest?.('[data-chat-composer]');
    if (form && filesOf(form) && draggingFiles(event)) {
        event.preventDefault();
        form.setAttribute('data-dragging', '');
    }
});
document.addEventListener('dragleave', (event) => {
    const form = event.target.closest?.('[data-chat-composer]');
    if (form && !form.contains(event.relatedTarget)) {
        form.removeAttribute('data-dragging');
    }
});
document.addEventListener('drop', (event) => {
    const form = event.target.closest?.('[data-chat-composer]');
    if (form && filesOf(form) && event.dataTransfer?.files.length > 0) {
        event.preventDefault();
        form.removeAttribute('data-dragging');
        filesInto(form, [...event.dataTransfer.files]);
    }
});

// A form put back as it was (form.reset(), or after it's sent with Livewire) has no files: neither has the list.
document.addEventListener('reset', (event) => {
    const input = event.target.querySelector?.('[data-chat-files]');
    if (input) {
        (chosen.get(input) ?? []).forEach(forgetPreview);
        chosen.delete(input);
        // After the reset has cleared the input itself.
        setTimeout(() => drawChips(event.target));
    }
});

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }
    const remove = event.target.closest('[data-chat-remove]');
    if (remove) {
        // A file input's list can't be edited, only replaced: everything but the one taken off.
        const form = remove.closest('form');
        const input = filesOf(form);
        const files = chosen.get(input) ?? [...input.files];
        const gone = Number(remove.dataset.chatRemove);
        forgetPreview(files[gone]);
        setFiles(input, files.filter((_, index) => index !== gone));
        drawChips(form);
        (form.querySelector('[data-chat-remove]') ?? inputOf(form))?.focus();

        return;
    }

    const chat = event.target.closest(CHAT);
    if (!chat) {
        return;
    }
    if (event.target.closest('[data-chat-older]')) {
        loadOlder(chat);
    } else if (event.target.closest('[data-chat-latest]')) {
        toLatest(chat);
        inputOf(chat)?.focus({ preventScroll: true });
    } else {
        // A suggested prompt: into the box, ready to send or change.
        const prompt = event.target.closest('[data-chat-prompt]');
        const input = prompt && inputOf(chat);
        if (input) {
            input.value = prompt.dataset.chatPrompt || prompt.textContent.trim();
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
    }
});

// --- Sending without leaving the page (async) -------------------------------------------------------------------------

// The composer posts in the background when it has async, isn't Livewire's (wire:submit sends then), and posts to this
// site: what comes back goes into the page, so it must be this site's own HTML.
function sendsHere(form) {
    if (!form.hasAttribute('data-chat-async') || [...form.attributes].some((attribute) => attribute.name.startsWith('wire:submit'))) {
        return false;
    }
    try {
        return new URL(form.action, window.location.href).origin === window.location.origin;
    } catch {
        return false;
    }
}

const cloneOf = (chat, name) => chat.querySelector(`template[data-chat-${name}]`)?.content.firstElementChild?.cloneNode(true) ?? null;
// Each message on its way: what to send again on Retry, and the files' object URLs to let go once it's in.
const outgoing = new WeakMap();

// The message as it will look, shown at once: the text, the files (an image as itself, read from the file), Sending.
function outgoingBubble(chat, text, files) {
    const bubble = cloneOf(chat, 'outgoing');
    const slot = bubble.querySelector('[data-chat-outgoing-text]');
    if (text.trim() === '') {
        slot.parentElement.remove();
    } else {
        slot.textContent = text;
    }
    const urls = [];
    if (files.length > 0) {
        const cards = document.createElement('div');
        cards.className = 'mt-2 flex flex-wrap justify-end gap-2';
        files.forEach((file) => {
            const image = file.type.startsWith('image/');
            const card = cloneOf(chat, image ? 'outgoing-image' : 'outgoing-file');
            const url = URL.createObjectURL(file);
            urls.push(url);
            card.href = url;
            if (image) {
                Object.assign(card.querySelector('img'), { src: url, alt: plainName(file.name) });
            } else {
                card.querySelector('bdi').textContent = plainName(file.name);
                card.querySelector('bdi + span').textContent = sizeText(file.size);
            }
            cards.append(card);
        });
        bubble.querySelector('[data-chat-meta]').before(cards);
    }

    return [bubble, urls];
}

function sendNow(form, chat) {
    const input = inputOf(form);
    const files = filesOf(form);
    const text = input?.value ?? '';
    const picked = files ? [...files.files] : [];
    // Taken before the box and the files are cleared: text, files, CSRF token and any _method, as the form would send.
    const data = new FormData(form);
    // No file picked: a form still sends an empty one, which a file rule turns away. Left out instead.
    if (files && picked.length === 0) {
        data.delete(files.name);
    }
    const [bubble, urls] = outgoingBubble(chat, text, picked);
    // Above anyone still typing, which stays last, as it would in their own chat.
    const typing = logOf(chat).querySelector(':scope > [data-chat-typing]');
    if (typing) {
        typing.before(bubble);
    } else {
        logOf(chat).append(bubble);
    }
    outgoing.set(bubble, { form, chat, data, text, picked, urls });

    if (input) {
        input.value = '';
        fit(input);
        clearError(form);
    }
    if (files) {
        (chosen.get(files) ?? []).forEach(forgetPreview);
        setFiles(files, []);
        drawChips(form);
    }
    toLatest(chat);
    deliver(bubble);
}

function clearError(form) {
    const input = inputOf(form);
    form.querySelector(`#${CSS.escape(input.id)}-error`)?.remove();
    input.removeAttribute('aria-invalid');
    input.removeAttribute('aria-describedby');
}

function showError(form, message) {
    const input = inputOf(form);
    clearError(form);
    const note = Object.assign(document.createElement('p'), { id: `${input.id}-error`, className: 'text-error mx-auto mt-2 max-w-3xl px-1 text-sm', textContent: message });
    form.append(note);
    input.setAttribute('aria-invalid', 'true');
    input.setAttribute('aria-describedby', note.id);
}

// A message's status, from one of the chat's templates: delivered (Sent) or failed (Not sent, Retry).
function statusFrom(chat, bubble, name) {
    const template = chat.querySelector(`template[data-chat-${name}]`);
    if (template) {
        bubble.querySelector('[data-chat-status]')?.replaceChildren(template.content.cloneNode(true));
    }
}

async function deliver(bubble) {
    const { form, chat, data, text, picked, urls } = outgoing.get(bubble);
    bubble.setAttribute('data-chat-delivering', '');
    try {
        // X-Requested-With: Laravel answers a failed validation with 422 and the errors, not a redirect. The chat's look
        // goes along, so a route serving more than one can answer in the right one.
        const headers = { Accept: '*/*', 'X-Requested-With': 'XMLHttpRequest', 'X-Chat-Variant': chat.dataset.variant ?? 'assistant' };
        if (chat.hasAttribute('data-chat-compact')) {
            headers['X-Chat-Compact'] = '1';
        }
        // Text alone goes as a plain form body, as the browser would send it; files need multipart.
        const body = picked.length > 0 ? data : new URLSearchParams(data);
        const response = await fetch(form.action, { method: 'POST', body, headers, credentials: 'same-origin' });

        // Turned away (too long, a file too big): the message goes back into the box, with why.
        if (response.status === 422) {
            const answer = await response.json().catch(() => ({}));
            bubble.remove();
            urls.forEach((url) => URL.revokeObjectURL(url));
            const input = inputOf(form);
            if (input && input.value.trim() === '') {
                input.value = text;
                fit(input);
            }
            const files = filesOf(form);
            if (files && picked.length > 0) {
                addFiles(files, picked);
            }
            showError(form, Object.values(answer.errors ?? {}).flat()[0] ?? answer.message ?? "Your message couldn't be sent.");
            input?.focus();

            return;
        }
        // A redirect (to a login page, say) brings back a page, not the message.
        if (!response.ok || response.redirected) {
            throw new Error(`${response.status}`);
        }

        // In: the server's own copy takes its place (Sent, the real time), with anything that came back with it, such as
        // a reply. Nothing back: it just says Sent.
        const html = (await response.text()).replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '');
        const answer = new DOMParser().parseFromString(html, 'text/html').querySelector(CHAT);
        const messages = [...(answer?.querySelector('[data-chat-log]')?.children ?? [])];
        if (messages.length > 0) {
            bubble.replaceWith(...messages.map((node) => document.adoptNode(node)));
            localTimes(logOf(chat));
            urls.forEach((url) => URL.revokeObjectURL(url));
        } else {
            statusFrom(chat, bubble, 'delivered');
        }
        outgoing.delete(bubble);
    } catch {
        // Not sent (offline, the server failed): it stays, saying so, with Retry.
        const meta = bubble.querySelector('[data-chat-meta]');
        meta?.classList.add('text-error');
        statusFrom(chat, bubble, 'failed');
    } finally {
        bubble.removeAttribute('data-chat-delivering');
    }
}

document.addEventListener('click', (event) => {
    const retry = event.target instanceof Element ? event.target.closest('[data-chat-retry]') : null;
    const bubble = retry?.closest('[data-chat-message]');
    if (!bubble || !outgoing.has(bubble) || bubble.hasAttribute('data-chat-delivering')) {
        return;
    }
    const { chat } = outgoing.get(bubble);
    bubble.querySelector('[data-chat-meta]')?.classList.remove('text-error');
    bubble.querySelector('[data-chat-status]')?.replaceChildren(...cloneOf(chat, 'outgoing').querySelector('[data-chat-status]').childNodes);
    deliver(bubble);
});
