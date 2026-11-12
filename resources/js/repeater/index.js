// Drives <x-widget.repeater>: adds rows from its template, removes and moves them, keeps the buttons' disabled states
// right and says what happened. Delegated from `document`, so repeaters added later (fetched HTML, a modal's content)
// work without setting up. Rows are keyed, not numbered: lines[k3f9][product] keeps its key, its old input and its
// errors wherever it moves, so nothing is renumbered. In a Livewire component (add-action, remove-action, move-action)
// the component's array owns the rows: the script leaves them to its methods and settles focus after the render.
import { onLivewireMorph } from '../field';

const ROOT = '[data-repeater]';

// Attributes that point at other elements by id: a row's ids change when it's copied, so these follow.
const REFERENCES = ['for', 'aria-labelledby', 'aria-describedby', 'aria-controls', 'aria-owns', 'aria-activedescendant', 'aria-details', 'aria-errormessage', 'aria-flowto', 'popovertarget', 'list', 'headers', 'href'];
const FOCUSABLE = 'input:not([type="hidden"]), select, textarea, button, a[href], [tabindex]:not([tabindex="-1"])';

const rowsOf = (root) => [...root.querySelectorAll(':scope > [data-repeater-rows] > [data-repeater-row]')];
const templateOf = (root) => root.querySelector(':scope > [data-repeater-rows] > template[data-repeater-template]');
const actionOf = (row, action) => row.querySelector(`:scope > div > [data-repeater-actions] > [data-repeater-action="${action}"]`);
const addOf = (root) => root.querySelector(':scope > div > [data-repeater-add]');
const nameOf = (root, position) => `${root.dataset.rowLabel} ${position}`;

function say(root, message, values) {
    const status = root.querySelector(':scope > [data-repeater-status]');
    const text = Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`:${key}`, value), message ?? '');
    // Emptied first, so the same message twice is still read out.
    status.textContent = '';
    requestAnimationFrame(() => {
        status.textContent = text;
    });
}

// Disables what can't be done now: add at the most rows, remove at the fewest, up on the first row, down on the last.
function update(root) {
    const rows = rowsOf(root);
    const min = Number(root.dataset.min ?? 0);
    const max = root.dataset.max === undefined ? Infinity : Number(root.dataset.max);
    const add = addOf(root);
    if (add) {
        // Without a template there's nothing to copy, unless a Livewire component adds the rows.
        add.disabled = rows.length >= max || (!templateOf(root) && !root.hasAttribute('data-repeater-livewire'));
    }
    rows.forEach((row, index) => {
        const [up, down, remove] = ['up', 'down', 'remove'].map((action) => actionOf(row, action));
        if (up) up.disabled = index === 0;
        if (down) down.disabled = index === rows.length - 1;
        if (remove) remove.disabled = rows.length <= min;
    });
}

// The error under the rows was about them as they were: too few, too many. Once they change it's just noise; the
// server checks again on submit.
function clearError(root) {
    const error = root.querySelector(':scope > [data-repeater-error]');
    if (error) {
        error.remove();
        root.removeAttribute('aria-describedby');
    }
}

// A key no row of this repeater has yet. "new-" tells the server it's not a record's id; letters, digits and the hyphen
// read the same in a name, a dot key and an id.
function newKey(root) {
    const taken = new Set(rowsOf(root).map((row) => row.dataset.repeaterRow));
    let key;
    do {
        key = `new-${Math.random().toString(36).slice(2, 8)}`;
    } while (taken.has(key));

    return key;
}

// Gives every id in a copied piece of markup the suffix, unless it has it already, and points what refers to them
// (labels, aria-*, popovers) at the new ones, so a copy never shares an id with its original. Exported for widgets that
// copy markup into a row too, as the filter builder does.
export function uniqueIds(scope, suffix) {
    const elements = [...scope.querySelectorAll('*')];
    const renamed = new Map();
    for (const element of elements) {
        if (element.id && !element.id.includes(suffix)) {
            renamed.set(element.id, `${element.id}-${suffix}`);
            element.id = `${element.id}-${suffix}`;
        }
    }
    if (renamed.size === 0) {
        return;
    }
    for (const element of elements) {
        for (const name of REFERENCES) {
            const value = element.getAttribute(name);
            if (value === null) {
                continue;
            }
            const updated = name === 'href'
                ? (value.startsWith('#') && renamed.has(value.slice(1)) ? `#${renamed.get(value.slice(1))}` : value)
                : value.split(/\s+/).map((token) => renamed.get(token) ?? token).join(' ');
            if (updated !== value) {
                element.setAttribute(name, updated);
            }
        }
    }
}

