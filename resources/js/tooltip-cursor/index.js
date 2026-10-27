// Drives <x-widget.tooltip-cursor>: a tooltip that follows the pointer over its element, just below and after it, and
// moves to the other side of the pointer near the window's edges. Keyboard focus puts it under the element instead.
// Esc hides it. Points the element's aria-describedby at it for screen readers. Delegated from `document`, so ones
// added later (a Livewire render, fetched HTML) work without setting up.

const ROOT = '[data-tooltip-cursor]';
const FOCUSABLE = 'a[href], button, input:not([type="hidden"]), select, textarea, summary, [tabindex]:not([tabindex="-1"])';
const SHOW_AFTER_MS = 150;
// Leaving one for the next within this long (the bars of a chart) shows the next at once.
const STILL_READING_MS = 300;
// From the pointer to the tooltip's corner: clear of the cursor arrow, which points down and to the right.
const OFFSET = 14;
const GAP = 8;
const VIEWPORT_EDGE = 8;

let open = null;
let showTimer = null;
let frame = null;
let pointer = null;
let closedAt = 0;

const bubbleOf = (root) => root.querySelector(':scope > [data-tooltip-cursor-bubble]');

// The element it describes: the first thing in it that can take focus, or the wrapper itself, made focusable.
function targetOf(root) {
    return [...root.children].find((child) => !child.matches('[data-tooltip-cursor-bubble]') && child.matches(FOCUSABLE))
        ?? root.querySelector(`:scope > :not([data-tooltip-cursor-bubble]) ${FOCUSABLE}`)
        ?? root;
}

// As the tooltip does: aria-describedby on the element (its own ids kept), a title saying the same dropped, and the
// tooltip's text as the name of an icon with no text of its own.
function link(root) {
    const bubble = bubbleOf(root);
    const target = targetOf(root);
    if (!bubble || !target) {
        return;
    }
    if (target === root && !root.hasAttribute('tabindex')) {
        root.tabIndex = 0;
    }
    if (target === root && [...root.childNodes].every((node) => node === bubble || !node.textContent.trim())) {
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

// After the pointer and below it, or on the other side of it where that would run off the window. Without a pointer
// (keyboard focus), centred under the element, or over it without room below.
function place(root) {
    const bubble = bubbleOf(root);
    const width = bubble.offsetWidth;
    const height = bubble.offsetHeight;
    let left;
    let top;
    if (pointer) {
        const rtl = getComputedStyle(root).direction === 'rtl';
        const after = rtl ? pointer.x - OFFSET - width : pointer.x + OFFSET;
        const before = rtl ? pointer.x + OFFSET : pointer.x - OFFSET - width;
        left = after >= VIEWPORT_EDGE && after + width <= window.innerWidth - VIEWPORT_EDGE ? after : before;
        top = pointer.y + OFFSET + height <= window.innerHeight - VIEWPORT_EDGE ? pointer.y + OFFSET : pointer.y - OFFSET - height;
    } else {
        const box = targetOf(root).getBoundingClientRect();
        left = box.left + box.width / 2 - width / 2;
        top = box.bottom + GAP + height <= window.innerHeight - VIEWPORT_EDGE ? box.bottom + GAP : box.top - GAP - height;
    }
    bubble.style.left = `${Math.min(Math.max(left, VIEWPORT_EDGE), window.innerWidth - width - VIEWPORT_EDGE)}px`;
    bubble.style.top = `${Math.min(Math.max(top, VIEWPORT_EDGE), window.innerHeight - height - VIEWPORT_EDGE)}px`;
}

function show(root) {
    clearTimeout(showTimer);
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
    place(root);
    open = root;
}

// keepPending: closing one that went out of view leaves the next one, already on its way, to show.
function hide({ keepPending = false } = {}) {
    if (!keepPending) {
        clearTimeout(showTimer);
    }
    if (open) {
        const bubble = bubbleOf(open);
        if (bubble?.matches(':popover-open')) {
            bubble.hidePopover();
        }
        open = null;
        closedAt = performance.now();
    }
}

// The pointer: a short rest before it shows, then it follows, one move per frame.
document.addEventListener('pointerover', (event) => {
    if (event.pointerType === 'touch') {
        return;
    }
    const root = event.target.closest?.(ROOT);
    if (root && open !== root) {
        pointer = { x: event.clientX, y: event.clientY };
        clearTimeout(showTimer);
        const reading = open || performance.now() - closedAt < STILL_READING_MS;
        showTimer = setTimeout(() => show(root), reading ? 0 : SHOW_AFTER_MS);
    }
});

document.addEventListener('pointermove', (event) => {
    if (event.pointerType === 'touch') {
        return;
    }
    pointer = { x: event.clientX, y: event.clientY };
    if (open && !frame) {
        frame = requestAnimationFrame(() => {
            frame = null;
            if (open) {
                place(open);
            }
        });
    }
});

// Touch has no hover, so a tap shows it beside the finger, on something a tap does nothing else to (a chart, a map, an
// icon). On a button or a link the tap is the action. It stays until the next press anywhere (the pointerdown below).
document.addEventListener('pointerup', (event) => {
    const root = event.pointerType === 'touch' ? event.target.closest?.(ROOT) : null;
    if (root && targetOf(root) === root) {
        pointer = { x: event.clientX, y: event.clientY };
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
        if (open === root) {
            hide();
        }
    }
});

// Keyboard focus: no pointer to follow, so it goes under the element.
document.addEventListener('focusin', (event) => {
    const root = event.target.closest?.(ROOT);
    if (root && event.target.matches(':focus-visible')) {
        pointer = null;
        show(root);
    }
});

document.addEventListener('focusout', (event) => {
    const root = event.target.closest?.(ROOT);
    if (root && open === root && !root.contains(event.relatedTarget)) {
        hide();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && open) {
        // This Esc was the tooltip's: in a modal, it closes the tooltip and not the modal too.
        event.preventDefault();
        hide();
    }
});
document.addEventListener('pointerdown', () => hide());
// A scroll or a resize (the window, a phone turned) can take the element out of view, move it, or move it out from under
// a pointer that hasn't moved (no pointerout fires then): hide it, or place it again under the element it describes,
// rather than leave it where the element was.
function follow() {
    if (!open) {
        return;
    }
    const bubble = bubbleOf(open);
    const under = pointer ? document.elementsFromPoint(pointer.x, pointer.y).find((node) => !bubble.contains(node)) : null;
    if (!inView(targetOf(open), bubble) || (pointer && !open.contains(under ?? null))) {
        hide({ keepPending: true });
    } else if (!pointer) {
        place(open);
    }
}
window.addEventListener('scroll', follow, true);
window.addEventListener('resize', follow);
// The page's font arriving after a tooltip opened (a slow connection) changes its width: placed again, it stays centred.
document.fonts?.addEventListener('loadingdone', follow);

linkAll();

new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            linkAll(node);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

// A Livewire render puts back the server's markup: link it up again, and keep a showing one where it was.
const hook = (Livewire) => Livewire.hook('morphed', ({ el }) => {
    linkAll(el);
    if (open) {
        place(open);
    }
});
if (window.Livewire) {
    hook(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => hook(window.Livewire));
}
