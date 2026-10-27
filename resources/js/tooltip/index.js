// Drives <x-widget.tooltip>. Shows it when the pointer rests on the element, a finger taps plain text or an icon, or
// it gets keyboard focus, keeps it while
// the pointer moves onto the tooltip, and hides it on Esc, a press, or leaving (WCAG 1.4.13). Points the element's
// aria-describedby at it, so screen readers read it as the element's description. Delegated from `document`, so
// tooltips added later (a Livewire render, fetched HTML) work without setting up.

const ROOT = '[data-tooltip]';
const FOCUSABLE = 'a[href], button, input:not([type="hidden"]), select, textarea, summary, [tabindex]:not([tabindex="-1"])';
const SHOW_AFTER_MS = 300;
const HIDE_AFTER_MS = 120;
const GAP = 8;
const VIEWPORT_EDGE = 8;
// How far the tail keeps from the tooltip's corners.
const ARROW_INSET = 8;

let open = null;
let showTimer = null;
let hideTimer = null;

const bubbleOf = (root) => root.querySelector(':scope > [data-tooltip-bubble]');

// The element the tooltip describes: the first thing in it that can take focus, or the wrapper itself, made
// focusable, so keyboard users can reach a tooltip on plain text or an icon too.
function targetOf(root) {
    return [...root.children].find((child) => !child.matches('[data-tooltip-bubble]') && child.matches(FOCUSABLE))
        ?? root.querySelector(`:scope > :not([data-tooltip-bubble]) ${FOCUSABLE}`)
        ?? root;
}

// Points the target's aria-describedby at the tooltip (keeping the target's own ids), and drops a title saying the
// same thing, which would otherwise show the browser's own tooltip on top of this one. Run again after a Livewire
// render, which puts back the server's markup without them.
function link(root) {
    const bubble = bubbleOf(root);
    const target = targetOf(root);
    if (!bubble || !target) {
        return;
    }
    if (target === root && !root.hasAttribute('tabindex')) {
        root.tabIndex = 0;
    }
    // Made focusable around an icon with no text of its own, it would have no name at all: the tooltip's text is its
    // name then, not a description of one. It stands in for the icon, so it's an image; a plain span can't take a name.
    if (target === root && [...root.childNodes].every((node) => node === bubble || !node.textContent.trim())) {
        if (!root.hasAttribute('role')) {
            root.setAttribute('role', 'img');
        }
        root.setAttribute('aria-label', bubble.textContent.trim());

        return;
    }
    const ids = new Set((target.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean));
    if (!ids.has(bubble.id)) {
        target.setAttribute('aria-describedby', [...ids, bubble.id].join(' '));
    }
    if (target.getAttribute('title')?.trim() === bubble.textContent.trim()) {
        target.removeAttribute('title');
    }
}

function linkAll(scope = document) {
    if (scope instanceof Element && scope.matches(ROOT)) {
        link(scope);
    }
    scope.querySelectorAll?.(ROOT).forEach(link);
}

// Whether any of the element can still be seen: not scrolled out of a container that clips it (a table that scrolls
// inside itself, a modal's body) or the window, and not covered there (a table's sticky header). The same check as
// '../field''s inView, kept here since the tooltip needs nothing else from the field.
function inView(element, bubble) {
    const box = element.getBoundingClientRect();
    let [top, right, bottom, left] = [Math.max(box.top, 0), Math.min(box.right, window.innerWidth), Math.min(box.bottom, window.innerHeight), Math.max(box.left, 0)];
    for (let parent = element.parentElement; parent; parent = parent.parentElement) {
        const style = getComputedStyle(parent);
        if (/auto|scroll|hidden|clip/.test(`${style.overflowX} ${style.overflowY}`)) {
            const clip = parent.getBoundingClientRect();
            [top, right, bottom, left] = [Math.max(top, clip.top), Math.min(right, clip.right), Math.min(bottom, clip.bottom), Math.max(left, clip.left)];
        }
    }
    if (bottom - top < 1 || right - left < 1) {
        return false;
    }
    const hit = document.elementsFromPoint((left + right) / 2, (top + bottom) / 2).find((node) => !bubble.contains(node));

    return !hit || element.contains(hit);
}