// Swaps the placeholder for the key in every attribute of a copied row: names, ids and what points at them. Templates
// inside it (a repeater in a row) are stamped too, so their rows are added under this one's key. Ids without the
// placeholder (one a widget made up) would repeat in every row added, so they get the key as well.
function stamp(fragment, placeholder, key) {
    for (const element of fragment.querySelectorAll('*')) {
        for (const attribute of [...element.attributes]) {
            if (attribute.value.includes(placeholder)) {
                element.setAttribute(attribute.name, attribute.value.replaceAll(placeholder, key));
            }
        }
        if (element instanceof HTMLTemplateElement) {
            stamp(element.content, placeholder, key);
        }
    }
    uniqueIds(fragment, key);
}

const isVisible = (element) => element.checkVisibility?.() ?? true;

// What follows each change, whether the script made it or a Livewire render did: focus where the person can carry on,
// the change read out, and an event for your own code.

function added(root, row) {
    // Straight into the first field; the row's own buttons only if it has none.
    const field = [...row.querySelector(':scope > [data-repeater-fields]').querySelectorAll(FOCUSABLE)]
        .find((element) => !element.disabled && isVisible(element));
    (field ?? actionOf(row, 'remove'))?.focus();

    const position = rowsOf(root).indexOf(row) + 1;
    say(root, root.dataset.added, { row: nameOf(root, position) });
    root.dispatchEvent(new CustomEvent('repeater:add', { bubbles: true, detail: { key: row.dataset.repeaterRow, row, position } }));
}

function removed(root, index, key) {
    // Where the row was: the row that took its place, else the one before; their remove button, else the add button.
    const rows = rowsOf(root);
    const neighbour = rows[index] ?? rows[index - 1];
    const target = neighbour && actionOf(neighbour, 'remove');
    (target && !target.disabled ? target : addOf(root))?.focus();

    say(root, root.dataset.removed, { row: nameOf(root, index + 1) });
    root.dispatchEvent(new CustomEvent('repeater:remove', { bubbles: true, detail: { key, position: index + 1 } }));
}

function moved(root, row, from, to) {
    // On the button that was pressed, unless the row is now at the top or bottom and that one is disabled: then on the
    // one that still works, so focus stays in the row.
    const [pressed, other] = to < from ? ['up', 'down'] : ['down', 'up'];
    const target = actionOf(row, pressed);
    (target && !target.disabled ? target : actionOf(row, other) ?? actionOf(row, 'remove'))?.focus();

    say(root, root.dataset.moved, { row: nameOf(root, from + 1), position: to + 1 });
    root.dispatchEvent(new CustomEvent('repeater:move', { bubbles: true, detail: { key: row.dataset.repeaterRow, row, from: from + 1, to: to + 1 } }));
}

function add(root) {
    const template = templateOf(root);
    if (!template) {
        return;
    }
    const fragment = template.content.cloneNode(true);
    stamp(fragment, root.dataset.placeholder, newKey(root));
    const row = fragment.querySelector('[data-repeater-row]');
    template.before(fragment);
    clearError(root);
    update(root);
    added(root, row);
}

// Links to the row's fields, such as an error summary's, would lead nowhere once it's gone: they go with it.
function unlink(row) {
    for (const element of row.querySelectorAll('[id]')) {
        document.querySelectorAll(`a[href="#${CSS.escape(element.id)}"]`).forEach((link) => (link.closest('li') ?? link).remove());
    }
}

function remove(root, row) {
    const index = rowsOf(root).indexOf(row);
    unlink(row);
    row.remove();
    clearError(root);
    update(root);
    removed(root, index, row.dataset.repeaterRow);
}

