// Drives <x-widget.sortable>. Each item has a handle and Move up / Move down buttons (Move earlier / later on tiles).
// The handle drags with a mouse, a pen or a finger; pressed with Space, Enter or a click, it picks the item up, the
// arrow keys, Home and End move it, and Space or Enter put it down (Escape, Tab or Cancel put it back). A move happens
// at once on the page, rings the item for a moment, is announced, and fires sortable:move (and sortable-move, for
// wire:sortable-move) on the list with detail { item, position, after, order }. The hidden select, what submits as
// name[] and what wire:model binds, follows. With move-url the move is sent there as JSON, one request at a time: moves
// made meanwhile go next, as the order they leave, so an older answer never overwrites a newer order. An answer that
// isn't 2xx puts the list back in the order last saved, rings the item red and says why; a 409 offers to reload. No
// answer at all leaves the order as it is, since it may have been saved, and says so: the next move sends it again.
// Focus stays on the handle or button that moved it, through a Livewire render too.
// From JS: sortable.move(item, position), by element or id; also on window.sortable.
// Delegated from `document`, so lists added later (a Livewire render, fetched HTML) work without setting up.

import { onLivewireMorph } from '../field';

const LIST = '[data-sortable]';
const ITEM = '[data-sortable-item]';
// Long enough for a slow connection, short enough that an item isn't left busy for good.
const TIMEOUT = 20000;

const listOf = (element) => element.closest(LIST);
// Only this list's own items: a list inside an item would be another list.
const boxOf = (list) => list.querySelector('[data-sortable-items]');
const itemsIn = (list) => [...boxOf(list).children].filter((item) => item.matches(ITEM));
const idOf = (item) => item.dataset.sortableItem;
const labelOf = (item) => item.dataset.label;
const isGrid = (list) => list.dataset.layout === 'grid';
const isStill = (list) => list.hasAttribute('data-readonly') || list.hasAttribute('data-disabled');
const itemById = (list, id) => itemsIn(list).find((item) => idOf(item) === String(id)) ?? null;
// The item as it is on the page now. A Livewire render can draw an item afresh in place of the one the script holds:
// same id, so follow it by that.
// Nothing, once the list itself is off the page.
const current = (list, item) => {
    if (!list.isConnected) {
        return null;
    }

    return item.isConnected && listOf(item) === list ? item : itemById(list, idOf(item));
};
const numbers = () => new Intl.NumberFormat(document.documentElement.lang || undefined);
const handleOf = (item) => item.querySelector('[data-sortable-handle]');

// Whether this item may move now, and if not, why: said out loud, since its handle may still have been reached.
function blocked(item) {
    return isStill(listOf(item)) ? `${labelOf(item)} can't be moved.` : null;
}

function say(list, message) {
    const status = list.querySelector('[data-sortable-status]');
    // Emptied first, so the same message twice is read out again.
    status.textContent = '';
    requestAnimationFrame(() => {
        status.textContent = message;
    });
}

const where = (item) => {
    const items = itemsIn(listOf(item));

    return `${numbers().format(items.indexOf(item) + 1)} of ${numbers().format(items.length)}`;
};

// The Move buttons at the ends: aria-disabled rather than disabled, so the one just pressed keeps focus. And each
// handle's description, the list's instructions: the items are drawn before the list has its id.
function refresh(list) {
    const items = itemsIn(list);
    // One item, or none, has nowhere to go: no handles or buttons for it.
    list.toggleAttribute('data-sortable-ready', ready.has(list) && !isStill(list) && items.length > 1);
    const help = list.querySelector('[data-sortable-help]')?.id;
    items.forEach((item, index) => {
        const ends = { up: index === 0, down: index === items.length - 1 };
        for (const [action, end] of Object.entries(ends)) {
            const button = item.querySelector(`[data-sortable-action="${action}"]`);
            if (button && end) {
                button.setAttribute('aria-disabled', 'true');
            } else {
                button?.removeAttribute('aria-disabled');
            }
        }
        if (help) {
            handleOf(item)?.setAttribute('aria-describedby', help);
        }
    });
}

// The hidden select follows the items, then tells wire:model and anything else listening.
function sync(list) {
    const select = list.querySelector('select[data-sortable-value]');
    if (!select) {
        return;
    }
    select.replaceChildren(...itemsIn(list).map((item) => new Option(idOf(item), idOf(item), true, true)));
    select.dispatchEvent(new Event('input', { bubbles: true }));
    select.dispatchEvent(new Event('change', { bubbles: true }));
}

// --- Motion ------------------------------------------------------------------------------------------------------

