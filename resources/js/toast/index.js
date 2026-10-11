// Drives <x-widget.toast>. From JS: toast.success('Saved.'), toast.loading(...) then
// toast.update(id, 'success', ...). From Laravel: redirect()->with('success', 'Saved.').

const ICONS = {
    success: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    error: '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
    warning: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
    clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    close: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
};

// Add a type here (plus a method on `toast` below) to extend the system.
// Errors and warnings use role="alert" so screen readers interrupt; the rest are polite.
const TYPES = {
    success: { title: 'Successful', icon: ICONS.success, color: 'text-success', bar: 'bg-success', role: 'status' },
    error: { title: 'Error', icon: ICONS.error, color: 'text-error', bar: 'bg-error', role: 'alert' },
    warning: { title: 'Warning', icon: ICONS.warning, color: 'text-warning', bar: 'bg-warning', role: 'alert' },
    info: { title: 'Info', icon: ICONS.info, color: 'text-primary', bar: 'bg-primary', role: 'status' },
    comingSoon: { title: 'Coming Soon', icon: ICONS.clock, color: 'text-primary', bar: 'bg-primary', role: 'status' },
    loading: { title: 'Please Wait...', icon: null, color: 'text-primary', bar: 'bg-primary', role: 'status' },
};

// Entry animation direction per container position, via CSS @starting-style (Tailwind `starting:`).
const ENTER_FROM = {
    top: 'starting:-translate-y-4',
    bottom: 'starting:translate-y-4',
    center: 'starting:scale-95',
};

// Matches the item's duration-300 exit transition.
const LEAVE_MS = 300;

const toasts = new Map();
let nextId = 0;

// The container, its settings, where the layout put it and the live regions beside it. Looked up again whenever it may
// have changed (see adopt), not once: wire:navigate swaps the body for the next page, container and all.
let container = null;
let config = null;
let home = null;
let regions = null;

// --- Where the toasts live, and how they're announced --------------------------------------------------

// An open modal <dialog> makes everything outside it inert, top-layer popovers included, so toasts drawn over
// it can't be clicked (a click falls through to the backdrop) and screen readers skip them. While a modal is open
// the container, and the live regions below, move inside the top-most one, and back when it closes.
const topModal = () => [...document.querySelectorAll('dialog:modal')].at(-1) ?? null;

// A toast's own element isn't a live region: it arrives with its text already in it, inside a popover that was
// hidden a moment before, and screen readers often miss that. These two are always on the page, empty until a
// toast has something to say. Errors and warnings interrupt (alert); the rest wait their turn (status).

// Where focus was before it went into the toasts, so dismissing the last one can put it back.
let focusBefore = null;

// The container on the page now; null without one. A new one (the next page, after wire:navigate) starts afresh: the
// toasts and regions that belonged to the old one went with it.
function adopt() {
    const found = document.querySelector('[data-toast-container]');
    if (found === container) {
        return container;
    }
    container = found;
    toasts.clear();
    config = home = regions = null;
    if (!container) {
        return null;
    }
    config = {
        position: container.dataset.position,
        autoClose: container.dataset.autoClose === 'false' ? false : Number(container.dataset.autoClose),
        showProgress: container.dataset.showProgress === 'true',
        pauseOnHover: container.dataset.pauseOnHover === 'true',
        pauseOnFocusLoss: container.dataset.pauseOnFocusLoss === 'true',
    };
    home = { parent: container.parentNode, next: container.nextSibling };
    regions = {
        status: Object.assign(document.createElement('div'), { className: 'sr-only' }),
        alert: Object.assign(document.createElement('div'), { className: 'sr-only' }),
    };
    regions.status.setAttribute('role', 'status');
    regions.alert.setAttribute('role', 'alert');
    container.after(regions.status, regions.alert);
    container.addEventListener('focusin', (event) => {
        if (!container.contains(event.relatedTarget)) {
            focusBefore = event.relatedTarget;
        }
    });

    return container;
}

function place() {
    const modal = topModal();
    const target = modal ?? home.parent;
    if (container.parentNode === target) {
        return;
    }
    // A popover can't be moved while it's showing; showContainer() opens it again at its new place.
    if (container.matches(':popover-open')) {
        container.hidePopover();
    }
    const nodes = [container, regions.status, regions.alert];
    if (modal) {
        modal.append(...nodes);

        return;
    }
    // Back where the layout put it (before its old next sibling, if that's still there).
    const before = home.next?.parentNode === home.parent ? home.next : null;
    nodes.forEach((node) => home.parent.insertBefore(node, before));
}

function announce(role, text) {
    const region = regions[role === 'alert' ? 'alert' : 'status'];
    // Emptied first and filled a moment later, so the same message twice in a row is read twice.
    region.textContent = '';
    setTimeout(() => { region.textContent = text; }, 100);
}

