// Drives <x-widget.kanban>. Cards drag with a mouse or pen, or with a finger held still on one first. Each card's
// "Move…" button opens a menu, built here as it opens from the board as it is
// then: the other columns (to the end of one), and up, down, to the top or to the bottom within its own. A move
// happens at once on the page, is announced, fires kanban:move (and kanban-move, for wire:kanban-move) on the board
// with detail { card, from, to, position, after }, and with move-url is sent there as JSON. An answer that isn't
// 2xx, or no answer, puts the card back where it was and says why; the card is busy until then. Readonly boards,
// readonly columns and locked cards never move.
// From JS: kanban.move(card, column, position), by element or id; also on window.kanban.
// Delegated from `document`, so boards added later (a Livewire render, fetched HTML) work without setting up.

const BOARD = '[data-kanban]';
const COLUMN = '[data-kanban-column]';
const CARD = '[data-kanban-card]';
// Long enough for a slow connection, short enough that a card isn't left busy for good.
const TIMEOUT = 20000;

const boardOf = (element) => element.closest(BOARD);
// Only this board's own columns and cards: a board inside a card would be another board.
const columnsOf = (board) => [...board.querySelectorAll(COLUMN)].filter((column) => boardOf(column) === board);
const listOf = (column) => column.querySelector('[data-kanban-cards]');
const cardsIn = (column) => [...listOf(column).children].filter((card) => card.matches(CARD));
const columnOf = (card) => card.closest(COLUMN);
const idOf = (element) => element.dataset.kanbanCard ?? element.dataset.kanbanColumn;
const titleOf = (column) => column.dataset.title;
const isReadonly = (element) => element.hasAttribute('data-readonly');
const columnById = (board, id) => columnsOf(board).find((column) => idOf(column) === String(id)) ?? null;
const cardById = (board, id) => columnsOf(board).flatMap(cardsIn).find((card) => idOf(card) === String(id)) ?? null;
// The card as it is on the page now. A Livewire render whose order differs from the page's doesn't move a card: it
// takes it out of one column and draws a new one in the other. That one has the same id, so follow it by its id.
const current = (board, card) => (card.isConnected ? card : cardById(board, idOf(card)));
// Per board, the ids of the cards whose move is being saved. By id, not element, and not read from aria-busy, which a
// render takes away.
const saving = new WeakMap();
const savingIn = (board) => saving.get(board) ?? saving.set(board, new Set()).get(board);
const isBusy = (card) => savingIn(boardOf(card)).has(idOf(card));
const numbers = () => new Intl.NumberFormat(document.documentElement.lang || undefined);

// Whether this card may move now, and if not, why: said out loud, since its button may still have been reached.
function blocked(card) {
    const board = boardOf(card);
    if (isReadonly(board) || card.hasAttribute('data-locked') || isReadonly(columnOf(card))) {
        return `${card.dataset.label} can't be moved.`;
    }

    return isBusy(card) ? `${card.dataset.label} is still being saved.` : null;
}

function say(board, message) {
    const status = board.querySelector('[data-kanban-status]');
    // Emptied first, so the same message twice is read out again.
    status.textContent = '';
    requestAnimationFrame(() => {
        status.textContent = message;
    });
}

// The column's count, and its jump button's.
function count(column) {
    const text = numbers().format(cardsIn(column).length);
    column.querySelector('[data-kanban-count]').textContent = text;
    const jump = [...boardOf(column).querySelectorAll('[data-kanban-jump] button')].find((button) => button.dataset.kanbanJumpTo === idOf(column));
    jump?.querySelector('[data-kanban-jump-count]')?.replaceChildren(text);
}

// --- Motion ------------------------------------------------------------------------------------------------------

// Cards glide to a new place, lift as they're dragged, and settle into the slot they're let go over; none of it for
// people who ask for less motion, whose cards go straight there.
const moving = () => !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const SLIDE = 160;
const LIFT = 150;
const LAND = 180;

// Where an element is laid out, leaving out the translate a slide is moving it by: what a slide measures from, and what
// a drag works out its place by, so a card part way through a slide doesn't make the place flicker.
function laidOut(element) {
    const box = element.getBoundingClientRect();
    const [x = 0, y = 0] = getComputedStyle(element).translate.split(' ').map((part) => Number.parseFloat(part) || 0);

    return { left: box.left - x, top: box.top - y, width: box.width, height: box.height };
}

// Runs a change to the board's cards, then glides each card that it moved from where it was on screen to its new
// place. From where it is on screen, not where it was laid out: a card already gliding carries on from there.
function slide(board, change) {
    if (!moving()) {
        change();

        return;
    }
    const cards = columnsOf(board).flatMap(cardsIn);
    // Where each card is on screen (part way through a glide, perhaps), and where it's laid out.
    const was = new Map(cards.map((card) => [card, { shown: card.getBoundingClientRect(), placed: laidOut(card) }]));
    change();
    for (const [card, { shown, placed }] of was) {
        if (!card.isConnected) {
            continue;
        }
        const now = laidOut(card);
        // Not moved by this change: a glide it's in carries on untouched. Starting it again on every pointer move
        // would make it judder.
        if (Math.abs(now.left - placed.left) < 1 && Math.abs(now.top - placed.top) < 1) {
            continue;
        }
        card.getAnimations().filter((animation) => animation.id === 'kanban-slide').forEach((animation) => animation.cancel());
        const [dx, dy] = [shown.left - now.left, shown.top - now.top];
        if (Math.abs(dx) >= 1 || Math.abs(dy) >= 1) {
            card.animate([{ translate: `${dx}px ${dy}px` }, { translate: '0 0' }], { id: 'kanban-slide', duration: SLIDE, easing: 'ease-out' });
        }
    }
}