// Items glide to a new place, lift as they're dragged, and settle into the place they're let go over; none of it for
// people who ask for less motion, whose items go straight there.
const moving = () => !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const SLIDE = 160;
const LIFT = 150;
const LAND = 180;

// A ring around an item for a moment after it moves, so the eye finds where it went. Red, and held longer, when the
// server sends it back: that answer comes a moment after the move, once the eye has gone elsewhere. Styled in
// item.blade.php; its transition fades it, except for people who ask for less motion.
const MARKED_FOR = { moved: 1200, refused: 2000 };
const marks = new WeakMap();

function mark(item, kind) {
    unmark(item);
    item.setAttribute(`data-sortable-${kind}`, '');
    marks.set(item, setTimeout(() => item.removeAttribute(`data-sortable-${kind}`), MARKED_FOR[kind]));
}

function unmark(item) {
    clearTimeout(marks.get(item));
    item.removeAttribute('data-sortable-moved');
    item.removeAttribute('data-sortable-refused');
}

// Where an element is laid out, leaving out the translate a slide is moving it by: what a slide measures from, and what
// a drag works out its place by, so an item part way through a slide doesn't make the place flicker.
function laidOut(element) {
    const box = element.getBoundingClientRect();
    const [x = 0, y = 0] = getComputedStyle(element).translate.split(' ').map((part) => Number.parseFloat(part) || 0);

    return { left: box.left - x, top: box.top - y, width: box.width, height: box.height };
}

// Runs a change to the list's items, then glides each item it moved from where it was on screen to its new place.
function slide(list, change) {
    if (!moving()) {
        change();

        return;
    }
    const was = new Map(itemsIn(list).map((item) => [item, { shown: item.getBoundingClientRect(), placed: laidOut(item) }]));
    change();
    for (const [item, { shown, placed }] of was) {
        if (!item.isConnected) {
            continue;
        }
        const now = laidOut(item);
        // Not moved by this change: a glide it's in carries on untouched, rather than judder on every pointer move.
        if (Math.abs(now.left - placed.left) < 1 && Math.abs(now.top - placed.top) < 1) {
            continue;
        }
        item.getAnimations().filter((animation) => animation.id === 'sortable-slide').forEach((animation) => animation.cancel());
        const [dx, dy] = [shown.left - now.left, shown.top - now.top];
        if (Math.abs(dx) >= 1 || Math.abs(dy) >= 1) {
            item.animate([{ translate: `${dx}px ${dy}px` }, { translate: '0 0' }], { id: 'sortable-slide', duration: SLIDE, easing: 'ease-out' });
        }
    }
}

// Puts the item at index (counted without the item itself), and keeps focus where it was inside it: taking an element
// out of the page drops its focus. Scrolled into view, except while dragged: the pointer is already there.
function place(item, index, { scroll = true } = {}) {
    const list = listOf(item);
    const focused = item.contains(document.activeElement) ? document.activeElement : null;
    const others = itemsIn(list).filter((other) => other !== item);
    slide(list, () => boxOf(list).insertBefore(item, others[index] ?? null));
    if (focused) {
        focused.focus({ preventScroll: true });
    }
    if (scroll) {
        item.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }
    refresh(list);
}

// Where an item sits, by its neighbour rather than its index: other items may move before it's put back.
function spot(item) {
    const items = itemsIn(listOf(item));
    const index = items.indexOf(item);

    return { index, after: index > 0 ? idOf(items[index - 1]) : null };
}

function putBack(item, before, options = {}) {
    const others = itemsIn(listOf(item)).filter((other) => other !== item);
    const after = before.after === null ? -1 : others.findIndex((other) => idOf(other) === before.after);
    // The neighbour it followed, if still there; otherwise where it was; the top if it led.
    const index = before.after === null ? 0 : after >= 0 ? after + 1 : Math.min(before.index, others.length);
    place(item, index, options);
}

// Puts the items in this order of ids, any not in it after the rest, keeping focus where it was.
function arrange(list, ids) {
    const items = itemsIn(list);
    const rank = (item) => (ids.indexOf(idOf(item)) + 1) || Infinity;
    const sorted = [...items].sort((a, b) => rank(a) - rank(b));
    if (sorted.every((item, index) => item === items[index])) {
        return;
    }
    const focused = list.contains(document.activeElement) ? document.activeElement : null;
    slide(list, () => boxOf(list).append(...sorted));
    focused?.focus({ preventScroll: true });
    refresh(list);
}

// Per list, its saving: the order the server last kept, the version it answered with, whether a request is out, and
// the order to send once it's back. Started from the order drawn, which the server has.
const saves = new WeakMap();
const savesOf = (list) => saves.get(list)
    ?? saves.set(list, { saved: itemsIn(list).map(idOf), version: list.dataset.version ?? null, sending: false, next: null }).get(list);