function move(root, row, direction) {
    const rows = rowsOf(root);
    const from = rows.indexOf(row);
    const other = rows[from + direction];
    if (!other) {
        return;
    }
    // The neighbour moves, not this row: moving the element that holds focus would drop it.
    if (direction < 0) {
        row.after(other);
    } else {
        row.before(other);
    }
    update(root);
    moved(root, row, from, from + direction);
}

// In Livewire the component's methods change the rows (the buttons' wire:click), and the render that follows puts the
// new rows on the page. What was pressed is kept until then, to know where focus goes and what to say.
let pending = null;

function settle(scope) {
    const root = pending && document.getElementById(pending.root);
    if (!root?.matches(ROOT) || !scope.contains(root)) {
        return;
    }
    const { action, key, keys, index, presses } = pending;
    const rows = rowsOf(root);
    const row = rows.find((candidate) => candidate.dataset.repeaterRow === key);
    if (action === 'add') {
        // Quick presses can come back as one render or several, or fewer rows than presses (Livewire 4 drops a repeat
        // click while the first is on its way). Each render that brings a row focuses the newest; one that brings none
        // (or the component refused) ends the wait, so a later render for something else doesn't pull focus back.
        const fresh = rows.filter((candidate) => !keys.includes(candidate.dataset.repeaterRow));
        if (fresh.length <= pending.seen) {
            pending = null;

            return;
        }
        pending = fresh.length >= presses ? null : { ...pending, seen: fresh.length };
        added(root, fresh.at(-1));

        return;
    }
    pending = null;
    if (action === 'remove') {
        if (!row) {
            removed(root, index, key);
        }
    } else if (row && rows.indexOf(row) !== index) {
        moved(root, row, index, rows.indexOf(row));
    }
}

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-repeater-add], [data-repeater-action]') : null;
    const root = button?.closest(ROOT);
    if (!root || button.disabled) {
        return;
    }
    const row = button.closest('[data-repeater-row]');
    const action = button.dataset.repeaterAction ?? 'add';
    if (root.hasAttribute('data-repeater-livewire')) {
        const keys = rowsOf(root).map((candidate) => candidate.dataset.repeaterRow);
        // Another add before the last one's render: still counted from the rows before the first.
        const again = action === 'add' && pending?.action === 'add' && pending.root === root.id;
        pending = again
            ? { ...pending, presses: pending.presses + 1 }
            : { root: root.id, action, key: row?.dataset.repeaterRow, keys, index: row ? keys.indexOf(row.dataset.repeaterRow) : -1, presses: 1, seen: 0 };

        return;
    }
    if (action === 'add') {
        add(root);
    } else if (action === 'remove') {
        remove(root, row);
    } else {
        move(root, row, action === 'up' ? -1 : 1);
    }
});

// Fewer rows than min, as a new form with none: the blank ones it needs, once, when the page comes. Not in Livewire,
// whose component sets the rows.
const filled = new WeakSet();
function fill(root) {
    if (filled.has(root) || root.hasAttribute('data-repeater-livewire')) {
        return;
    }
    filled.add(root);
    const template = templateOf(root);
    const min = Number(root.dataset.min ?? 0);
    while (template && rowsOf(root).length < min) {
        const fragment = template.content.cloneNode(true);
        stamp(fragment, root.dataset.placeholder, newKey(root));
        template.before(fragment);
    }
}

export function initRepeaters(scope = document) {
    (scope.matches?.(ROOT) ? [scope] : []).concat([...scope.querySelectorAll(ROOT)]).forEach((root) => {
        fill(root);
        update(root);
    });
}

initRepeaters();

// Repeaters added to the page later (a Livewire render or wire:navigate, fetched HTML) get their buttons' states too.
new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            initRepeaters(node);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

// A Livewire render puts back the server's markup, buttons enabled, and may be the one a button asked for. Only the
// component that rendered: the rest of the page didn't change.
onLivewireMorph((scope) => {
    initRepeaters(scope);
    settle(scope);
});