// Puts the card at index in column (counted without the card itself), and keeps focus on it if it had it: taking an
// element out of the page drops its focus. Scrolled into view, except while dragged: the pointer is already there.
function place(card, column, index, { scroll = true } = {}) {
    const focused = card.contains(document.activeElement) ? document.activeElement : null;
    const from = columnOf(card);
    const others = cardsIn(column).filter((other) => other !== card);
    slide(boardOf(column), () => listOf(column).insertBefore(card, others[index] ?? null));
    count(column);
    if (from !== column) {
        count(from);
    }
    if (focused) {
        focused.focus({ preventScroll: true });
    }
    if (scroll) {
        card.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }
}

// Where a card sits, by its neighbour rather than its index: other cards may move before it's put back.
function spot(card) {
    const column = columnOf(card);
    const cards = cardsIn(column);
    const index = cards.indexOf(card);

    return { column, index, after: index > 0 ? idOf(cards[index - 1]) : null };
}

function putBack(card, before, options = {}) {
    const board = boardOf(card);
    const column = columnById(board, idOf(before.column)) ?? before.column;
    const cards = cardsIn(column).filter((other) => other !== card);
    const after = before.after === null ? -1 : cards.findIndex((other) => idOf(other) === before.after);
    // The neighbour it followed, if still there; otherwise where it was; the top if it led.
    const index = before.after === null ? 0 : after >= 0 ? after + 1 : Math.min(before.index, cards.length);
    place(card, column, index, options);
}

async function save(board, moved, detail, before) {
    savingIn(board).add(idOf(moved));
    moved.setAttribute('aria-busy', 'true');
    let problem = null;
    try {
        const response = await fetch(board.dataset.moveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': board.dataset.csrf ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify(detail),
            signal: AbortSignal.timeout(TIMEOUT),
        });
        // A redirect is the sign-in page or somewhere else that isn't an answer to the move.
        if (response.redirected) {
            problem = 'You may have been signed out. Reload the page and try again.';
        } else if (response.status === 419) {
            problem = 'This page has expired. Reload it and try again.';
        } else if (!response.ok) {
            const answer = await response.json().catch(() => null);
            problem = typeof answer?.message === 'string' && answer.message !== '' ? answer.message : 'It couldn\'t be saved.';
        }
    } catch {
        problem = 'The server couldn\'t be reached.';
    }
    savingIn(board).delete(idOf(moved));
    // Gone from the page meanwhile (a render, a script): nothing to put back.
    const card = current(board, moved);
    if (!card) {
        return;
    }
    card.removeAttribute('aria-busy');
    if (problem) {
        // Already undone, by the server: nothing left to offer.
        if (undoable.get(board) && idOf(undoable.get(board).card) === idOf(card)) {
            offerUndo(board, null);
        }
        putBack(card, before);
        say(board, `Couldn't move ${card.dataset.label}: ${problem} It's back in ${titleOf(before.column)}.`);
    }
}

const where = (card) => {
    const column = columnOf(card);

    return `${titleOf(column)}, ${numbers().format(cardsIn(column).indexOf(card) + 1)} of ${numbers().format(cardsIn(column).length)}`;
};

// Per board, the last move, while it can still be undone: { card, before, after }.
const undoable = new WeakMap();

function offerUndo(board, move) {
    if (move) {
        undoable.set(board, move);
    } else {
        undoable.delete(board);
    }
    board.querySelector('[data-kanban-undo]').hidden = !move;
}

// The card is where it now sits, having been at before: announced as a move, sent out, and saved, and offered for
// undoing unless it's an undo itself. False when it ended up where it started, which isn't a move.
function settle(card, before, { canUndo = true } = {}) {
    const board = boardOf(card);
    const now = spot(card);
    if (idOf(now.column) === idOf(before.column) && now.index === before.index) {
        offerUndo(board, null);
        say(board, `${card.dataset.label} stays in ${titleOf(now.column)}.`);

        return false;
    }
    const detail = { card: idOf(card), from: idOf(before.column), to: idOf(now.column), position: now.index, after: now.after };
    for (const name of ['kanban:move', 'kanban-move']) {
        board.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }
    offerUndo(board, canUndo ? { card, before, after: now } : null);
    say(board, `Moved ${card.dataset.label} to ${where(card)}.`);
    if (board.dataset.moveUrl) {
        save(board, card, detail, before);
    }

    return true;
}

// Whether the card may go to this column, and if not, says why.
function allowed(card, column) {
    const board = boardOf(card);
    const problem = blocked(card) ?? (boardOf(column) !== board || isReadonly(column) ? `${card.dataset.label} can't go to ${titleOf(column)}.` : null);
    if (problem) {
        say(board, problem);
    }

    return problem === null;
}