// Queues the move's order. One request at a time: a move made meanwhile replaces any still waiting, since each sends
// the whole order, and the latest is the one meant.
function save(list, detail) {
    const saving = savesOf(list);
    saving.next = detail;
    if (!saving.sending) {
        send(list);
    }
}

async function send(list) {
    const saving = savesOf(list);
    const detail = saving.next;
    saving.next = null;
    saving.sending = true;
    boxOf(list).setAttribute('aria-busy', 'true');
    // kept, refused (with problem), conflict (with problem) or unsure: no answer, so it may or may not have been saved.
    let outcome = 'kept';
    let problem = null;
    try {
        const response = await fetch(list.dataset.moveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': list.dataset.csrf ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify(saving.version === null ? detail : { ...detail, version: saving.version }),
            signal: AbortSignal.timeout(TIMEOUT),
        });
        const answer = await response.json().catch(() => null);
        const message = typeof answer?.message === 'string' && answer.message !== '' ? answer.message : null;
        // A redirect is the sign-in page or somewhere else that isn't an answer to the move.
        if (response.redirected) {
            [outcome, problem] = ['refused', 'You may have been signed out. Reload the page and try again.'];
        } else if (response.status === 419) {
            [outcome, problem] = ['refused', 'This page has expired. Reload it and try again.'];
        } else if (response.status === 409) {
            [outcome, problem] = ['conflict', message ?? 'The order was changed somewhere else since this page loaded.'];
        } else if (!response.ok) {
            [outcome, problem] = ['refused', message ?? 'It couldn\'t be saved.'];
        } else if (answer?.version !== undefined && answer.version !== null) {
            saving.version = String(answer.version);
        }
    } catch {
        outcome = 'unsure';
    }
    saving.sending = false;
    if (!list.isConnected) {
        return;
    }
    if (outcome === 'kept') {
        saving.saved = detail.order;
    }
    // Moves made meanwhile go next. After no answer too: they send the whole order, which settles the one in doubt.
    if (saving.next && (outcome === 'kept' || outcome === 'unsure')) {
        send(list);

        return;
    }
    boxOf(list).removeAttribute('aria-busy');
    const item = itemById(list, detail.item);
    const label = item ? labelOf(item) : 'the item';
    if (outcome === 'unsure') {
        say(list, `The server couldn't be reached, so the new order may not be saved. Your next move sends it again, or reload the page to see the order saved.`);

        return;
    }
    if (outcome === 'kept') {
        return;
    }
    // Back to the order the server last kept: the moves after this one were made from it, and are dropped with it.
    saving.next = null;
    offerUndo(list, null);
    arrange(list, saving.saved);
    sync(list);
    list.querySelector('[data-sortable-reload]').hidden = outcome !== 'conflict';
    say(list, `Couldn't move ${label}: ${problem} The list is back in the order last saved.`);
    if (item) {
        mark(item, 'refused');
    }
}

// Per list, the last move, while it can still be undone: { item, before, after }.
const undoable = new WeakMap();

function offerUndo(list, move) {
    if (move) {
        undoable.set(list, move);
    } else {
        undoable.delete(list);
    }
    list.querySelector('[data-sortable-undo]').hidden = !move;
}

// The item is where it now sits, having been at before: announced as a move, sent out, and saved, and offered for
// undoing unless it's an undo itself. False when it ended up where it started, which isn't a move.
function settle(item, before, { canUndo = true } = {}) {
    const list = listOf(item);
    const now = spot(item);
    if (now.index === before.index) {
        offerUndo(list, null);
        say(list, `${labelOf(item)} stays at ${where(item)}.`);

        return false;
    }
    const detail = { item: idOf(item), position: now.index, after: now.after, order: itemsIn(list).map(idOf) };
    sync(list);
    for (const name of ['sortable:move', 'sortable-move']) {
        list.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }
    offerUndo(list, canUndo ? { item, before, after: now } : null);
    say(list, `Moved ${labelOf(item)} to ${where(item)}.`);
    mark(item, 'moved');
    if (list.dataset.moveUrl) {
        save(list, detail);
    }

    return true;
}

// Moves the item to index; false when it can't. Past the end means the end.
function moveItem(item, index) {
    const list = listOf(item);
    const problem = blocked(item);
    if (problem) {
        say(list, problem);

        return false;
    }
    if (picked?.item === item) {
        putDown(false);
    }
    const before = spot(item);
    place(item, Math.max(0, Math.min(index, itemsIn(list).length - 1)));

    return settle(item, before);
}