// A modal that closes with the toasts inside takes them out of view; move them back and show them again.
document.addEventListener('close', (event) => {
    if (container && event.target instanceof HTMLDialogElement && event.target.contains(container)) {
        place();
        if (toasts.size > 0) {
            showContainer();
        }
    }
}, true);

const pick = (options, key) => (options[key] !== undefined ? options[key] : config[key]);

function element(tag, className, text) {
    const el = document.createElement(tag);
    el.className = className;
    if (text !== undefined) {
        // textContent, never innerHTML: messages can come from the server or user input.
        el.textContent = text;
    }

    return el;
}

function icon(paths, className) {
    // Only ever called with the static ICONS strings above.
    const template = document.createElement('template');
    template.innerHTML = `<svg class="${className}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;

    return template.content.firstElementChild;
}

function pause(t, reason) {
    t.pausedBy.add(reason);
    t.timer?.pause();
}

function resume(t, reason) {
    t.pausedBy.delete(reason);
    if (!('leaving' in t.el.dataset) && t.pausedBy.size === 0 && t.timer?.playState === 'paused') {
        t.timer.play();
    }
}

// The progress bar's animation doubles as the auto-close timer, so pausing one pauses both.
function startTimer(t, bar) {
    t.timer?.cancel();
    t.timer = null;
    if (!t.autoClose) {
        return;
    }

    t.timer = bar.animate([{ transform: 'scaleX(1)' }, { transform: 'scaleX(0)' }], {
        duration: t.autoClose,
        easing: 'linear',
        fill: 'forwards',
    });
    t.timer.onfinish = () => dismiss(t.id);
    if (t.pausedBy.size > 0) {
        t.timer.pause();
    }
}

function render(t, type, message, options) {
    const kind = TYPES[type] ? type : 'info';
    const spec = TYPES[kind];

    t.type = kind;
    t.autoClose = pick(options, 'autoClose');
    t.showProgress = pick(options, 'showProgress');
    t.pauseOnHover = pick(options, 'pauseOnHover');
    t.pauseOnFocusLoss = pick(options, 'pauseOnFocusLoss');

    const heading = element('div', 'flex min-w-0 items-center gap-2');
    heading.append(
        kind === 'loading'
            ? element('span', 'border-line border-t-primary size-5 shrink-0 animate-spin rounded-full border-2')
            : icon(spec.icon, `size-6 shrink-0 ${spec.color}`),
        element('p', 'text-sm font-semibold', options.title ?? spec.title),
    );

    const close = element('button', 'text-muted hover:text-foreground focus-visible:ring-primary tap-target relative -m-1 grid size-7 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2');
    close.type = 'button';
    close.setAttribute('aria-label', 'Dismiss notification');
    close.append(icon(ICONS.close, 'size-5'));
    close.addEventListener('click', () => dismiss(t.id));

    const header = element('div', 'flex items-center justify-between gap-3');
    header.append(heading, close);

    const parts = [header];
    if (message) {
        parts.push(element('p', 'text-foreground/75 mt-1 text-sm break-words', message));
    }

    // The bar also drives the timer, so it exists whenever autoClose is on, just hidden if unwanted.
    // The toast's outline is an inset ring rather than a border, so this bar can sit flush on the bottom edge.
    let bar = null;
    if (t.autoClose) {
        bar = element('div', `absolute inset-x-0 bottom-0 h-1 origin-left ${spec.bar} ${t.showProgress ? '' : 'invisible'}`);
        parts.push(bar);
    }

    t.el.replaceChildren(...parts);
    startTimer(t, bar);
    announce(spec.role, [options.title ?? spec.title, message].filter(Boolean).join('. '));
}

function showContainer() {
    if (!container.showPopover) {
        return;
    }
    place();
    // A modal <dialog> opened after the container sits above it in the top layer; re-showing puts the toasts back on top.
    if (container.matches(':popover-open') && topModal() && !topModal().contains(container)) {
        container.hidePopover();
    }
    if (!container.matches(':popover-open')) {
        container.showPopover();
    }
}

function add(type, message, options = {}) {
    if (!adopt()) {
        console.warn('toast: add <x-widget.toast /> to the layout.');

        return null;
    }

    const id = ++nextId;
    const edge = config.position.split('-')[0];
    const el = element(
        'div',
        `bg-surface ring-line text-foreground pointer-events-auto relative w-full overflow-hidden rounded-xl p-4 ring-1 ring-inset shadow-lg transition duration-300 motion-reduce:transition-none starting:opacity-0 ${ENTER_FROM[edge]} data-leaving:scale-95 data-leaving:opacity-0`,
    );
    const t = { id, el, pausedBy: new Set(), timer: null };

    el.addEventListener('mouseenter', () => t.pauseOnHover && pause(t, 'hover'));
    el.addEventListener('mouseleave', () => resume(t, 'hover'));
    // Keyboard users tabbing to the close button shouldn't have it vanish under them.
    el.addEventListener('focusin', () => pause(t, 'keyboard'));
    el.addEventListener('focusout', (event) => !el.contains(event.relatedTarget) && resume(t, 'keyboard'));

    toasts.set(id, t);
    render(t, type, message, options);
    if (t.pauseOnFocusLoss && (document.hidden || !document.hasFocus())) {
        pause(t, 'window');
    }

    container.prepend(el);
    showContainer();

    return id;
}

function dismiss(id) {
    const targets = id === undefined ? [...toasts.values()] : [toasts.get(id)].filter(Boolean);

    for (const t of targets) {
        // Dismissed from the keyboard: focus goes to the next toast, or back where it was before, not to <body>.
        if (t.el.contains(document.activeElement)) {
            const next = [...toasts.values()].find((other) => other !== t && !targets.includes(other));
            (next?.el.querySelector('button') ?? (focusBefore?.isConnected ? focusBefore : null))?.focus();
        }
        toasts.delete(t.id);
        // Freeze the bar where it is. cancel() would drop the animation's fill and snap the bar back to
        // full width, which flashed during the fade-out.
        if (t.timer) {
            t.timer.onfinish = null;
            if (t.timer.playState === 'running') {
                t.timer.pause();
            }
        }
        t.el.dataset.leaving = '';
        setTimeout(() => {
            t.el.remove();
            if (toasts.size === 0 && container.childElementCount === 0 && container.matches?.(':popover-open')) {
                container.hidePopover();
            }
        }, LEAVE_MS);
    }
}

const pauseAll = () => toasts.forEach((t) => t.pauseOnFocusLoss && pause(t, 'window'));
const resumeAll = () => toasts.forEach((t) => resume(t, 'window'));
window.addEventListener('blur', pauseAll);
window.addEventListener('focus', resumeAll);
document.addEventListener('visibilitychange', () => (document.hidden ? pauseAll() : resumeAll()));

export const toast = {
    success: (message, options) => add('success', message, options),
    error: (message, options) => add('error', message, options),
    warning: (message, options) => add('warning', message, options),
    info: (message, options) => add('info', message, options),
    comingSoon: (message, options) => add('comingSoon', message, options),
    loading: (message, options) => add('loading', message, { autoClose: false, ...options }),
    update: (id, type, message, options = {}) => {
        const t = toasts.get(id);
        if (t) {
            render(t, type, message, options);
        }
    },
    dismiss,
};

// Exposed globally so Blade views and inline handlers can call toast.success(...) without imports.
window.toast = toast;

// Messages flashed by the server, once each: on load, and on each page wire:navigate brings in. Its Back and Forward bring
// back a copy of a page as it first arrived, flash included, so each flash's id (fresh per page) is shown only once.
const shownFlashes = new Set();

function showFlashed() {
    const flashed = document.querySelector('[data-toast-flash]');
    if (!flashed || shownFlashes.has(flashed.dataset.toastFlash)) {
        return;
    }
    shownFlashes.add(flashed.dataset.toastFlash);
    for (const { type, message, title } of JSON.parse(flashed.textContent)) {
        add(type, message, title ? { title } : {});
    }
}

showFlashed();
document.addEventListener('livewire:navigated', showFlashed);

// From a Livewire component: $this->dispatch('toast', type: 'success', message: 'Saved.'), with title: or autoClose: too.
// A flash in a Livewire action only shows on the next full page load; this shows it now. Any script can send it as well.
window.addEventListener('toast', (event) => {
    const { type = 'info', message = '', ...options } = event.detail ?? {};
    add(type, message, options);
});

// data-toast="success" data-toast-message="Saved." on any element shows a toast when it's clicked, with no script:
// success, error, warning, info, comingSoon or loading, with data-toast-title too. data-toast="dismiss" clears them all.
document.addEventListener('click', (event) => {
    const element = event.target.closest?.('[data-toast]');
    if (!element) {
        return;
    }
    const { toast: type, toastMessage: message = '', toastTitle: title } = element.dataset;
    if (type === 'dismiss') {
        dismiss();
    } else if (['success', 'error', 'warning', 'info', 'comingSoon', 'loading'].includes(type)) {
        toast[type](message, title ? { title } : {});
    } else {
        console.warn(`Bladewell toast: data-toast="${type}" isn't a kind of toast. Use success, error, warning, info, comingSoon, loading or dismiss.`, element);
    }
});