// Moves the card to index in column; false when it can't. Index past the end means the end.
function moveCard(card, column, index) {
    if (!allowed(card, column)) {
        return false;
    }
    const before = spot(card);
    place(card, column, Math.max(0, Math.min(index, cardsIn(column).filter((other) => other !== card).length)));

    return settle(card, before);
}

// --- The "Move…" menu --------------------------------------------------------------------------------------

function menuItem(board, text, data) {
    const item = board.querySelector('template[data-kanban-templates]').content.querySelector('[data-kanban-item]').cloneNode(true);
    item.querySelector('span').textContent = text;
    Object.assign(item.dataset, data);

    return item;
}

function divider(board) {
    return board.querySelector('template[data-kanban-templates]').content.querySelector('[role="separator"]').cloneNode(true);
}

// Built as the menu opens, after the dropdown's own handler, so it lists the columns and the card's place as they
// are now. A choice is handled on click below.
document.addEventListener('beforetoggle', (event) => {
    const menu = event.target;
    const card = event.newState === 'open' && menu.matches?.('[data-dropdown-menu]') ? menu.closest(CARD) : null;
    const board = card && boardOf(card);
    if (!board || !menu.closest('[data-kanban-move]')) {
        return;
    }
    const here = columnOf(card);
    const index = cardsIn(here).indexOf(card);
    const last = cardsIn(here).length - 1;
    const away = columnsOf(board)
        .filter((column) => column !== here && !isReadonly(column))
        .map((column) => menuItem(board, `Move to ${titleOf(column)}`, { kanbanTo: idOf(column) }));
    const steps = [
        index > 0 && menuItem(board, 'Move up', { kanbanIndex: String(index - 1) }),
        index < last && menuItem(board, 'Move down', { kanbanIndex: String(index + 1) }),
    ].filter(Boolean);
    // To the top or bottom only when that's more than one step away: one step is Move up or Move down. Grouped apart
    // from them, so a slip of the arrow key or the finger doesn't send a card to the far end.
    const jumps = [
        index > 1 && menuItem(board, 'Move to top', { kanbanIndex: '0' }),
        index < last - 1 && menuItem(board, 'Move to bottom', { kanbanIndex: String(last) }),
    ].filter(Boolean);
    // Picking up comes first when there's anywhere to go: it reaches every place, the others are shortcuts.
    const pick = away.length || steps.length ? [menuItem(board, 'Pick up and place', { kanbanPick: '' })] : [];
    const items = [pick, away, steps, jumps].filter((group) => group.length).flatMap((group, i) => (i ? [divider(board), ...group] : group));
    if (items.length === 0) {
        const none = menuItem(board, 'Nowhere to move it', {});
        none.setAttribute('aria-disabled', 'true');
        items.push(none);
    }
    menu.replaceChildren(...items);
}, true);

document.addEventListener('click', (event) => {
    const item = event.target.closest?.('[data-kanban-item]');
    const card = item?.closest(CARD);
    if (!card || item.getAttribute('aria-disabled') === 'true') {
        return;
    }
    if (item.dataset.kanbanPick !== undefined) {
        pickUp(card);
    } else if (item.dataset.kanbanTo !== undefined) {
        const column = columnById(boardOf(card), item.dataset.kanbanTo);
        if (column) {
            moveCard(card, column, Infinity);
        }
    } else if (item.dataset.kanbanIndex !== undefined) {
        moveCard(card, columnOf(card), Number(item.dataset.kanbanIndex));
    }
});

// --- Picking up and placing ------------------------------------------------------------------------------------

// One card at a time, on the whole page: { card, board, before }. The card moves as it's placed, and only counts as
// moved when it's put down, from where it was picked up: one kanban:move, one save.
let picked = null;
// Enter or Space put the card down on keydown; their keyup would then press the move button and open its menu.
let swallowKeyUp = null;

// The "Move here" targets, for a finger or a pointer. Keys move the card itself, so these are hidden from screen
// readers, and out of the Tab order.
const SLOT = 'border-primary/60 text-primary hover:bg-primary/10 focus-visible:ring-primary flex h-9 w-full items-center justify-center rounded-xl border-2 border-dashed text-xs font-medium outline-none focus-visible:ring-2';

function drawSlots() {
    const { card } = picked;
    const board = boardOf(card);
    // The targets push the cards apart: they glide apart, rather than jump.
    slide(board, () => {
        board.querySelectorAll('[data-kanban-slot]').forEach((slot) => slot.remove());
        for (const column of columnsOf(board).filter((other) => !isReadonly(other))) {
            const others = cardsIn(column).filter((other) => other !== card);
            const here = column === columnOf(card) ? cardsIn(column).indexOf(card) : -1;
            for (let index = 0; index <= others.length; index++) {
                // Right beside the card would leave it where it is.
                if (index === here) {
                    continue;
                }
                const slot = Object.assign(document.createElement('li'), { className: 'list-none' });
                slot.setAttribute('data-kanban-slot', '');
                slot.setAttribute('aria-hidden', 'true');
                const button = Object.assign(document.createElement('button'), { type: 'button', tabIndex: -1, className: SLOT, textContent: 'Move here' });
                Object.assign(button.dataset, { kanbanSlotColumn: idOf(column), kanbanSlotIndex: String(index) });
                slot.append(button);
                listOf(column).insertBefore(slot, others[index] ?? null);
            }
        }
    });
}