// --- The Move buttons ------------------------------------------------------------------------------------------

document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-sortable-action]');
    const item = button?.closest(ITEM);
    if (!item || button.getAttribute('aria-disabled') === 'true') {
        return;
    }
    const { index } = spot(item);
    moveItem(item, index + (button.dataset.sortableAction === 'up' ? -1 : 1));
});

// --- Picking up with the handle, and placing with the keys --------------------------------------------------------

// One item at a time, on the whole page: { item, list, before }. It moves as it's placed, and only counts as moved
// when it's put down, from where it was picked up: one sortable:move, one save.
let picked = null;
// Enter or Space put the item down on keydown; their keyup would then press the handle again and pick it back up.
let swallowKeyUp = null;

function setPicked(item, on) {
    item.toggleAttribute('data-sortable-picked', on);
    handleOf(item)?.setAttribute('aria-pressed', String(on));
}

function pickUp(item) {
    if (picked) {
        putDown(false);
    }
    const list = listOf(item);
    const problem = blocked(item);
    if (problem) {
        say(list, problem);

        return false;
    }
    offerUndo(list, null);
    unmark(item);
    picked = { item, list, before: spot(item) };
    setPicked(item, true);
    list.querySelector('[data-sortable-cancel]').hidden = false;
    const keys = isGrid(list) ? 'the arrow keys' : 'the up and down arrow keys';
    say(list, `Picked up ${labelOf(item)}, ${where(item)}. Move it with ${keys}, then press Space or Enter to put it down. Escape puts it back.`);

    return true;
}

// Ends placing: puts the item down where it is now (keep), or back where it was picked up.
function putDown(keep) {
    if (!picked) {
        return;
    }
    const { item, list, before } = picked;
    picked = null;
    setPicked(item, false);
    list.querySelector('[data-sortable-cancel]').hidden = true;
    // Gone from the page while being placed (a Livewire render, a script): nothing to put down.
    if (!item.isConnected) {
        return;
    }
    if (keep) {
        settle(item, before);
    } else {
        putBack(item, before);
        say(list, `${labelOf(item)} is back at ${where(item)}.`);
    }
}

// Moves the picked item to index, held at the ends, and says where it is.
function step(index) {
    const { item, list } = picked;
    const last = itemsIn(list).length - 1;
    const to = Math.max(0, Math.min(index, last));
    if (to === itemsIn(list).indexOf(item)) {
        say(list, `${labelOf(item)} is at ${to === 0 ? 'the start' : 'the end'}, ${where(item)}.`);

        return;
    }
    place(item, to);
    say(list, `${where(item)}.`);
}

// How many tiles fit on one row of a grid: those level with the first.
function perRow(list) {
    const items = itemsIn(list);
    const top = items[0] && laidOut(items[0]).top;

    return Math.max(1, items.filter((item) => Math.abs(laidOut(item).top - top) < 2).length);
}

// The handle's click (and Space or Enter on it, which click it) picks the item up, or puts it down if it's the one in
// hand. A click that ends a drag never reaches here.
document.addEventListener('click', (event) => {
    const handle = event.target.closest?.('[data-sortable-handle]');
    const item = handle?.closest(ITEM);
    if (!item || !listOf(item)?.hasAttribute('data-sortable-ready')) {
        return;
    }
    if (picked?.item === item) {
        putDown(true);
    } else {
        pickUp(item);
    }
});

