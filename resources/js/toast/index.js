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
// Swiped away: past this share of its width, or on a flick (pixels per millisecond) however short.
const SWIPE_DISTANCE = 0.35;
const SWIPE_SPEED = 0.5;

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
// the container, and the live regions below, move inside the top-most one, and back when it closes. The top one is the
// one focus is in (the browser makes those below it inert), not the last on the page: a modal further down the page can
// be open underneath. Last on the page only when focus is in none of them.
const topModal = () => document.activeElement?.closest('dialog:modal')
    ?? [...document.querySelectorAll('dialog:modal')].at(-1)
    ?? null;

// A toast's own element isn't a live region: it arrives with its text already in it, inside a popover that was
// hidden a moment before, and screen readers often miss that. These two are always on the page, empty until a
// toast has something to say. Errors and warnings interrupt (alert); the rest wait their turn (status).

// Where focus was before it went into the toasts, so dismissing the last one can put it back.
let focusBefore = null;

// "Dismiss all", above the stack (below it at the bottom of the screen) while two or more toasts are up, so a pile of
// them takes one click, not one each. Its label from the container (dismiss-all-label). On a phone (under the sm
// breakpoint) from three: its row takes room the toasts need there, and two are as quickly swiped away.
let dismissAll = null;
const phone = window.matchMedia('(width < 40rem)');

function syncDismissAll() {
    if (!dismissAll) {
        return;
    }
    if (dismissAll.parentElement !== container) {
        container.append(dismissAll);
    }
    dismissAll.hidden = toasts.size < (phone.matches ? 3 : 2);
}
// A phone turned on its side, or a window narrowed past the breakpoint.
phone.addEventListener('change', syncDismissAll);