function setPlacing(board, on) {
    board.toggleAttribute('data-kanban-placing', on);
    holdHeight(board, on);
    board.querySelector('[data-kanban-cancel]').hidden = !on;
}

function pickUp(card) {
    if (picked) {
        putDown(false);
    }
    const board = boardOf(card);
    const problem = blocked(card);
    if (problem) {
        say(board, problem);

        return false;
    }
    offerUndo(board, null);
    picked = { card, board, before: spot(card) };
    card.setAttribute('data-kanban-picked', '');
    setPlacing(board, true);
    drawSlots();
    say(board, `Picked up ${card.dataset.label}, ${where(card)}. Choose where it goes: the arrow keys and Enter, or Move here. Escape or Cancel puts it back.`);

    return true;
}

// Ends placing: puts the card down where it is now (keep), or back where it was picked up.
function putDown(keep) {
    if (!picked) {
        return;
    }
    const { card, board, before } = picked;
    picked = null;
    card.removeAttribute('data-kanban-picked');
    slide(board, () => board.querySelectorAll('[data-kanban-slot]').forEach((slot) => slot.remove()));
    setPlacing(board, false);
    // Gone from the page while being placed (a Livewire render, a script): nothing to put down.
    if (!card.isConnected) {
        return;
    }
    if (keep) {
        settle(card, before);
    } else {
        putBack(card, before);
        say(board, `${card.dataset.label} is back in ${where(card)}.`);
    }
}

// Moves the picked card one step and says where it is; the board's edges and readonly columns stop it.
function step(column, index) {
    const { card } = picked;
    if (!column || isReadonly(column)) {
        return;
    }
    place(card, column, Math.max(0, Math.min(index, cardsIn(column).filter((other) => other !== card).length)));
    drawSlots();
    say(boardOf(card), `${where(card)}.`);
}

// The next column that takes cards, towards the end of the board (+1) or the start (-1).
function nextColumn(card, direction) {
    const columns = columnsOf(boardOf(card));
    const from = columns.indexOf(columnOf(card));
    const ahead = direction > 0 ? columns.slice(from + 1) : columns.slice(0, from).reverse();

    return ahead.find((column) => !isReadonly(column)) ?? null;
}