// Capture phase, so the arrows move the item, not the page or focus.
document.addEventListener('keydown', (event) => {
    if (!picked || !picked.list.contains(event.target)) {
        return;
    }
    const { item, list } = picked;
    const index = itemsIn(list).indexOf(item);
    // Left and Right follow the page: in a right-to-left one, the next tile is to the left. In a list they don't move it.
    const rtl = getComputedStyle(list).direction === 'rtl';
    const grid = isGrid(list);
    const row = grid ? perRow(list) : 1;
    const keys = {
        ArrowUp: () => step(index - row),
        ArrowDown: () => step(index + row),
        Home: () => step(0),
        End: () => step(Infinity),
        ...(grid ? {
            ArrowLeft: () => step(index + (rtl ? 1 : -1)),
            ArrowRight: () => step(index + (rtl ? -1 : 1)),
        } : {}),
        Enter: () => putDown(true),
        ' ': () => putDown(true),
        Escape: () => putDown(false),
    };
    if (event.key === 'Tab') {
        putDown(false);

        return;
    }
    if (!keys[event.key]) {
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    if (event.key === 'Enter' || event.key === ' ') {
        swallowKeyUp = event.key;
    }
    keys[event.key]();
}, true);

document.addEventListener('keyup', (event) => {
    if (swallowKeyUp === event.key) {
        swallowKeyUp = null;
        event.preventDefault();
        event.stopPropagation();
    }
}, true);

// Capture phase: while an item is in hand, Cancel puts it back, and so does a click anywhere other than its handle or
// its own Move buttons, which then does what it would have done.
document.addEventListener('click', (event) => {
    if (!picked) {
        return;
    }
    const { item } = picked;
    if (event.target.closest?.('[data-sortable-cancel]') && picked.list.contains(event.target)) {
        event.preventDefault();
        event.stopPropagation();
        handleOf(item)?.focus({ preventScroll: true });
        putDown(false);
    } else if (!item.contains(event.target)) {
        putDown(false);
    }
}, true);

// --- Dragging, with a mouse, a pen or a finger -----------------------------------------------------------------

// A press on a handle, then a few pixels' travel, drags the item: a copy follows the pointer, and the item itself
// waits, faded, where it would land. Letting go puts it there; Escape or a cancelled pointer puts it back. The handle is
// touch-none, so a finger drags it at once and a swipe anywhere else still scrolls the page.
const THRESHOLD = 5;
// How far outside the list the pointer can stray and still place the item: further, letting go puts it back.
const OUTSIDE = 32;
// How near the window's top or bottom the pointer has to be for the page to scroll, and how fast.
const EDGE = 48;
const SPEED = 14;
// Grabbing, wherever the pointer is until it lets go: important, to win over the cursors of what it passes over.
const GRABBING = ['cursor-grabbing!', '**:cursor-grabbing!'];
// The copy that follows the pointer, positioned by transform from the window's top left.
const GHOST = 'pointer-events-none fixed top-0 left-0 z-50 m-0 list-none rotate-1 shadow-xl ring-2 ring-primary';

let press = null;
let drag = null;
// The click a pointer sends after letting go of a drag would otherwise pick the item up.
let swallowClick = false;

// Its place among the others. In a list, after every item whose middle is above the pointer. In a grid, after every
// tile on a row above the pointer's, and those on its row whose middle it has passed, reading the page's way.
function indexAt(list, item, x, y) {
    const others = itemsIn(list).filter((other) => other !== item).map(laidOut);
    if (!isGrid(list)) {
        return others.filter((box) => box.top + box.height / 2 < y).length;
    }
    const rtl = getComputedStyle(list).direction === 'rtl';

    return others.filter((box) => {
        if (box.top + box.height <= y) {
            return true;
        }
        if (box.top > y) {
            return false;
        }
        const middle = box.left + box.width / 2;

        return rtl ? middle > x : middle < x;
    }).length;
}

// Moves the copy to the pointer, and the item to where letting go would put it.
function follow() {
    const { item, list, ghost, x, y, dx, dy } = drag;
    ghost.style.transform = `translate(${x - dx}px, ${y - dy}px)`;
    // Well outside the list, letting go puts it back where it started: show that, rather than where it last was.
    const box = boxOf(list).getBoundingClientRect();
    drag.outside = x < box.left - OUTSIDE || x > box.right + OUTSIDE || y < box.top - OUTSIDE || y > box.bottom + OUTSIDE;
    if (drag.outside) {
        if (spot(item).index !== drag.before.index) {
            putBack(item, drag.before, { scroll: false });
        }

        return;
    }
    const index = indexAt(list, item, x, y);
    if (itemsIn(list).indexOf(item) !== index) {
        place(item, index, { scroll: false });
    }
}

// The nearest box around the list that scrolls up and down, if any: a long list in a panel or a dialog.
function scrollerAround(list) {
    for (let parent = list.parentElement; parent && parent !== document.body && parent !== document.documentElement; parent = parent.parentElement) {
        if (/auto|scroll/.test(getComputedStyle(parent).overflowY) && parent.scrollHeight > parent.clientHeight) {
            return parent;
        }
    }

    return null;
}

// Scrolls the box the list is in while the pointer is near its top or bottom, and the page near the window's. Only
// while the pointer is there: moved away from the edge, it stops.
function scrollNearEdges() {
    if (!drag) {
        return;
    }
    const { y, scroller } = drag;
    let scrolled = false;
    if (scroller) {
        const box = scroller.getBoundingClientRect();
        const by = y < box.top + EDGE ? -SPEED : y > box.bottom - EDGE ? SPEED : 0;
        const room = by < 0 ? scroller.scrollTop > 0 : scroller.scrollTop + scroller.clientHeight < scroller.scrollHeight;
        if (by && room) {
            scroller.scrollBy({ top: by });
            scrolled = true;
        }
    }
    const down = y < EDGE ? -SPEED : y > window.innerHeight - EDGE ? SPEED : 0;
    if (down) {
        window.scrollBy({ top: down });
        scrolled = true;
    }
    if (scrolled) {
        follow();
    }
    drag.frame = requestAnimationFrame(scrollNearEdges);
}

function startDrag(event) {
    // Where the item was pressed, not where the pointer is now: the copy keeps that same point under the pointer.
    const { item, handle, x: pressX, y: pressY } = press;
    press = null;
    const list = listOf(item);
    const box = item.getBoundingClientRect();
    // The ring is about its last move; the copy that follows the pointer has a ring of its own.
    unmark(item);
    const ghost = item.cloneNode(true);
    // Not an item, not focusable, not read out: a picture of one.
    for (const element of [ghost, ...ghost.querySelectorAll('[id]')]) {
        element.removeAttribute('id');
    }
    ghost.removeAttribute('data-sortable-item');
    ghost.removeAttribute('wire:key');
    // Its fields show what the item's show, but aren't fields: no name, so a radio in it can't untick the item's own,
    // and nothing in it could be submitted. Values set after, which a select's clone doesn't keep.
    const fields = item.querySelectorAll('input, select, textarea');
    ghost.querySelectorAll('input, select, textarea').forEach((copy, index) => {
        copy.removeAttribute('name');
        copy.removeAttribute('form');
        copy.removeAttribute('wire:model');
        if (copy.type === 'checkbox' || copy.type === 'radio') {
            copy.checked = fields[index].checked;
        } else if (copy.type !== 'file') {
            copy.value = fields[index].value;
        }
    });
    ghost.setAttribute('aria-hidden', 'true');
    ghost.inert = true;
    ghost.className = `${ghost.className} ${GHOST}`;
    // relative would win over fixed, leaving the copy at the foot of the page instead of under the pointer.
    ghost.classList.remove('relative');
    ghost.style.width = `${box.width}px`;
    // On the page's body, so nothing it sits in moves or clips it. Held in a stand-in for the list, so it still shows
    // its handle and buttons, and keeps the list's direction.
    const holder = Object.assign(document.createElement('div'), { className: 'group/sortable', dir: getComputedStyle(list).direction });
    holder.setAttribute('data-sortable-ready', '');
    holder.append(ghost);
    document.body.append(holder);
    if (moving()) {
        ghost.animate([{ scale: '1' }, { scale: '1.02' }], { duration: LIFT, easing: 'ease-out', fill: 'forwards' });
    }
    drag = { item, list, before: spot(item), ghost, handle, scroller: scrollerAround(list), outside: false, pointerId: event.pointerId, dx: pressX - box.left, dy: pressY - box.top, x: event.clientX, y: event.clientY, frame: 0 };
    if (event.pointerType === 'touch' && navigator.userActivation?.hasBeenActive) {
        // A moment's buzz where the phone has one, as the item comes up under the finger.
        navigator.vibrate?.(10);
    }
    item.setAttribute('data-sortable-dragging', '');
    offerUndo(list, null);
    document.documentElement.classList.add(...GRABBING);
    window.getSelection()?.removeAllRanges();
    // So the pointer's moves and its letting go still reach here off the handle, or off the window. A pointer the
    // browser doesn't know (a script's) can't be captured, and doesn't need to be.
    try {
        handle.setPointerCapture(event.pointerId);
    } catch {
        // Nothing to capture.
    }
    follow();
    drag.frame = requestAnimationFrame(scrollNearEdges);
}

function endDrag(keep) {
    if (!drag) {
        return;
    }
    const { item, list, before, ghost, handle, pointerId, frame } = drag;
    drag = null;
    cancelAnimationFrame(frame);
    item.removeAttribute('data-sortable-dragging');
    document.documentElement.classList.remove(...GRABBING);
    if (handle.hasPointerCapture?.(pointerId)) {
        handle.releasePointerCapture(pointerId);
    }
    if (!item.isConnected) {
        ghost.parentElement.remove();

        return;
    }
    if (keep) {
        settle(item, before);
    } else {
        putBack(item, before);
        say(list, `${labelOf(item)} is back at ${where(item)}.`);
    }
    land(ghost, item);
}

// The copy settles into the item's place, wherever that now is, and the item shows again as it lands. Straight away for
// people who ask for less motion.
function land(ghost, item) {
    if (!moving()) {
        ghost.parentElement.remove();

        return;
    }
    item.setAttribute('data-sortable-landing', '');
    const to = laidOut(item);
    const glide = ghost.animate([
        { transform: ghost.style.transform, scale: '1.02' },
        { transform: `translate(${to.left}px, ${to.top}px)`, scale: '1' },
    ], { duration: LAND, easing: 'ease-in-out' });
    glide.finished.catch(() => {}).finally(() => {
        ghost.parentElement.remove();
        item.removeAttribute('data-sortable-landing');
    });
}

document.addEventListener('pointerdown', (event) => {
    if (event.button !== 0 || !event.isPrimary || drag) {
        return;
    }
    const handle = event.target.closest?.('[data-sortable-handle]');
    const item = handle?.closest(ITEM);
    // Only on a list the script has set up, and never an item that can't move: a press is silent, so is its refusal.
    if (!item || !listOf(item)?.hasAttribute('data-sortable-ready') || blocked(item)) {
        return;
    }
    press = { item, handle, pointerId: event.pointerId, x: event.clientX, y: event.clientY };
});

document.addEventListener('pointermove', (event) => {
    if (press && event.pointerId === press.pointerId && Math.hypot(event.clientX - press.x, event.clientY - press.y) >= THRESHOLD) {
        // Picked up by the keys, then dragged: the drag takes over, from where the keys had left it.
        if (picked?.item === press.item) {
            putDown(false);
        }
        startDrag(event);
    }
    if (drag && event.pointerId === drag.pointerId) {
        drag.x = event.clientX;
        drag.y = event.clientY;
        follow();
    }
});

document.addEventListener('pointerup', (event) => {
    if (press?.pointerId === event.pointerId) {
        press = null;
    }
    if (drag?.pointerId === event.pointerId) {
        endDrag(!drag.outside);
        swallowClick = true;
        // The click comes in the same task as the pointerup; anything later isn't it.
        setTimeout(() => {
            swallowClick = false;
        });
    }
});

document.addEventListener('pointercancel', (event) => {
    if (press?.pointerId === event.pointerId) {
        press = null;
    }
    if (drag?.pointerId === event.pointerId) {
        endDrag(false);
    }
});

document.addEventListener('keydown', (event) => {
    if (drag && event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        endDrag(false);
    }
}, true);

document.addEventListener('click', (event) => {
    if (swallowClick) {
        swallowClick = false;
        event.preventDefault();
        event.stopPropagation();
    }
}, true);

// Dragging over text would otherwise select it.
document.addEventListener('selectstart', (event) => {
    if (drag || press) {
        event.preventDefault();
    }
});

// A finger held on a handle: not the phone's menu for a long press.
document.addEventListener('contextmenu', (event) => {
    if ((press || drag) && event.target.closest?.('[data-sortable-handle]')) {
        event.preventDefault();
    }
});

// --- Undo ----------------------------------------------------------------------------------------------------

const stillThere = (item, after) => spot(item).index === after.index;

// Makes the opposite of the last move, as a move of its own: announced, sent and saved like any other. Not if the item
// has moved since by some other way (a render, a script), which Undo would then get wrong.
document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-sortable-undo]');
    const list = button && listOf(button);
    const last = list && undoable.get(list);
    if (!last) {
        return;
    }
    const item = current(list, last.item);
    if (!item || !stillThere(item, last.after)) {
        offerUndo(list, null);
        say(list, `${labelOf(last.item)} has moved since, so it stays where it is.`);

        return;
    }
    const problem = blocked(item);
    if (problem) {
        say(list, problem);

        return;
    }
    const now = spot(item);
    offerUndo(list, null);
    // The Undo button hides, so focus goes to the item it moved.
    handleOf(item)?.focus({ preventScroll: true });
    putBack(item, last.before);
    settle(item, now, { canUndo: false });
});