// The container on the page now; null without one. A new one (the next page, after wire:navigate) starts afresh: the
// toasts and regions that belonged to the old one went with it.
function adopt() {
    const found = document.querySelector('[data-toast-container]');
    if (found === container) {
        return container;
    }
    container = found;
    toasts.clear();
    config = home = regions = dismissAll = null;
    if (!container) {
        return null;
    }
    config = {
        position: container.dataset.position,
        autoClose: container.dataset.autoClose === 'false' ? false : Number(container.dataset.autoClose),
        // Errors and warnings (role="alert") can keep their own; without alert-auto-close they follow autoClose.
        alertAutoClose: container.dataset.alertAutoClose === undefined ? undefined : (container.dataset.alertAutoClose === 'false' ? false : Number(container.dataset.alertAutoClose)),
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
    // order: at the stack's outer edge, whichever way it grows (flex-col-reverse at the bottom of the screen).
    const outer = config.position.startsWith('bottom') ? 'order-last' : 'order-first';
    // tap-target: 44px to a finger on a touch screen, without looking any bigger.
    dismissAll = element('button', `bg-surface text-foreground ring-line hover:bg-field focus-visible:ring-primary tap-target relative pointer-events-auto ${outer} shrink-0 self-end rounded-full px-3 py-1.5 text-xs font-medium shadow-lg ring-1 ring-inset outline-none focus-visible:ring-2`, container.dataset.dismissAllLabel || 'Dismiss all');
    dismissAll.type = 'button';
    dismissAll.dataset.toastDismissAll = '';
    dismissAll.hidden = true;
    dismissAll.addEventListener('click', () => {
        // It goes with the toasts, so focus goes back where it was before, not to <body>.
        const back = dismissAll.contains(document.activeElement) && focusBefore?.isConnected ? focusBefore : null;
        dismiss();
        back?.focus();
    });
    container.append(dismissAll);
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

// Messages waiting to be read, per role: toasts shown together (a flash pair) are read together, not the last one only.
const pending = { status: [], alert: [] };
const flushes = { status: null, alert: null };

function announce(role, text) {
    const key = role === 'alert' ? 'alert' : 'status';
    // The same toast twice in one go (two requests failing at once) is one toast on screen, counted: read it once too.
    if (!pending[key].includes(text)) {
        pending[key].push(text);
    }
    clearTimeout(flushes[key]);
    flushes[key] = setTimeout(() => {
        // Each one ends as a sentence, so a reader pauses between them.
        const said = pending[key].length === 1 ? pending[key][0] : pending[key].map((one) => (/[.!?…]$/.test(one) ? one : `${one}.`)).join(' ');
        pending[key] = [];
        // Where the browser has it, ariaNotify says it straight out, with no region to have been on the page first;
        // errors and warnings ahead of anything else being read. The regions are for the rest.
        if (typeof document.ariaNotify === 'function') {
            document.ariaNotify(said, { priority: key === 'alert' ? 'high' : 'normal' });

            return;
        }
        const region = regions?.[key];
        if (!region) {
            return;
        }
        // Emptied first and filled a moment later, so the same message twice in a row is read twice.
        region.textContent = '';
        setTimeout(() => { region.textContent = said; }, 100);
    }, 50);
}

// A modal opened while toasts are showing would leave them outside it: under its backdrop and inert. Move them in, as
// for toasts added while it's open. toggle doesn't bubble, so in the capture phase.
document.addEventListener('toggle', (event) => {
    if (container && event.newState === 'open' && event.target instanceof HTMLDialogElement && event.target.matches(':modal')) {
        place();
        if (toasts.size > 0) {
            showContainer();
        }
    }
}, true);

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
    // What makes two toasts the same one (see add): the kind, the title and the message. One with an action has no
    // twin: two "Deleted" toasts with Undo undo different things.
    const action = typeof options.action?.label === 'string' && typeof options.action.onClick === 'function' ? options.action : null;
    t.key = action || options.merge === false ? null : [kind, options.title ?? spec.title, message ?? ''].join('\n');
    // A toast's own autoClose wins; one with an action stays until dismissed, as its button would otherwise vanish
    // before it could be used; then alert-auto-close for errors and warnings; then the container's.
    if (action && options.autoClose === undefined) {
        t.autoClose = false;
    } else {
        t.autoClose = options.autoClose !== undefined || spec.role !== 'alert' || config.alertAutoClose === undefined ? pick(options, 'autoClose') : config.alertAutoClose;
    }
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
    // Shown again while it was still up: how many times, beside the title. Read out again each time, so the count is for
    // the eye only.
    if (t.count > 1) {
        const times = element('span', 'text-muted text-xs font-medium tabular-nums', `×${t.count}`);
        times.setAttribute('aria-hidden', 'true');
        heading.append(times);
    }

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
    // An action (Undo, Retry): it runs, and the toast goes.
    if (action) {
        // tap-target-y: 44px tall to a finger; not wider, which would reach over the toast's edge.
        const button = element('button', 'text-primary hover:bg-primary/10 focus-visible:ring-primary tap-target-y relative -mx-2 mt-2 rounded-lg px-2 py-1 text-sm font-semibold outline-none focus-visible:ring-2', action.label);
        button.type = 'button';
        button.dataset.toastAction = '';
        button.addEventListener('click', () => {
            dismiss(t.id);
            action.onClick();
        });
        parts.push(button);
    }

    // The bar also drives the timer, so it exists whenever autoClose is on, just hidden if unwanted.
    // The toast's outline is an inset ring rather than a border, so this bar can sit flush on the bottom edge.
    let bar = null;
    if (t.autoClose) {
        bar = element('div', `absolute inset-x-0 bottom-0 h-1 origin-left ${spec.bar} ${t.showProgress ? '' : 'invisible'}`);
        parts.push(bar);
    }

    // Focus on its close button (a keyboard user waiting on a loading toast) goes to the new one, rather than to <body>
    // as the old one is taken out; focusin then holds the timer, as it does for any toast with focus.
    const hadFocus = t.el.contains(document.activeElement);
    t.el.replaceChildren(...parts);
    startTimer(t, bar);
    if (hadFocus) {
        close.focus({ preventScroll: true });
    }
    // Its text only, not its buttons' names; with an action, a hint that there's one to reach.
    // Each part a sentence, so a message that already ends in one isn't doubled.
    announce(spec.role, [options.title ?? spec.title, message, action ? 'Actions available' : null].filter(Boolean).map((part) => (/[.!?…]$/.test(part) ? part : `${part}.`)).join(' '));
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

    // The same toast shown again while it's still up (Save pressed over and over while the server is down) doesn't stack
    // another copy: the one there counts it, comes back to the top, starts its time again and is read out again. Not a
    // loading toast: each stands for a task of its own, updated by its id. Nor one shown with merge: false, for code that
    // keeps its own toast's id.
    const kind = TYPES[type] ? type : 'info';
    const key = [kind, options.title ?? TYPES[kind].title, message ?? ''].join('\n');
    const same = kind === 'loading' || options.action || options.merge === false ? null : [...toasts.values()].find((t) => t.key === key && !('leaving' in t.el.dataset));
    if (same) {
        same.count += 1;
        render(same, type, message, options);
        // Not while it has focus: taking it out of the page to move it would drop that focus.
        if (!same.el.contains(document.activeElement)) {
            container.prepend(same.el);
        }
        // Shown, as a new one would be: the stack may have been put away meanwhile (a modal opened over it, say).
        showContainer();

        return same.id;
    }

    const id = ++nextId;
    const edge = config.position.split('-')[0];
    const el = element(
        'div',
        // shrink-0: past what fits on screen the stack scrolls; without it, every toast would be squeezed shorter instead.
        `bg-surface ring-line text-foreground pointer-events-auto relative w-full shrink-0 overflow-hidden rounded-xl p-4 ring-1 ring-inset shadow-lg transition duration-300 motion-reduce:transition-none starting:opacity-0 ${ENTER_FROM[edge]} data-leaving:scale-95 data-leaving:opacity-0`,
    );
    el.dataset.toastItem = '';
    const t = { id, el, pausedBy: new Set(), timer: null, count: 1 };

    el.addEventListener('mouseenter', () => t.pauseOnHover && pause(t, 'hover'));
    el.addEventListener('mouseleave', () => resume(t, 'hover'));
    // Keyboard users tabbing to the close button shouldn't have it vanish under them.
    el.addEventListener('focusin', () => pause(t, 'keyboard'));
    el.addEventListener('focusout', (event) => !el.contains(event.relatedTarget) && resume(t, 'keyboard'));
    swipeAway(t);

    toasts.set(id, t);
    render(t, type, message, options);
    if (t.pauseOnFocusLoss && (document.hidden || !document.hasFocus())) {
        pause(t, 'window');
    }

    container.prepend(el);
    syncDismissAll();
    showContainer();

    return id;
}

// On a touch screen a toast follows a finger dragged sideways, either way, and goes past a third of its width or on a
// flick; otherwise it springs back. The close button does the same for anyone who can't swipe. Touch events, as the
// modal's sheet uses: the browser cancels a pointer once it starts scrolling. Decided on the first movement, the only
// one iOS lets a script cancel: up or down is the stack scrolling, and is left to the browser.
function swipeAway(t) {
    let drag = null;
    t.el.addEventListener('touchstart', (event) => {
        drag = event.touches.length === 1 ? { x: event.touches[0].clientX, y: event.touches[0].clientY, dx: 0, dragging: false, moves: [] } : null;
    }, { passive: true });

    t.el.addEventListener('touchmove', (event) => {
        if (!drag) {
            return;
        }
        const { clientX: x, clientY: y } = event.touches[0];
        if (!drag.dragging) {
            const across = Math.abs(x - drag.x);
            if (across === 0 && y === drag.y) {
                return;
            }
            if (across <= Math.abs(y - drag.y) || !event.cancelable) {
                drag = null;

                return;
            }
            drag.dragging = true;
            // Held while it's in the finger, as it is under a resting pointer.
            pause(t, 'swipe');
            // Through the CSSOM, which a Content Security Policy allows, unlike a style attribute in the markup.
            t.el.style.transition = 'none';
        }
        event.preventDefault();
        drag.dx = x - drag.x;
        t.el.style.translate = `${drag.dx}px 0`;
        t.el.style.opacity = String(1 - Math.min(Math.abs(drag.dx) / t.el.offsetWidth, 1) * 0.6);
        drag.moves = [drag.moves.at(-1) ?? { x: drag.x, t: event.timeStamp }, { x, t: event.timeStamp }];
    }, { passive: false });

    const release = (event) => {
        if (!drag?.dragging) {
            drag = null;

            return;
        }
        const { dx, moves: [from, to] } = drag;
        drag = null;
        // No click on whatever the finger was lifted from (its close or action button).
        if (event.cancelable) {
            event.preventDefault();
        }
        // A finger that stopped before lifting isn't a flick, however fast it moved before.
        const speed = event.timeStamp - to.t > 100 ? 0 : (to.x - from.x) / Math.max(to.t - from.t, 1);
        const away = event.type === 'touchend' && (Math.abs(dx) > t.el.offsetWidth * SWIPE_DISTANCE || (Math.abs(speed) > SWIPE_SPEED && Math.sign(speed) === Math.sign(dx)));
        if (!away) {
            // Handing back to the classes eases it back from where the finger left it.
            t.el.style.transition = '';
            t.el.style.translate = '';
            t.el.style.opacity = '';
            resume(t, 'swipe');

            return;
        }
        // On out the way it was going, then gone; at once with reduced motion.
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            t.el.style.transition = `translate ${LEAVE_MS}ms ease-out, opacity ${LEAVE_MS}ms ease-out`;
            t.el.style.translate = `${Math.sign(dx) * t.el.offsetWidth * 1.2}px 0`;
            t.el.style.opacity = '0';
        }
        dismiss(t.id);
    };
    t.el.addEventListener('touchend', release);
    t.el.addEventListener('touchcancel', release);
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
            if (toasts.size === 0 && !container.querySelector('[data-toast-item]') && container.matches?.(':popover-open')) {
                container.hidePopover();
            }
        }, LEAVE_MS);
    }
    syncDismissAll();
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
// htmx and Turbo swap pages without a load too; each flash still shows once (its id).
document.addEventListener('htmx:afterSettle', showFlashed);
document.addEventListener('turbo:load', showFlashed);

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
