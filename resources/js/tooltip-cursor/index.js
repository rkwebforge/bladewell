// Drives <x-widget.tooltip-cursor>: a tooltip that follows the pointer over its element, just below and after it, and
// moves to the other side of the pointer near the window's edges. Keyboard focus puts it under the element instead.
// A finger's tap shows it above the finger; held still a moment, the finger then slides it from one to the next (the
// bars of a chart) without scrolling the page. On a link or a button a long press shows it instead of pressing. Esc
// hides it. Points the element's aria-describedby at it for screen readers. Delegated from `document`, so ones added
// later (a Livewire render, fetched HTML) work without setting up.

const ROOT = '[data-tooltip-cursor]';
const FOCUSABLE = 'a[href], button, input:not([type="hidden"]), select, textarea, summary, [tabindex]:not([tabindex="-1"])';
const SHOW_AFTER_MS = 150;
// Leaving one for the next within this long (the bars of a chart) shows the next at once.
const STILL_READING_MS = 300;
// From the pointer to the tooltip's corner: clear of the cursor arrow, which points down and to the right.
const OFFSET = 14;
const GAP = 8;
const VIEWPORT_EDGE = 8;
// From a finger's point to the tooltip: clear of the fingertip, which covers more than the point it touches.
const FINGER = 32;
// How long a finger holds still before it slides the tooltip instead of scrolling, and how far it can stray meanwhile.
const HOLD = 300;
const SLOP = 10;
// How long a finger holds a link or a button to read its tooltip instead of pressing it (as the tooltip does), and how
// long after letting go the click the browser may still send for it is the hold's, not a press.
const LONG_PRESS = 500;
const HELD_CLICK_MS = 600;

let open = null;
let showTimer = null;
let frame = null;
// Where the pointer is; touch: a finger's, which the tooltip goes above rather than beside.
let pointer = null;
let closedAt = 0;
// A finger on one, waiting out its hold: { pointerId, x, y, timer }; on a link or a button, also { root, held }.
let press = null;
// A held link or button let go, and until when the click that follows is the hold's: { root, until }.
let heldClick = null;
// The finger sliding the tooltip, once its hold is up.
let sliding = null;

const bubbleOf = (root) => root.querySelector(':scope > [data-tooltip-cursor-bubble]');

