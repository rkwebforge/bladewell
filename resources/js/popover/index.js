// Drives <x-widget.popover>. Opening, Esc and click-outside come from the native popover (popovertarget); this places
// the panel beside its trigger (flipping above when there's no room below), moves focus into it as it opens and back to
// the trigger as it closes, closes it when focus leaves it (Tab past its end), and closes it on [data-popover-close]
// inside. From JS: popover.open(target), popover.close(target), popover.toggle(target), with the trigger's id or any
// element of the popover; also on window.popover.
// Events on the panel: popover:opened and popover:closed. Delegated from `document`, so popovers added later (a
// Livewire render, fetched HTML) work without setting up.

const VIEWPORT_EDGE = 8;
const GAP = 6;
const PANEL = '[data-popover-panel]';
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

let openPanel = null;

const parts = (panel) => {
    const root = panel.closest('[data-popover]');

    return { root, trigger: root.querySelector('[data-popover-trigger]') };
};
// The trigger's id (or the panel's), or any element of the popover: its trigger, panel or wrapper.
const panelOf = (target) => {
    const element = typeof target === 'string' ? document.getElementById(target) : target;
    const panel = element?.closest?.('[data-popover]')?.querySelector(PANEL);
    if (!panel) {
        console.warn('Bladewell popover: no popover on this page for', target);
    }

    return panel ?? null;
};

// Whether any of the trigger can still be seen: not scrolled out of a container that clips it (a table that scrolls
// inside itself, a modal's body) or the window, and not covered there (a table's sticky header).
function inView(trigger, panel) {
    const box = trigger.getBoundingClientRect();
    let [top, right, bottom, left] = [Math.max(box.top, 0), Math.min(box.right, window.innerWidth), Math.min(box.bottom, window.innerHeight), Math.max(box.left, 0)];
    for (let parent = trigger.parentElement; parent; parent = parent.parentElement) {
        const style = getComputedStyle(parent);
        if (/auto|scroll|hidden|clip/.test(`${style.overflowX} ${style.overflowY}`)) {
            const clip = parent.getBoundingClientRect();
            [top, right, bottom, left] = [Math.max(top, clip.top), Math.min(right, clip.right), Math.min(bottom, clip.bottom), Math.max(left, clip.left)];
        }
    }
    if (bottom - top < 1 || right - left < 1) {
        return false;
    }
    const hit = document.elementsFromPoint((left + right) / 2, (top + bottom) / 2).find((element) => !panel.contains(element));

    return !hit || trigger.contains(hit);
}

function position() {
    if (!openPanel) {
        return;
    }
    const panel = openPanel;
    const { root, trigger } = parts(panel);
    // In the top layer nothing clips it: with its trigger scrolled out of view it would point at nothing. Close it.
    if (!inView(trigger, panel)) {
        panel.hidePopover();

        return;
    }
    const box = trigger.getBoundingClientRect();
    // Measured at its full height: still held to the room it had last time (before a resize), it would be placed by
    // that height, then grow past the edge of the screen.
    panel.style.maxHeight = '';
    const width = panel.offsetWidth;
    const height = panel.offsetHeight;

    // start and end follow the reading direction: end is the left edge in right-to-left pages.
    const rtl = getComputedStyle(root).direction === 'rtl';
    const align = root.dataset.align;
    const left = align === 'center'
        ? box.left + box.width / 2 - width / 2
        : (align === 'end') !== rtl ? box.right - width : box.left;
    panel.style.left = `${Math.min(Math.max(left, VIEWPORT_EDGE), window.innerWidth - width - VIEWPORT_EDGE)}px`;

    // Below by default; above when it fits there and not below. Fitting neither, the roomier side wins and the panel
    // scrolls in that room.
    const roomBelow = window.innerHeight - VIEWPORT_EDGE - (box.bottom + GAP);
    const roomAbove = box.top - GAP - VIEWPORT_EDGE;
    const up = height > roomBelow && (height <= roomAbove || roomAbove > roomBelow);
    const room = up ? roomAbove : roomBelow;
    if (height > room) {
        panel.style.maxHeight = `${Math.max(room, 0)}px`;
    }
    panel.style.top = `${up ? box.top - GAP - Math.min(height, room) : box.bottom + GAP}px`;
    panel.dataset.side = up ? 'top' : 'bottom';
}

