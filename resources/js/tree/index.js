// Drives <x-widget.tree>, an ARIA tree. A click on a node opens or closes it (in the checkbox look, ticks it; its arrow
// still opens it); a link node is followed. The keyboard: Up and Down move between the nodes you can see, Right opens
// a node or moves into it, Left closes it or moves to its parent, Home and End go to the first and last, Enter
// activates, Space ticks (checkbox) or opens, and a letter jumps to the next node starting with it. Only one node is
// in the Tab order at a time. A node with data-children-url fetches its children's HTML on first open.
// Events on the tree: tree:toggle (detail.item, detail.open) and tree:loaded (detail.item, detail.count).
// From JS: tree.open(target) and tree.close(target), by an item's id or element; also on window.tree.
// Delegated from `document`, so trees added later (a Livewire render, fetched HTML) work without setting up.

import { onLivewireMorph } from '../field';

const TREE = '[data-tree]';
const ITEM = '[data-tree-item]';
// The error row's look, written out here since the script builds it.
const ERROR_ROW = 'text-error flex flex-wrap items-center gap-x-2 px-2 py-1.5 text-sm';
const RETRY = 'text-foreground hover:text-primary focus-visible:ring-primary rounded underline underline-offset-4 outline-none focus-visible:ring-2';

const treeOf = (element) => element.closest(TREE);
const rowOf = (item) => item.querySelector(':scope > [data-tree-row]');
const groupOf = (item) => item.querySelector(':scope > [data-tree-group]');
const childItems = (item) => [...(groupOf(item)?.children ?? [])].filter((child) => child.matches(ITEM));
const isBranch = (item) => item.hasAttribute('aria-expanded');
const isOpen = (item) => item.getAttribute('aria-expanded') === 'true';
const isCheckbox = (tree) => tree.dataset.variant === 'checkbox';
const isDisabled = (item) => item.getAttribute('aria-disabled') === 'true';

// The node a node sits under, in the same tree; null at the top.
function parentItem(item) {
    const parent = item.parentElement?.closest(`${ITEM}, ${TREE}`);

    return parent?.matches(ITEM) ? parent : null;
}

// The nodes a person can see, in reading order: none inside a closed node.
const visibleItems = (tree) => [...tree.querySelectorAll(ITEM)].filter((item) => !item.parentElement.closest('[data-tree-group][hidden]'));

// --- Focus ------------------------------------------------------------------------------------------------------------

// One node in the Tab order (tabindex 0), the rest reached with the arrow keys: the one last focused, or the first.
function keepTabStop(tree) {
    const items = visibleItems(tree);
    const stop = items.find((item) => item.tabIndex === 0) ?? items[0];
    tree.querySelectorAll(ITEM).forEach((item) => {
        item.tabIndex = item === stop ? 0 : -1;
    });
}

function focusItem(item, { scroll = true } = {}) {
    if (!item) {
        return;
    }
    treeOf(item).querySelectorAll(`${ITEM}[tabindex="0"]`).forEach((other) => {
        other.tabIndex = -1;
    });
    item.tabIndex = 0;
    item.focus({ preventScroll: !scroll });
}

// --- Opening, and children fetched on first open ----------------------------------------------------------------------

const loading = new WeakMap();

async function setOpen(item, open) {
    if (!isBranch(item) || isOpen(item) === open) {
        return;
    }
    item.setAttribute('aria-expanded', String(open));
    rowOf(item).toggleAttribute('data-expanded', open);
    const group = groupOf(item);
    if (group) {
        group.hidden = !open;
    }
    item.dispatchEvent(new CustomEvent('tree:toggle', { bubbles: true, detail: { item, open } }));
    if (open && item.dataset.childrenUrl && !('loaded' in item.dataset)) {
        await load(item);
    }
}