// Capture phase, before the dropdown's own keys: while placing, the arrows move the card, not focus or a menu.
document.addEventListener('keydown', (event) => {
    if (!picked || !boardOf(picked.card)?.contains(event.target)) {
        return;
    }
    const { card } = picked;
    const column = columnOf(card);
    const index = cardsIn(column).indexOf(card);
    // Left and Right follow the page: in a right-to-left one, the next column is to the left.
    const forward = getComputedStyle(boardOf(card)).direction === 'rtl' ? 'ArrowLeft' : 'ArrowRight';
    const keys = {
        ArrowUp: () => step(column, index - 1),
        ArrowDown: () => step(column, index + 1),
        Home: () => step(column, 0),
        End: () => step(column, Infinity),
        ArrowLeft: () => step(nextColumn(card, forward === 'ArrowLeft' ? 1 : -1), index),
        ArrowRight: () => step(nextColumn(card, forward === 'ArrowRight' ? 1 : -1), index),
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

// Capture phase: while placing, a click inside the board places the card or cancels, and does nothing else, so a
// stray tap can't follow a card's link or open a menu. A click outside the board puts the card back.
document.addEventListener('click', (event) => {
    if (!picked) {
        return;
    }
    const board = boardOf(picked.card);
    if (!board?.contains(event.target)) {
        putDown(false);

        return;
    }
    event.preventDefault();
    event.stopPropagation();
    const slot = event.target.closest('[data-kanban-slot] button');
    if (slot) {
        place(picked.card, columnById(board, slot.dataset.kanbanSlotColumn), Number(slot.dataset.kanbanSlotIndex));
        picked.card.querySelector('[data-dropdown-trigger]')?.focus({ preventScroll: true });
        putDown(true);
    } else if (event.target.closest('[data-kanban-cancel]') || picked.card.contains(event.target)) {
        picked.card.querySelector('[data-dropdown-trigger]')?.focus({ preventScroll: true });
        putDown(false);
    }
}, true);

// --- Dragging, with a mouse, a pen or a finger -----------------------------------------------------------------

// A press on a card that can move, then a few pixels' travel, drags it: a copy follows the pointer, and the card itself
// waits, faded, where it would land. Letting go puts it there. Escape, a cancelled pointer, or letting go off the board
// or over a readonly column puts it back. A finger has to hold still on the card first, so a swipe still scrolls.
const THRESHOLD = 5;
// How long a finger holds still before the card comes up, and how far it can stray meanwhile: further is a scroll.
// Under Android's 400ms, so the card comes up before the phone's own long-press menu would.
const HOLD = 350;
const SLOP = 10;
// How near an edge the pointer has to be for the board or the page to scroll, and how fast.
const EDGE = 48;
const SPEED = 14;
// A press on these inside a card is theirs: a link, a button, a field, text someone means to select.
const OWN_PRESS = 'a, button, input, select, textarea, label, summary, [contenteditable], [tabindex]:not([tabindex="-1"])';
// Grabbing, wherever the pointer is until it lets go: important, to win over the grab and pointer cursors of the
// cards and buttons it passes over.
const GRABBING = ['cursor-grabbing!', '**:cursor-grabbing!'];
// The copy that follows the pointer, positioned by transform from the window's top left.
const GHOST = 'pointer-events-none fixed top-0 left-0 z-50 m-0 list-none rotate-2 shadow-xl ring-2 ring-primary';

let press = null;
let drag = null;
// The click a mouse sends after letting go of a drag would otherwise follow a link or open a menu.
let swallowClick = false;

// Ends a press that hasn't become a drag, and the hold a finger's press is waiting out.
function release() {
    clearTimeout(press?.timer);
    press = null;
}

// While a finger drags, its moves are the card's, not the page's to scroll. Bound on each board's row of columns, not
// the document: a listener that can cancel touchmove makes the browser wait for it before scrolling anything under it.
// Also on the element the finger first pressed while it drags, since its touches go on to it even once a render has
// taken it off the page, and from there they no longer reach the row.
const holdPage = (event) => {
    if (drag?.touch && event.cancelable) {
        event.preventDefault();
    }
};

// The column under the pointer. Columns are only as tall as their cards, so below a short one, still in the row and
// within its width, counts as that column too: its lane, where letting go puts the card at its end.
const columnAt = (board, x, y) => {
    const column = document.elementFromPoint(x, y)?.closest(COLUMN);
    if (column) {
        return boardOf(column) === board ? column : null;
    }
    const row = scrollerOf(board).getBoundingClientRect();
    if (y < row.top || y > row.bottom || x < row.left || x > row.right) {
        return null;
    }

    return columnsOf(board).find((lane) => {
        const box = lane.getBoundingClientRect();

        return x >= box.left && x <= box.right && y >= box.top;
    }) ?? null;
};

// Moves the ghost to the pointer, and the card to where letting go would put it.
function follow() {
    const { card, board, ghost, x, y, dx, dy } = drag;
    // Only where it is: its tilt and lift are its rotate and scale, so they animate apart from this.
    ghost.style.transform = `translate(${x - dx}px, ${y - dy}px)`;
    const column = columnAt(board, x, y);
    if (!column || isReadonly(column)) {
        // Over a readonly column or off the board, letting go puts it back; in the gap between columns it keeps its
        // last place.
        drag.refused = column !== null || !board.contains(document.elementFromPoint(x, y));
        // Letting go now would put it back where it started: show that, rather than the column it last passed over.
        // Once: it's then home, and every later pointer move would only send it there again.
        if (drag.refused && !stillThere(card, drag.before)) {
            putBack(card, drag.before, { scroll: false });
        }

        return;
    }
    drag.refused = false;
    // Its place among the others: after every card whose middle is above the pointer.
    const index = cardsIn(column).filter((other) => {
        const box = laidOut(other);

        return other !== card && box.top + box.height / 2 < y;
    }).length;
    if (columnOf(card) !== column || cardsIn(column).indexOf(card) !== index) {
        place(card, column, index, { scroll: false });
    }
}

// Scrolls the board sideways and the page up or down while the pointer is near their edges.
function scrollNearEdges() {
    if (!drag) {
        return;
    }
    const { board, x, y } = drag;
    const columns = board.querySelector('[data-kanban-columns]');
    const box = columns.getBoundingClientRect();
    const sideways = x < box.left + EDGE ? -SPEED : x > box.right - EDGE ? SPEED : 0;
    const down = y < EDGE ? -SPEED : y > window.innerHeight - EDGE ? SPEED : 0;
    if (sideways) {
        columns.scrollBy({ left: sideways });
    }
    if (down) {
        window.scrollBy({ top: down });
    }
    if (sideways || down) {
        follow();
    }
    drag.frame = requestAnimationFrame(scrollNearEdges);
}

function startDrag(event) {
    // Where the card was pressed, not where the pointer is now: the copy keeps that same point under the pointer.
    const { card, target, x: pressX, y: pressY } = press;
    release();
    const touch = event.pointerType === 'touch';
    const box = card.getBoundingClientRect();
    const ghost = card.cloneNode(true);
    // Not a card, not focusable, not read out: a picture of one.
    for (const element of [ghost, ...ghost.querySelectorAll('[id]')]) {
        element.removeAttribute('id');
    }
    ghost.removeAttribute('data-kanban-card');
    ghost.removeAttribute('wire:key');
    ghost.setAttribute('aria-hidden', 'true');
    ghost.inert = true;
    ghost.className = `${ghost.className} ${GHOST}`;
    // The card is relative (its move button sits in its corner), and that would win over fixed, leaving the copy at
    // the foot of the page instead of under the pointer.
    ghost.classList.remove('relative');
    ghost.style.width = `${box.width}px`;
    document.body.append(ghost);
    // Lifted off the board: it tilts and grows a little as it comes up.
    if (moving()) {
        ghost.animate([{ rotate: '0deg', scale: '1' }, { rotate: '2deg', scale: '1.03' }], { duration: LIFT, easing: 'ease-out', fill: 'forwards' });
    }
    drag = { card, board: boardOf(card), before: spot(card), ghost, pointerId: event.pointerId, touch, target, dx: pressX - box.left, dy: pressY - box.top, x: event.clientX, y: event.clientY, refused: false, frame: 0 };
    if (touch) {
        target.addEventListener('touchmove', holdPage, { passive: false });
        // A moment's buzz where the phone has one, as the card comes up under the finger. Only once the page has been
        // tapped: before that, the browser refuses it and says so in the console.
        if (navigator.userActivation?.hasBeenActive) {
            navigator.vibrate?.(10);
        }
    }
    card.setAttribute('data-kanban-dragging', '');
    drag.board.setAttribute('data-kanban-moving', '');
    holdHeight(drag.board, true);
    offerUndo(drag.board, null);
    document.documentElement.classList.add(...GRABBING);
    window.getSelection()?.removeAllRanges();
    // So the pointer's moves and its letting go still reach here off the card, or off the window. A pointer the
    // browser doesn't know (a script's) can't be captured, and doesn't need to be.
    try {
        card.setPointerCapture(event.pointerId);
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
    const { card, board, before, ghost, pointerId, target, frame } = drag;
    drag = null;
    cancelAnimationFrame(frame);
    target.removeEventListener('touchmove', holdPage);
    card.removeAttribute('data-kanban-dragging');
    board.removeAttribute('data-kanban-moving');
    document.documentElement.classList.remove(...GRABBING);
    if (card.hasPointerCapture?.(pointerId)) {
        card.releasePointerCapture(pointerId);
    }
    if (!card.isConnected) {
        ghost.remove();
        holdHeight(board, false);

        return;
    }
    if (keep) {
        settle(card, before);
    } else {
        putBack(card, before);
        say(board, `${card.dataset.label} is back in ${where(card)}.`);
    }
    land(ghost, card, () => holdHeight(board, false));
}

// The copy settles into the card's place, wherever that now is (the slot it was let go over, or back where it
// started), and the card shows again as it lands. Straight away for people who ask for less motion.
function land(ghost, card, done) {
    if (!moving()) {
        ghost.remove();
        done();

        return;
    }
    card.setAttribute('data-kanban-landing', '');
    const to = laidOut(card);
    const glide = ghost.animate([
        { transform: ghost.style.transform, rotate: '2deg', scale: '1.03' },
        { transform: `translate(${to.left}px, ${to.top}px)`, rotate: '0deg', scale: '1' },
    ], { duration: LAND, easing: 'ease-in-out' });
    glide.finished.catch(() => {}).finally(() => {
        ghost.remove();
        card.removeAttribute('data-kanban-landing');
        done();
    });
}

// The columns stretch to the tallest. A card taken out of the tallest one would shrink the row, and with it the page
// below, then grow it again as the card comes back: the row keeps the height it had while a card is in hand, and only
// takes its new one once the card is down.
function holdHeight(board, hold) {
    // Not let go while another card is in hand on this board: a landing that ends during the next drag, say.
    if (!hold && (drag?.board === board || picked?.board === board)) {
        return;
    }
    const row = scrollerOf(board);
    row.style.minHeight = hold ? `${row.offsetHeight}px` : '';
}

document.addEventListener('pointerdown', (event) => {
    const touch = event.pointerType === 'touch';
    // A second finger: a pinch, not a drag.
    if (touch && !event.isPrimary) {
        release();

        return;
    }
    if (event.button !== 0 || picked || drag) {
        return;
    }
    const card = event.target.closest?.(CARD);
    const own = event.target.closest?.(OWN_PRESS);
    // Only on a board the script has set up, and never one that can't move: a press is silent, so is its refusal.
    if (!card || !boardOf(card)?.hasAttribute('data-kanban-ready') || (own && card.contains(own)) || blocked(card)) {
        return;
    }
    release();
    press = { card, target: event.target, pointerId: event.pointerId, touch, x: event.clientX, y: event.clientY, timer: 0 };
    if (touch) {
        const { pointerId, x, y } = press;
        press.timer = setTimeout(() => {
            // Taken off the page by a render meanwhile, or now being saved: nothing to pick up.
            if (!card.isConnected || blocked(card)) {
                release();
            } else {
                startDrag({ pointerId, pointerType: 'touch', clientX: x, clientY: y });
            }
        }, HOLD);
    }
});

document.addEventListener('pointermove', (event) => {
    if (press && event.pointerId === press.pointerId) {
        const travel = Math.hypot(event.clientX - press.x, event.clientY - press.y);
        // A finger that moves before it has held still long enough is scrolling; a mouse or pen that moves is dragging.
        if (press.touch && travel >= SLOP) {
            release();
        } else if (!press.touch && travel >= THRESHOLD) {
            startDrag(event);
        }
    }
    if (drag && event.pointerId === drag.pointerId) {
        drag.x = event.clientX;
        drag.y = event.clientY;
        follow();
    }
});

document.addEventListener('pointerup', (event) => {
    if (press?.pointerId === event.pointerId) {
        release();
    }
    if (drag?.pointerId === event.pointerId) {
        endDrag(!drag.refused);
        swallowClick = true;
        // The click comes in the same task as the pointerup; anything later isn't it.
        setTimeout(() => {
            swallowClick = false;
        });
    }
});

document.addEventListener('pointercancel', (event) => {
    if (press?.pointerId === event.pointerId) {
        release();
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

// Dragging over text would otherwise select it, as would a finger held on it.
document.addEventListener('selectstart', (event) => {
    if (drag || press?.touch) {
        event.preventDefault();
    }
});

// A finger held on a card picks it up: not the phone's menu for a long press as well.
document.addEventListener('contextmenu', (event) => {
    if (press?.touch || drag?.touch) {
        event.preventDefault();
    }
});

// --- Undo ----------------------------------------------------------------------------------------------------

// Where the last move left its card, by column id and place: the elements may have been drawn again since.
const stillThere = (card, after) => {
    const now = spot(card);

    return idOf(now.column) === idOf(after.column) && now.index === after.index;
};

// Makes the opposite of the last move, as a move of its own: announced, sent and saved like any other. Not if the card
// has moved since by some other way (a render, a script), which Undo would then get wrong.
document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-kanban-undo]');
    const board = button && boardOf(button);
    const last = board && undoable.get(board);
    if (!last) {
        return;
    }
    const card = current(board, last.card);
    if (!card || !stillThere(card, last.after)) {
        offerUndo(board, null);
        say(board, `${last.card.dataset.label} has moved since, so it stays where it is.`);

        return;
    }
    const { before } = last;
    const now = spot(card);
    // Still being saved, say: the offer stands for when it's done.
    const problem = blocked(card);
    if (problem) {
        say(board, problem);

        return;
    }
    offerUndo(board, null);
    // The Undo button hides, so focus goes to the card it moved.
    card.querySelector('[data-dropdown-trigger]')?.focus({ preventScroll: true });
    putBack(card, before);
    settle(card, now, { canUndo: false });
});

// --- Jumping to a column -------------------------------------------------------------------------------------

// The look of the buttons the script draws, and of the one for the column in view.
const JUMP = 'border-line text-foreground hover:bg-field focus-visible:ring-primary aria-[current=true]:border-primary/40 aria-[current=true]:bg-primary/10 aria-[current=true]:text-primary inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap outline-none focus-visible:ring-2';

// The ring around a column just chosen from its button. Inset, so the scroller's edge doesn't clip it.
const JUMPED = ['ring-primary', 'ring-2', 'ring-inset'];

const scrollerOf = (board) => board.querySelector('[data-kanban-columns]');

// Per board, the column last chosen from its jump buttons.
const chosen = new WeakMap();
// Per column, the timer that takes its ring away again.
const ringing = new WeakMap();

// Marks the button of the column most in view: on a phone, the one being looked at. When several are wholly in view,
// as once scrolled to the end, the one last chosen, or else the first of them: scrolled as far as it goes, choosing
// Delivered can't bring it any further in, but it's still the one asked for.
function markCurrent(board) {
    const view = scrollerOf(board).getBoundingClientRect();
    const columns = columnsOf(board);
    const shown = columns.map((column) => {
        const box = column.getBoundingClientRect();

        return box.width ? Math.max(0, Math.min(box.right, view.right) - Math.max(box.left, view.left)) / box.width : 0;
    });
    // Within a pixel or so of wholly in view counts as wholly in view.
    const most = Math.max(...shown) - 0.01;
    const candidates = columns.filter((column, i) => shown[i] >= most);
    const current = candidates.includes(chosen.get(board)) ? chosen.get(board) : candidates[0];
    board.querySelectorAll('[data-kanban-jump] button').forEach((button) => {
        button.setAttribute('aria-current', String(button.dataset.kanbanJumpTo === (current && idOf(current))));
    });
}

// One button per column, shown only while the columns don't all fit side by side.
function drawJumps(board) {
    const bar = board.querySelector('[data-kanban-jump]');
    const scroller = scrollerOf(board);
    const overflows = scroller.scrollWidth > scroller.clientWidth + 1;
    bar.hidden = !overflows;
    // Columns that scroll sideways can only be scrolled by a mouse or a finger unless the row can take focus: it does,
    // named, while it scrolls, so a board that fits gets no needless tab stop. A readonly board has nothing else
    // inside to focus, and the arrow keys would have no way to reach its other columns.
    if (overflows) {
        scroller.tabIndex = 0;
        scroller.setAttribute('role', 'region');
        scroller.setAttribute('aria-label', `Columns of ${board.getAttribute('aria-label')}`);
    } else {
        scroller.removeAttribute('tabindex');
        scroller.removeAttribute('role');
        scroller.removeAttribute('aria-label');
    }
    if (bar.hidden) {
        return;
    }
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', `Columns of ${board.getAttribute('aria-label')}`);
    bar.replaceChildren(...columnsOf(board).map((column) => {
        const button = Object.assign(document.createElement('button'), { type: 'button', className: JUMP });
        button.dataset.kanbanJumpTo = idOf(column);
        // Set apart by weight, not by fading it: faded, it fell below AA on the tinted button of the column in view.
        const count = Object.assign(document.createElement('span'), { className: 'font-normal tabular-nums' });
        count.setAttribute('data-kanban-jump-count', '');
        count.textContent = numbers().format(cardsIn(column).length);
        // A space between, for the name a screen reader gives the button ("New 3"); the gap already shows one.
        button.append(titleOf(column), ' ', count);

        return button;
    }));
    markCurrent(board);
}

document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-kanban-jump] button');
    const board = button && boardOf(button);
    const column = board && columnById(board, button.dataset.kanbanJumpTo);
    if (!column) {
        return;
    }
    chosen.set(board, column);
    const smooth = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    column.scrollIntoView({ inline: 'start', block: 'nearest', behavior: smooth ? 'smooth' : 'auto' });
    markCurrent(board);
    // A moment's ring around it, so the choice shows even when there was nothing to scroll.
    column.classList.add(...JUMPED);
    clearTimeout(ringing.get(column));
    ringing.set(column, setTimeout(() => column.classList.remove(...JUMPED), 1200));
});

// Scroll events don't bubble, so listen in the capture phase; once a frame is enough.
let marking = 0;
document.addEventListener('scroll', (event) => {
    const board = event.target.matches?.('[data-kanban-columns]') ? boardOf(event.target) : null;
    if (board && !marking) {
        marking = requestAnimationFrame(() => {
            marking = 0;
            markCurrent(board);
        });
    }
}, true);

// Whether the columns fit changes with the window, the page's layout, and the columns themselves.
const resized = new ResizeObserver((entries) => entries.forEach((entry) => drawJumps(boardOf(entry.target))));

// --- Setting up ----------------------------------------------------------------------------------------------

// Set up once per element. Not a data attribute for that: Livewire's morph removes attributes the server didn't render.
const ready = new WeakSet();

function initBoard(board) {
    if (ready.has(board)) {
        return;
    }
    ready.add(board);
    // Shows the move buttons. The board keeps it through a Livewire render (wire:ignore.self on it).
    if (!isReadonly(board)) {
        board.setAttribute('data-kanban-ready', '');
    }
    const seen = new Set();
    for (const card of columnsOf(board).flatMap(cardsIn)) {
        if (seen.has(idOf(card))) {
            console.warn(`Bladewell kanban: two cards on one board share the id "${idOf(card)}"; a move would send the wrong one.`, card);
        }
        seen.add(idOf(card));
    }
    columnsOf(board).forEach(count);
    drawJumps(board);
    resized.observe(scrollerOf(board));
    scrollerOf(board).addEventListener('touchmove', holdPage, { passive: false });
}

export function initKanbans(scope = document) {
    scope.querySelectorAll(BOARD).forEach(initBoard);
}

initKanbans();

// After the page has changed under the script (a Livewire render, a script of yours): the cards in play may have been
// drawn again, without the marks the script set on them, and somewhere else. Follow them by their id, put the marks
// back (and the Move here targets with them), and drop an Undo the change has made wrong, saying where the card is.
function catchUp() {
    if (picked) {
        const card = current(picked.board, picked.card);
        if (!card) {
            putDown(false);
        } else {
            const redrawn = card !== picked.card;
            picked.card = card;
            if (!card.hasAttribute('data-kanban-picked')) {
                card.setAttribute('data-kanban-picked', '');
                drawSlots();
            }
            // Focus went with the old card; the keys that place it need it back. And it's drawn where the server has
            // it, which may not be where it was being placed: say where.
            if (redrawn) {
                if (!picked.board.contains(document.activeElement)) {
                    card.querySelector('[data-dropdown-trigger]')?.focus({ preventScroll: true });
                }
                say(picked.board, `${where(card)}.`);
            }
        }
    }
    if (drag) {
        const card = current(drag.board, drag.card);
        if (!card) {
            endDrag(false);
        } else {
            drag.card = card;
            card.setAttribute('data-kanban-dragging', '');
        }
    }
    for (const board of document.querySelectorAll(BOARD)) {
        for (const id of savingIn(board)) {
            cardById(board, id)?.setAttribute('aria-busy', 'true');
        }
        const last = undoable.get(board);
        const card = last && current(board, last.card);
        if (last && !card) {
            offerUndo(board, null);
        } else if (last && !stillThere(card, last.after)) {
            // A Livewire component that refused the move has drawn the card where it was.
            offerUndo(board, null);
            const back = idOf(columnOf(card)) === idOf(last.before.column);
            say(board, `${card.dataset.label} is ${back ? 'back' : 'now'} in ${where(card)}.`);
        } else if (last) {
            last.card = card;
        }
    }
}

// Boards added to the page later (a Livewire render or wire:navigate, fetched HTML) set themselves up. A card taken off
// the page while it was picked up or dragged ends that.
new MutationObserver((records) => {
    catchUp();
    // Columns added or taken away (a render, a script): the jump buttons follow.
    for (const scroller of new Set(records.map((record) => record.target).filter((target) => target.matches?.('[data-kanban-columns]')))) {
        drawJumps(boardOf(scroller));
    }
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            (node.matches(BOARD) ? [node] : node.querySelectorAll(BOARD)).forEach(initBoard);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

// --- From scripts --------------------------------------------------------------------------------------------

const find = (target, selector) => (typeof target === 'string' || typeof target === 'number'
    ? document.querySelector(`${selector.slice(0, -1)}="${CSS.escape(String(target))}"]`)
    : target);

// kanban.move(card, column, position): card by element or id, column by element or id on the card's board, position
// counted from 0 (left out, the end). Returns whether it moved.
function move(target, toColumn, position = Infinity) {
    const card = find(target, CARD);
    const column = card && (toColumn instanceof Element ? toColumn : columnById(boardOf(card), toColumn));
    if (!card || !column) {
        console.warn('Bladewell kanban: no such card or column on this page for', target, toColumn);

        return false;
    }

    return moveCard(card, column, position);
}

export const kanban = { move };

window.kanban = kanban;