// Its side first; the opposite one when it doesn't fit there. Kept inside the window either way. Hidden once its
// element is scrolled out of view, rather than left pointing at nothing.
function position(root) {
    const bubble = bubbleOf(root);
    if (!inView(targetOf(root), bubble)) {
        hide({ keepPending: true });

        return;
    }
    const box = targetOf(root).getBoundingClientRect();
    const width = bubble.offsetWidth;
    const height = bubble.offsetHeight;
    const rtl = getComputedStyle(root).direction === 'rtl';
    let side = root.dataset.placement ?? 'top';
    if (side === 'start' || side === 'end') {
        side = (side === 'end') !== rtl ? 'right' : 'left';
    }
    const room = { top: box.top, bottom: window.innerHeight - box.bottom, left: box.left, right: window.innerWidth - box.right };
    const needs = side === 'top' || side === 'bottom' ? height + GAP : width + GAP;
    const opposite = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' }[side];
    if (room[side] < needs && room[opposite] > room[side]) {
        side = opposite;
    }
    let left;
    let top;
    if (side === 'top' || side === 'bottom') {
        left = box.left + box.width / 2 - width / 2;
        top = side === 'top' ? box.top - GAP - height : box.bottom + GAP;
    } else {
        left = side === 'left' ? box.left - GAP - width : box.right + GAP;
        top = box.top + box.height / 2 - height / 2;
    }
    const placedLeft = Math.min(Math.max(left, VIEWPORT_EDGE), window.innerWidth - width - VIEWPORT_EDGE);
    const placedTop = Math.min(Math.max(top, VIEWPORT_EDGE), window.innerHeight - height - VIEWPORT_EDGE);
    bubble.style.left = `${placedLeft}px`;
    bubble.style.top = `${placedTop}px`;
    bubble.dataset.side = side;

    // The tail points at the element's middle, even when the tooltip was pushed in from the window's edge, and keeps
    // clear of the rounded corners.
    const arrow = bubble.querySelector('[data-tooltip-arrow]');
    if (arrow) {
        const size = arrow.offsetWidth;
        const along = (start, length, middle) => `${Math.min(Math.max(middle - start - size / 2, ARROW_INSET), length - size - ARROW_INSET)}px`;
        const across = side === 'top' || side === 'bottom';
        arrow.style.left = across ? along(placedLeft, width, box.left + box.width / 2) : '';
        arrow.style.top = across ? '' : along(placedTop, height, box.top + box.height / 2);
    }
}

function show(root) {
    clearTimeout(showTimer);
    clearTimeout(hideTimer);
    if (open === root) {
        return;
    }
    hide();
    const bubble = bubbleOf(root);
    if (!bubble?.textContent.trim()) {
        return;
    }
    link(root);
    bubble.showPopover();
    position(root);
    open = root;
}

// keepPending: closing one that went out of view leaves the next one, already on its way, to show.
function hide({ keepPending = false } = {}) {
    if (!keepPending) {
        clearTimeout(showTimer);
    }
    clearTimeout(hideTimer);
    if (open) {
        const bubble = bubbleOf(open);
        if (bubble?.matches(':popover-open')) {
            bubble.hidePopover();
        }
        open = null;
    }
}

const later = (root) => {
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => open === root && hide(), HIDE_AFTER_MS);
};

// Pointer: a short rest before it shows, so moving across a toolbar doesn't flash every tooltip. Moving onto the
// tooltip keeps it (its text can be selected, and magnified screens need to reach it).
document.addEventListener('pointerover', (event) => {
    if (event.pointerType === 'touch') {
        return;
    }
    const root = event.target.closest?.(ROOT);
    if (!root) {
        return;
    }
    clearTimeout(hideTimer);
    if (open !== root) {
        clearTimeout(showTimer);
        showTimer = setTimeout(() => show(root), open ? 0 : SHOW_AFTER_MS);
    }
});

// Touch has no hover, so a tap shows a tooltip on something a tap does nothing else to: plain text or an icon, made
// focusable by link(). On a button or a link the tap is the action, and screen readers read the text either way. It
// stays until the next press anywhere (the pointerdown below).
document.addEventListener('pointerup', (event) => {
    const root = event.pointerType === 'touch' ? event.target.closest?.(ROOT) : null;
    if (root && targetOf(root) === root) {
        show(root);
    }
});

document.addEventListener('pointerout', (event) => {
    // A finger lifting leaves the element too, which would take away the tooltip its tap just showed.
    if (event.pointerType === 'touch') {
        return;
    }
    const root = event.target.closest?.(ROOT);
    if (root && !root.contains(event.relatedTarget)) {
        clearTimeout(showTimer);
        later(root);
    }
});

// Keyboard focus shows it at once; a click's focus doesn't (the pointer already did, or it's a tap).
document.addEventListener('focusin', (event) => {
    const root = event.target.closest?.(ROOT);
    if (root && event.target.matches(':focus-visible')) {
        show(root);
    }
});

document.addEventListener('focusout', (event) => {
    const root = event.target.closest?.(ROOT);
    if (root && !root.contains(event.relatedTarget)) {
        later(root);
    }
});

// Esc hides it without moving focus, and a press means the person has moved on.
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && open) {
        // This Esc was the tooltip's: in a modal, it closes the tooltip and not the modal too.
        event.preventDefault();
        hide();
    }
});
document.addEventListener('pointerdown', () => hide());

window.addEventListener('scroll', () => open && position(open), true);
window.addEventListener('resize', () => open && position(open));
// The page's font arriving after a tooltip opened (a slow connection) changes its width: placed again, it stays centred.
document.fonts?.addEventListener('loadingdone', () => open && position(open));

linkAll();

// Tooltips added to the page later link themselves up, and a Livewire render, which puts back the server's markup,
// gets the describedby back.
new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            linkAll(node);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

// The bubble keeps its place through a render (wire:ignore.self), but the tail inside it doesn't: put it back too.
const hook = (Livewire) => Livewire.hook('morphed', ({ el }) => {
    linkAll(el);
    if (open) {
        position(open);
    }
});
if (window.Livewire) {
    hook(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => hook(window.Livewire));
}