// The URL answers with a tree of the same look holding the children, as HTML; its items go in as they are. A <style> in it is dropped, as the
// table does: inserted, it would break a Content Security Policy without 'unsafe-inline'.
function load(item) {
    if (loading.has(item)) {
        return loading.get(item);
    }
    const tree = treeOf(item);
    const row = rowOf(item);
    const group = groupOf(item);
    row.toggleAttribute('data-busy', true);
    item.setAttribute('aria-busy', 'true');
    group.querySelector(':scope > [data-tree-error]')?.remove();

    const done = (async () => {
        try {
            const response = await fetch(item.dataset.childrenUrl, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
            // A redirect (to a login page, say) brings back a page, not children.
            if (!response.ok || response.redirected) {
                throw new Error(`${response.status}`);
            }
            const html = (await response.text()).replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, '');
            // They come back inside a tree of the same look, which tells each one which look to render. Its items are
            // what goes in; the tree around them is left behind.
            const answer = new DOMParser().parseFromString(html, 'text/html');
            const children = [...(answer.querySelector(TREE) ?? answer.body).children].filter((child) => child.matches(ITEM));
            group.replaceChildren(...children.map((child) => document.adoptNode(child)));
            item.dataset.loaded = '';
            if (children.length === 0) {
                // Nothing under it after all: a leaf from now on.
                item.removeAttribute('aria-expanded');
                row.removeAttribute('data-expanded');
                // A list keeps the empty box, so labels stay in line; a card has nothing there.
                const toggle = row.querySelector('[data-tree-toggle]');
                if (['cards', 'org'].includes(tree.dataset.variant)) {
                    toggle?.remove();
                } else {
                    toggle?.replaceChildren();
                }
                group.remove();
            } else if (isCheckbox(tree)) {
                tickLoaded(item, children);
            }
            item.dispatchEvent(new CustomEvent('tree:loaded', { bubbles: true, detail: { item, count: children.length } }));
        } catch {
            showError(item);
        } finally {
            row.removeAttribute('data-busy');
            item.removeAttribute('aria-busy');
            loading.delete(item);
        }
    })();
    loading.set(item, done);

    return done;
}

// Not a node: a row inside the open group saying so, with a way to try again.
function showError(item) {
    const row = Object.assign(document.createElement('li'), { className: ERROR_ROW });
    row.setAttribute('role', 'none');
    row.dataset.treeError = '';
    const retry = Object.assign(document.createElement('button'), { type: 'button', className: RETRY, textContent: 'Try again' });
    retry.dataset.treeRetry = '';
    row.append(Object.assign(document.createElement('span'), { textContent: "Couldn't load these." }), retry);
    groupOf(item).replaceChildren(row);
}

// --- Ticking (the checkbox look) ----------------------------------------------------------------------------------------

function setChecked(item, state) {
    item.setAttribute('aria-checked', state);
    const row = rowOf(item);
    row.toggleAttribute('data-checked', state === 'true');
    row.toggleAttribute('data-mixed', state === 'mixed');
}

const checked = (item) => item.getAttribute('aria-checked');

// A node, and everything under it that isn't disabled.
function tickDown(item, state) {
    if (!isDisabled(item)) {
        setChecked(item, state);
    }
    childItems(item).forEach((child) => tickDown(child, state));
}

// A parent shows what's under it: ticked when all of it is, a dash when some is. One not yet opened keeps its own.
function settleUp(item) {
    for (let node = item; node; node = parentItem(node)) {
        const states = childItems(node).map(checked);
        if (states.length > 0) {
            setChecked(node, states.every((state) => state === 'true') ? 'true' : states.every((state) => state === 'false') ? 'false' : 'mixed');
        }
    }
}

// Ticks it and everything under it, or unticks them when every leaf under it that can be ticked already is. Deciding by
// those leaves, not the parent's own state, means a disabled, unticked child can't leave it stuck half-ticked.
function toggleChecked(item) {
    if (isDisabled(item)) {
        return;
    }
    const tickable = [item, ...item.querySelectorAll(ITEM)].filter((node) => !isDisabled(node) && childItems(node).length === 0);
    tickDown(item, tickable.every((node) => checked(node) === 'true') ? 'false' : 'true');
    // From the node itself: a disabled child it couldn't tick leaves it half-ticked.
    settleUp(item);
    writeValue(treeOf(item));
}