// Offered when another change got there first: the page as it's saved now.
document.addEventListener('click', (event) => {
    if (event.target.closest?.('[data-sortable-reload]')) {
        window.location.reload();
    }
});

// --- Focus through a render -------------------------------------------------------------------------------------

// The handle or Move button last focused in an item, by the item's id: a Livewire render that moves the item, or draws
// it afresh, takes focus off it, and the next key press would go nowhere. Forgotten once focus or a press goes
// anywhere else, so a person who clicked away keeps it away.
let focused = null;

const controlOf = (element) => (element.matches('[data-sortable-handle]') ? 'handle' : element.dataset.sortableAction ?? null);

document.addEventListener('focusin', (event) => {
    const item = event.target.closest?.(ITEM);
    const control = item && controlOf(event.target);
    focused = control ? { list: listOf(item), id: idOf(item), control, element: event.target } : null;
});

document.addEventListener('pointerdown', (event) => {
    if (focused && !focused.element.contains(event.target)) {
        focused = null;
    }
}, true);

function refocus() {
    const lost = document.activeElement === null || document.activeElement === document.body;
    if (!focused || !lost || !focused.list.isConnected || focused.element === document.activeElement) {
        return;
    }
    const item = itemById(focused.list, focused.id);
    const control = item && (focused.control === 'handle' ? handleOf(item) : item.querySelector(`[data-sortable-action="${focused.control}"]`));
    control?.focus({ preventScroll: true });
}