// Popover toggle events don't bubble, so listen in the capture phase.
const focusTargets = new WeakMap();

// Focus goes into it as a dialog's does: to its first control, or to the panel itself when it's only text (read from
// the top). Marked with autofocus just before it opens, so the browser focuses it as part of opening: a script calling
// focus() as the toggle event runs is ignored, while the panel is still fading in. It starts transparent and is placed
// as it opens, so it never shows at the popover's default spot (the middle of the screen).
document.addEventListener('beforetoggle', (event) => {
    const panel = event.target;
    if (panel.matches?.(PANEL) && event.newState === 'open') {
        const target = panel.querySelector('[data-autofocus]') ?? panel.querySelector(FOCUSABLE) ?? panel;
        focusTargets.set(panel, target);
        if (!target.hasAttribute('autofocus')) {
            target.setAttribute('autofocus', '');
            target.dataset.popoverAutofocus = '';
        }
    }
}, true);

document.addEventListener('toggle', (event) => {
    const panel = event.target;
    if (!panel.matches?.(PANEL)) {
        return;
    }
    const { trigger } = parts(panel);
    const open = event.newState === 'open';
    trigger.setAttribute('aria-expanded', String(open));

    if (open) {
        openPanel = panel;
        position();
        // Only for this opening: the content may change before the next.
        panel.querySelectorAll('[data-popover-autofocus]').forEach((element) => {
            element.removeAttribute('autofocus');
            delete element.dataset.popoverAutofocus;
        });
        if (panel.hasAttribute('data-popover-autofocus')) {
            panel.removeAttribute('autofocus');
            delete panel.dataset.popoverAutofocus;
        }
        // The browser only autofocuses for a person's click or key. Opened by a script (popover.open, a Livewire event),
        // focus goes in here instead, frame by frame until it takes: while the panel is still fading in, focus() can be
        // ignored. It stops once focus is inside, the panel closes, or after half a second.
        let frames = 0;
        const into = () => {
            if (!panel.matches(':popover-open') || panel.contains(document.activeElement)) {
                return;
            }
            focusTargets.get(panel)?.focus({ preventScroll: true });
            if (!panel.contains(document.activeElement) && ++frames < 30) {
                requestAnimationFrame(into);
            }
        };
        into();
        window.addEventListener('scroll', position, true);
        window.addEventListener('resize', position);
        panel.dispatchEvent(new CustomEvent('popover:opened', { bubbles: true }));

        return;
    }

    if (openPanel === panel) {
        openPanel = null;
        window.removeEventListener('scroll', position, true);
        window.removeEventListener('resize', position);
    }
    // Esc, a close button or a script leaves focus nowhere or inside (or, in a modal, on the dialog around the trigger);
    // put it back on the trigger. A click elsewhere, or Tab out of the panel, keeps focus where it went.
    const left = document.activeElement;
    if (left === document.body || panel.contains(left) || left?.contains(trigger)) {
        trigger.focus({ preventScroll: true });
    }
    panel.dispatchEvent(new CustomEvent('popover:closed', { bubbles: true }));
}, true);

// Focus leaving the panel (Tab past its last control, or Shift+Tab back to the trigger) closes it, as a click outside
// does. Without this, the panel would stay open behind whatever has focus now.
document.addEventListener('focusout', (event) => {
    const panel = event.target.closest?.(PANEL);
    if (panel?.matches(':popover-open') && event.relatedTarget && !panel.contains(event.relatedTarget)) {
        panel.hidePopover();
    }
});

document.addEventListener('click', (event) => {
    const closer = event.target.closest?.(`${PANEL} [data-popover-close]`);
    closer?.closest(PANEL).hidePopover();
});

function open(target) {
    const panel = panelOf(target);
    if (panel && !panel.matches(':popover-open')) {
        panel.showPopover();
    }
}

function close(target) {
    const panel = panelOf(target);
    if (panel?.matches(':popover-open')) {
        panel.hidePopover();
    }
}

function toggle(target) {
    const panel = panelOf(target);
    panel?.togglePopover();
}

export const popover = { open, close, toggle };

// Global, as modal and toast are, so a script on the page can call it without an import.
window.popover = popover;

// From a Livewire component ($this->dispatch('popover-close', id: 'filters')) or any script.
window.addEventListener('popover-open', (event) => open(event.detail?.id));
window.addEventListener('popover-close', (event) => close(event.detail?.id));