const valueSelect = (tree) => tree.closest('[data-field]')?.querySelector('select[data-tree-value]');

// The hidden select is what submits and what wire:model binds. Nodes on the page update their option; values under
// nodes not yet opened keep theirs.
function writeValue(tree) {
    const select = valueSelect(tree);
    if (!select) {
        return;
    }
    const options = new Map([...select.options].map((option) => [option.value, option]));
    tree.querySelectorAll(`${ITEM}[data-value]`).forEach((item) => {
        const on = checked(item) === 'true';
        const option = options.get(item.dataset.value);
        if (option) {
            option.selected = on;
        } else if (on) {
            select.append(new Option(item.dataset.value, item.dataset.value, true, true));
        }
    });
    select.dispatchEvent(new Event('input', { bubbles: true }));
    select.dispatchEvent(new Event('change', { bubbles: true }));
}

// Ticks the boxes from the select: as the page loads, and after a Livewire render changed the bound property.
function readValue(tree) {
    const select = valueSelect(tree);
    const chosen = new Set(select ? [...select.selectedOptions].map((option) => option.value) : []);
    const items = [...tree.querySelectorAll(ITEM)];
    items.forEach((item) => setChecked(item, chosen.has(item.dataset.value) ? 'true' : 'false'));
    // Deepest first, so each parent counts children that are already settled.
    items.reverse().forEach((item) => {
        const states = childItems(item).map(checked);
        if (states.length > 0) {
            setChecked(item, states.every((state) => state === 'true') ? 'true' : states.every((state) => state === 'false') ? 'false' : 'mixed');
        }
    });
}

// Children just fetched: ticked if their value was, or if their parent was ticked whole; then the parents settle.
function tickLoaded(item, children) {
    const chosen = new Set([...(valueSelect(treeOf(item))?.selectedOptions ?? [])].map((option) => option.value));
    const inherit = checked(item) === 'true';
    children.forEach((child) => tickDown(child, inherit || chosen.has(child.dataset.value) ? 'true' : 'false'));
    settleUp(item);
    writeValue(treeOf(item));
}

// --- Setting up --------------------------------------------------------------------------------------------------------

function setUp(tree) {
    keepTabStop(tree);
    if (isCheckbox(tree)) {
        readValue(tree);
    }
}

const setUpAll = (scope = document) => {
    if (scope instanceof Element && scope.matches(TREE)) {
        setUp(scope);
    }
    scope.querySelectorAll?.(TREE).forEach(setUp);
};

// --- Pointer -----------------------------------------------------------------------------------------------------------

document.addEventListener('click', (event) => {
    const retry = event.target.closest?.('[data-tree-retry]');
    if (retry) {
        const item = retry.closest(ITEM);
        focusItem(item);
        load(item);

        return;
    }
    const row = event.target.closest?.('[data-tree-row]');
    const tree = row && treeOf(row);
    if (!tree) {
        return;
    }
    const item = row.parentElement;
    // Something of its own inside the node (a link in its details, say) does its own thing.
    const own = event.target.closest('a[href], button, input, select, textarea, summary');
    if (own && !own.closest('[data-tree-row] > span > span[id$="-label"]')) {
        return;
    }
    focusItem(item, { scroll: false });
    // The node's own link is followed as a link.
    if (own) {
        return;
    }
    if (isCheckbox(tree) && !event.target.closest('[data-tree-toggle]')) {
        toggleChecked(item);

        return;
    }
    setOpen(item, !isOpen(item));
});