// --- Setting up ----------------------------------------------------------------------------------------------

// Set up once per element. Not a data attribute for that: Livewire's morph removes attributes the server didn't render.
const ready = new WeakSet();

function initList(list) {
    if (ready.has(list)) {
        return;
    }
    ready.add(list);
    // What the server has, for putting it back if a move is refused: the order drawn, before any below.
    if (list.dataset.moveUrl) {
        savesOf(list);
    }
    // After a failed submit the select holds the order sent, which the items, drawn in their stored order, take on.
    const select = list.querySelector('select[data-sortable-value]');
    if (select) {
        arrange(list, [...select.options].map((option) => option.value));
    }
    // refresh() shows the handles and buttons (data-sortable-ready). The list keeps that through a Livewire render
    // (wire:ignore.self on it).
    const seen = new Set();
    for (const item of itemsIn(list)) {
        if (seen.has(idOf(item))) {
            console.warn(`Bladewell sortable: two items in one list share the id "${idOf(item)}"; a move would send the wrong one.`, item);
        }
        seen.add(idOf(item));
    }
    refresh(list);
}

export function initSortables(scope = document) {
    scope.querySelectorAll(LIST).forEach(initList);
}

initSortables();

// After the page has changed under the script (a Livewire render, a script of yours): the items in play may have been
// drawn again, without the marks the script set on them, and somewhere else. Follow them by their id, put the marks
// back, drop an Undo the change has made wrong, saying where the item is, and give focus back if the change took it.
function catchUp() {
    if (picked) {
        const item = current(picked.list, picked.item);
        if (!item) {
            putDown(false);
        } else if (item !== picked.item || !item.hasAttribute('data-sortable-picked')) {
            picked.item = item;
            setPicked(item, true);
        }
    }
    if (drag) {
        const item = current(drag.list, drag.item);
        if (!item) {
            endDrag(false);
        } else {
            drag.item = item;
            item.setAttribute('data-sortable-dragging', '');
        }
    }
    for (const list of document.querySelectorAll(LIST)) {
        if (!ready.has(list)) {
            continue;
        }
        if (saves.get(list)?.sending) {
            boxOf(list).setAttribute('aria-busy', 'true');
        }
        // Items taken out or put in by your own script (a remove button, say): what the form submits follows.
        const select = list.querySelector('select[data-sortable-value]');
        if (select && [...select.options].map((option) => option.value).join('\n') !== itemsIn(list).map(idOf).join('\n')) {
            sync(list);
        }
        const last = undoable.get(list);
        const item = last && current(list, last.item);
        if (last && !item) {
            offerUndo(list, null);
        } else if (last && !stillThere(item, last.after)) {
            // A Livewire component that refused the move has drawn the item where it was.
            offerUndo(list, null);
            const back = spot(item).index === last.before.index;
            say(list, `${labelOf(item)} is ${back ? 'back' : 'now'} at ${where(item)}.`);
            mark(item, back ? 'refused' : 'moved');
        } else if (last) {
            last.item = item;
        }
        refresh(list);
    }
    refocus();
}