// While a finger slides the tooltip, its moves are the tooltip's, not the page's to scroll.
const sliders = new WeakSet();
const holdPage = (event) => {
    if (sliding !== null && event.cancelable) {
        event.preventDefault();
    }
};

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
    // On each one, not the document: a listener that can cancel touchmove makes the browser wait for it before
    // scrolling anything under it. A finger's touches stay with what it first pressed, so this one sees the whole slide.
    if (!sliders.has(root)) {
        sliders.add(root);
        root.addEventListener('touchmove', holdPage, { passive: false });
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

// After the pointer and below it, or on the other side of it where that would run off the window. Above a finger,
// centred on it, which would cover it anywhere below: under it only without room above. Without a pointer (keyboard
// focus), centred under the element, or over it without room below.
function place(root) {
    const bubble = bubbleOf(root);
    const width = bubble.offsetWidth;
    const height = bubble.offsetHeight;
    let left;
    let top;
    if (pointer?.touch) {
        left = pointer.x - width / 2;
        top = pointer.y - FINGER - height >= VIEWPORT_EDGE ? pointer.y - FINGER - height : pointer.y + FINGER;
    } else if (pointer) {
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

// Placed again on the next frame, however many moves come before it.
function placeSoon() {
    if (open && !frame) {
        frame = requestAnimationFrame(() => {
            frame = null;
            if (open) {
                place(open);
            }
        });
    }
}

// What a tap or a slide shows a tooltip for: something a tap does nothing else to (a chart, a map, an icon). On a
// button or a link the tap is the action.
const tappable = (root) => root && targetOf(root) === root;

// Ends a finger's press before its hold is up.
function release() {
    clearTimeout(press?.timer);
    press = null;
}

// Whether a point is among the ones beside this one (the bars of its chart: those in the same parent), gaps included.
function among(root, x, y) {
    const boxes = [...root.parentElement.children].filter((other) => other.matches(ROOT)).map((other) => other.getBoundingClientRect());

    return x >= Math.min(...boxes.map((box) => box.left)) && x <= Math.max(...boxes.map((box) => box.right))
        && y >= Math.min(...boxes.map((box) => box.top)) && y <= Math.max(...boxes.map((box) => box.bottom));
}

// The one under a sliding finger: its touches all go to the one it first pressed, so it's found by where it is. In a
// gap between two the last one stays, rather than flicker; off them altogether it goes, and comes back on the next.
function slideTo(x, y) {
    const root = document.elementFromPoint(x, y)?.closest(ROOT);
    if (!tappable(root)) {
        if (open && !among(open, x, y)) {
            hide();
        }

        return;
    }
    pointer = { x, y, touch: true };
    if (root !== open) {
        show(root);
    } else {
        placeSoon();
    }
}

document.addEventListener('pointermove', (event) => {
    if (event.pointerType === 'touch') {
        // Moved before its hold was up: a scroll.
        if (press?.pointerId === event.pointerId && Math.hypot(event.clientX - press.x, event.clientY - press.y) >= SLOP) {
            release();
        }
        if (sliding === event.pointerId) {
            slideTo(event.clientX, event.clientY);
        }

        return;
    }
    pointer = { x: event.clientX, y: event.clientY };
    placeSoon();
});

// Touch has no hover, so a tap shows it above the finger, on one that's tappable. It stays until the next press
// anywhere (the pointerdown below).
document.addEventListener('pointerup', (event) => {
    if (event.pointerType !== 'touch') {
        return;
    }
    if (press?.pointerId === event.pointerId) {
        if (press.held) {
            heldClick = { root: press.root, until: performance.now() + HELD_CLICK_MS };
        }
        release();
    }
    // A slide leaves it on the one the finger lifted from, not the one it started on.
    if (sliding === event.pointerId) {
        sliding = null;

        return;
    }
    const root = event.target.closest?.(ROOT);
    if (tappable(root)) {
        pointer = { x: event.clientX, y: event.clientY, touch: true };
        show(root);
    }
});

document.addEventListener('pointercancel', (event) => {
    if (press?.pointerId === event.pointerId) {
        release();
    }
    if (sliding === event.pointerId) {
        sliding = null;
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
// Any press hides the one showing. A finger held still on a tappable one shows it, and from then on slides it. Held
// longer on a link or a button, whose tap is its action, it shows that one's, and letting go doesn't press it.
document.addEventListener('pointerdown', (event) => {
    hide();
    release();
    heldClick = null;
    if (event.pointerType !== 'touch') {
        return;
    }
    // A second finger: a pinch, not a slide.
    if (!event.isPrimary) {
        sliding = null;

        return;
    }
    const root = event.target.closest?.(ROOT);
    if (tappable(root)) {
        const { pointerId, clientX: x, clientY: y } = event;
        press = { pointerId, x, y, timer: setTimeout(() => {
            press = null;
            sliding = pointerId;
            slideTo(x, y);
        }, HOLD) };

        return;
    }
    const target = root && targetOf(root);
    if (target?.contains(event.target) && !target.matches(':disabled')) {
        const { pointerId, clientX: x, clientY: y } = event;
        press = { pointerId, x, y, root, held: false, timer: setTimeout(() => {
            press.held = true;
            pointer = { x, y, touch: true };
            show(root);
        }, LONG_PRESS) };
    }
});

document.addEventListener('click', (event) => {
    if (heldClick && performance.now() < heldClick.until && heldClick.root.contains(event.target)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
    heldClick = null;
}, true);

// Held on one, a finger brings up the phone's menu for it or selects its text: not while it shows the tooltip.
for (const type of ['contextmenu', 'selectstart']) {
    document.addEventListener(type, (event) => {
        if (press || sliding !== null) {
            event.preventDefault();
        }
    });
}
// A scroll or a resize (the window, a phone turned) can take the element out of view, move it, or move it out from under
// a pointer that hasn't moved (no pointerout fires then): hide it, or place it again, rather than leave it where the
// element was. Placed again at the pointer too: a finger doesn't move on to place it, and the font arriving late (a slow
// connection, a fresh browser) widens it, which would leave it off the finger's centre.
function follow() {
    if (!open) {
        return;
    }
    const bubble = bubbleOf(open);
    const under = pointer ? document.elementsFromPoint(pointer.x, pointer.y).find((node) => !bubble.contains(node)) : null;
    if (!inView(targetOf(open), bubble) || (pointer && !open.contains(under ?? null))) {
        hide({ keepPending: true });
    } else {
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