// Focus moving onto a node by any means (a click on its padding, a script) makes it the Tab stop.
document.addEventListener('focusin', (event) => {
    if (!event.target.matches?.(ITEM)) {
        return;
    }
    treeOf(event.target).querySelectorAll(`${ITEM}[tabindex="0"]`).forEach((other) => {
        other.tabIndex = -1;
    });
    event.target.tabIndex = 0;
});

// --- Keyboard ----------------------------------------------------------------------------------------------------------

function activate(item) {
    const link = rowOf(item).querySelector('[id$="-label"] a[href]');
    if (link) {
        link.click();
    } else if (isCheckbox(treeOf(item))) {
        toggleChecked(item);
    } else {
        setOpen(item, !isOpen(item));
    }
}

const labelOf = (item) => rowOf(item).querySelector('[id$="-label"]')?.textContent.trim().toLowerCase() ?? '';

document.addEventListener('keydown', (event) => {
    const item = event.target.matches?.(ITEM) ? event.target : null;
    if (!item || event.altKey || event.ctrlKey || event.metaKey) {
        return;
    }
    const tree = treeOf(item);
    const items = visibleItems(tree);
    const at = items.indexOf(item);
    // Right and Left follow the reading direction: in a right-to-left page, Left goes in.
    const rtl = getComputedStyle(tree).direction === 'rtl';
    const key = rtl && event.key === 'ArrowLeft' ? 'ArrowRight' : rtl && event.key === 'ArrowRight' ? 'ArrowLeft' : event.key;

    switch (key) {
        case 'ArrowDown':
            focusItem(items[at + 1]);
            break;
        case 'ArrowUp':
            focusItem(items[at - 1]);
            break;
        case 'Home':
            focusItem(items[0]);
            break;
        case 'End':
            focusItem(items.at(-1));
            break;
        case 'ArrowRight':
            if (isBranch(item) && !isOpen(item)) {
                setOpen(item, true);
            } else if (isOpen(item)) {
                focusItem(childItems(item)[0]);
            }
            break;
        case 'ArrowLeft':
            if (isOpen(item)) {
                setOpen(item, false);
            } else {
                focusItem(parentItem(item));
            }
            break;
        case 'Enter':
            activate(item);
            break;
        case ' ':
            if (isCheckbox(tree)) {
                toggleChecked(item);
            } else {
                setOpen(item, !isOpen(item));
            }
            break;
        default: {
            // A letter or digit: the next node, after this one and round to the top, whose label starts with it.
            if (event.key.length !== 1 || !/\S/.test(event.key)) {
                return;
            }
            const letter = event.key.toLowerCase();
            const next = [...items.slice(at + 1), ...items.slice(0, at)].find((other) => labelOf(other).startsWith(letter));
            if (!next) {
                return;
            }
            focusItem(next);
        }
    }
    event.preventDefault();
});

// --- After a Livewire render, and for pages swapped in by wire:navigate -------------------------------------------------

// A frame later: Livewire 3 first reuses the hidden select's options in place, then marks the bound values selected,
// so ticking from it straight after the render would read the half-done select (checked in a browser against
// Livewire 3.8 and 4.4).
onLivewireMorph((element) => requestAnimationFrame(() => {
    const tree = element.closest?.(TREE);
    if (tree) {
        setUp(tree);
    } else {
        setUpAll(element);
    }
}));
document.addEventListener('livewire:navigated', () => setUpAll());

// An item by its id (one you gave it), or the item element itself or anything in it.
const itemOf = (target) => {
    const element = typeof target === 'string' ? document.getElementById(target) : target;
    const item = element?.closest?.(ITEM);
    if (!item) {
        console.warn('Bladewell tree: no tree item on this page for', target);
    }

    return item ?? null;
};

const call = (open) => async (target) => {
    const item = itemOf(target);
    if (item) {
        await setOpen(item, open);
    }
};

export const tree = { open: call(true), close: call(false) };

// Global, as modal and toast are, so a script on the page can call it without an import.
window.tree = tree;

setUpAll();