// Lists added to the page later (a Livewire render or wire:navigate, fetched HTML) set themselves up.
new MutationObserver((records) => {
    // Only what the script itself says changed: nothing for it to catch up on.
    if (records.every((record) => record.target.closest?.('[data-sortable-status]') || record.target.matches?.('[data-sortable-status]'))) {
        return;
    }
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            (node.matches(LIST) ? [node] : node.querySelectorAll(LIST)).forEach(initList);
        }
    }
    catchUp();
}).observe(document.documentElement, { childList: true, subtree: true });

// A Livewire render also takes away attributes the script set and the server didn't draw (the ends' aria-disabled, the
// handles' description): put them back.
onLivewireMorph(() => catchUp());

// --- From scripts --------------------------------------------------------------------------------------------

// sortable.move(item, position): item by element or id, position counted from 0. Returns whether it moved.
function move(target, position) {
    const item = typeof target === 'string' || typeof target === 'number'
        ? document.querySelector(`[data-sortable-item="${CSS.escape(String(target))}"]`)
        : target;
    if (!item?.matches?.(ITEM) || !listOf(item)) {
        console.warn('Bladewell sortable: no such item on this page:', target);

        return false;
    }

    return moveItem(item, Number(position));
}

export const sortable = { move };

window.sortable = sortable;
