// Drives <x-widget.filter-builder>. A row holds only what it shows: its field, the conditions that field's type offers,
// and the value its condition takes. When the field or condition changes, those are swapped in from the builder's
// templates, so ten rows don't each carry every kind of control. Which controls a condition shows comes from the
// server (FilterField::parts()), not from here. The complete rows go into one hidden field as JSON, and a change there
// is what a form, a table's filters slot or wire:model sees. A row still missing its value, or with one the server would
// turn down, isn't sent, and the builder says so: a table filtered in place keeps its toolbar, so the server's answer
// about a row would never be seen, and the rows shown would seem to filter by it. Delegated from `document`; the rows
// themselves are the repeater's.
import { clearError, onLivewireMorph, showError } from '../field';
import { uniqueIds } from '../repeater';

const ROOT = '[data-filter-builder]';
// Typing waits for a pause, as a table's search does; a pick is sent at once.
const TYPING_MS = 400;

const scripts = new WeakMap();
const scriptOf = (root) => {
    if (!scripts.has(root)) {
        scripts.set(root, JSON.parse(root.dataset.filterScript));
    }

    return scripts.get(root);
};

// What the select, number, date picker and text widgets hold: their own hidden inputs, or the field typed in.
const chosen = (scope) => scope?.querySelector('[data-select-input]')?.value ?? '';
const chosenAll = (scope) => [...(scope?.querySelectorAll('[data-select-values] input') ?? [])].map((input) => input.value).filter(Boolean);
const typed = (scope) => (scope?.querySelector('[data-datepicker-input], [data-number-value]') ?? scope?.querySelector('input:not([type="hidden"])'))?.value.trim() ?? '';

// A copy of one of the builder's templates, its ids made its own (a suffix no generated id contains).
let copies = 0;
function copy(root, name) {
    const fragment = root.querySelector(`template[data-filter-template="${CSS.escape(name)}"]`).content.cloneNode(true);
    uniqueIds(fragment, `copy${++copies}x`);

    return fragment.firstElementChild;
}

// Says it once the controls have settled, emptied first so the same words twice are still read out.
function say(root, text) {
    const status = root.querySelector(':scope > [data-filter-builder-status]');
    status.textContent = '';
    requestAnimationFrame(() => {
        status.textContent = text;
    });
}

// Puts the row's conditions and values in line with its field and condition: the conditions for its type, then the
// value controls the condition shows, keeping those it already had (and what's in them) and dropping the rest.
function arrange(root, row) {
    const script = scriptOf(root);
    const field = chosen(row.querySelector(':scope > [data-filter-part="field"]'));
    const type = script.types[field];
    let condition = row.querySelector(':scope > [data-filter-part="operator"]');
    if (type && condition?.dataset.filterType !== type) {
        const fresh = copy(root, `operator:${type}`);
        condition.replaceWith(fresh);
        condition = fresh;
        // New conditions appeared without anything being said: the field's conditions are what follows it now.
        say(root, root.dataset.changedMessage.replace(':field', script.labels[field] ?? field));
    }
    const operator = chosen(condition);
    const wanted = script.parts[field]?.[operator];
    if (!wanted) {
        return { field, operator, wanted: null };
    }
    const kept = new Map([...row.querySelectorAll(':scope > [data-filter-value]')].map((part) => [part.dataset.filterValue, part]));
    let after = condition;
    for (const name of wanted) {
        const part = kept.get(name) ?? copy(root, name);
        kept.delete(name);
        if (after.nextElementSibling !== part) {
            after.after(part);
        }
        after = part;
    }
    kept.forEach((part) => part.remove());

    return { field, operator, wanted };
}

// Why Filters::read() would turn the value down, with the control it's about, or null. The same checks and words as
// there, so a row is marked before it's sent rather than left out after. Its limits come from the server.
function problem(root, row, wanted, value) {
    const { limits } = scriptOf(root);
    const [first] = wanted;
    if (first.startsWith('choice:')) {
        // A choice the list no longer has: one a saved filter brought, which the select can't show.
        const offered = new Set([...row.querySelectorAll(`:scope > [data-filter-value="${CSS.escape(first)}"] [role="option"][data-value]`)].map((option) => option.dataset.value));
        const missing = value.filter((one) => !offered.has(one));
        if (missing.length > 0) {
            return [first, `Not in the list: “${missing.slice(0, 5).map((one) => one.slice(0, 60)).join('”, “')}”. Choose from the list.`];
        }

        return value.length > limits.choices ? [first, `Choose up to ${limits.choices}.`] : null;
    }
    if (first === 'days') {
        const days = Number(value);

        return Number.isInteger(days) && days >= 1 && days <= limits.days ? null : [first, `Enter a number of days from 1 to ${limits.days}.`];
    }
    if (first === 'text') {
        // Characters as PHP's mb_strlen() counts them, not UTF-16 units.
        return [...value].length > limits.text ? [first, `Enter something to match, up to ${limits.text} characters.`] : null;
    }
    if (wanted.length === 2) {
        const dates = first === 'date';
        // Y-m-d days compare as text; numbers as numbers.
        const reversed = dates ? value[0] > value[1] : Number(value[0]) > Number(value[1]);

        return reversed ? [first, dates ? 'Choose the earlier date first.' : 'Enter the smaller number first.'] : null;
    }

    return null;
}

// What each marked field last had read out, so a problem is said once, when it appears or changes: not again on every
// pause in typing, though editing the field takes the error away until the next check.
const said = new WeakMap();

// Shows the row's problem on its control, or takes away one shown before that no longer applies. Only the ones this
// script decides (data-filter-checked): the server's stay until the row is changed, as on any form.
function mark(root, row, found) {
    const target = found && row.querySelector(`:scope > [data-filter-value="${CSS.escape(found[0])}"] [data-field]`);
    row.querySelectorAll('[data-field][data-filter-checked]').forEach((field) => {
        if (field !== target) {
            field.removeAttribute('data-filter-checked');
            said.delete(field);
            clearError(field);
        }
    });
    if (!target) {
        return;
    }
    showError(target, found[1]);
    target.setAttribute('data-filter-checked', '');
    if (said.get(target) !== found[1]) {
        said.set(target, found[1]);
        say(root, found[1]);
    }
}

// The row's rule, or null until it has everything its condition needs and the server would take it. A row the server
// turned down waits to be changed: sent again unchanged it would only be turned down again.
function rowRule(root, row) {
    const { field, operator, wanted } = arrange(root, row);
    if (!wanted || row.hasAttribute('data-filter-rejected')) {
        return null;
    }
    const part = (name) => row.querySelector(`:scope > [data-filter-value="${CSS.escape(name)}"]`);
    if (wanted.length === 0) {
        return { field, op: operator };
    }
    let value;
    if (wanted[0].startsWith('choice:')) {
        value = chosenAll(part(wanted[0]));
        if (value.length === 0) {
            mark(root, row, null);

            return null;
        }
    } else {
        const values = wanted.map((name) => typed(part(name)));
        if (!values.every(Boolean)) {
            mark(root, row, null);

            return null;
        }
        value = values.length === 2 ? values : values[0];
    }
    const found = problem(root, row, wanted, value);
    mark(root, row, found);

    return found ? null : { field, op: operator, value };
}

// Under the rows: how many aren't used yet. Not those the server turned down, which the message above them counts.
function notice(root, unused) {
    const pending = root.querySelector('[data-filter-builder-pending]');
    const [one, many = one] = pending.dataset.message.split('|');
    const text = unused === 0 ? '' : (unused === 1 ? one : many).replace(':count', unused.toLocaleString());
    if (pending.textContent !== text) {
        pending.textContent = text;
    }
}

// Writes the complete rows to the hidden field, and tells whatever listens when they've changed.
function write(root) {
    const rows = [...root.querySelectorAll('[data-filter-row]')];
    const rules = [];
    let unused = 0;
    for (const row of rows) {
        const rule = rowRule(root, row);
        if (rule) {
            rules.push(rule);
        } else if (!row.hasAttribute('data-filter-rejected')) {
            unused++;
        }
    }
    notice(root, unused);
    // The server's message about what it left out was about the rows as they came. Once none of those is left
    // unchanged, it's about filters no longer there: a table filtered in place keeps it otherwise.
    if (!rows.some((row) => row.hasAttribute('data-filter-rejected'))) {
        root.querySelector(':scope > [data-filter-builder-error]')?.remove();
    }
    const match = chosen(root.querySelector('[data-filter-match]')) === 'any' ? 'any' : 'all';
    const json = rules.length > 0 ? JSON.stringify({ match, rules }) : '';
    const field = root.querySelector('[data-filter-builder-value]');
    if (field.value === json) {
        return;
    }
    sentBy(root).push(json);
    field.value = json;
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

const timers = new WeakMap();

function changed(event) {
    const target = event.target;
    if (!(target instanceof Element) || target.matches('[data-filter-builder-value]')) {
        return;
    }
    const root = target.closest(ROOT);
    if (!root) {
        return;
    }
    // Changed by the person (not just looked through, in a popover), so it's sent again. The server's errors on it were
    // about the row as it came: from here this script's checks decide whether they stay.
    const row = target.closest('[data-filter-row][data-filter-rejected]');
    if (row && !target.closest('[popover]')) {
        row.removeAttribute('data-filter-rejected');
        row.querySelectorAll('[data-field][data-invalid]').forEach((field) => field.setAttribute('data-filter-checked', ''));
    }
    clearTimeout(timers.get(root));
    if (event.type === 'input' && target.matches('input:not([type="hidden"])')) {
        timers.set(root, setTimeout(() => write(root), TYPING_MS));
    } else {
        write(root);
    }
}

document.addEventListener('input', changed);
document.addEventListener('change', changed);

// A row added starts incomplete, so nothing is sent; a row removed may take a condition with it.
for (const type of ['repeater:add', 'repeater:remove']) {
    document.addEventListener(type, (event) => {
        const root = event.target.closest?.(ROOT);
        if (root) {
            write(root);
        }
    });
}

// --- Livewire ---------------------------------------------------------------------------------------------------

// The rows are the browser's (wire:ignore), so a render never undoes what's being typed. But your component may set the
// filters itself: a saved filter loaded, or the table's "Clear filters". Its render then brings that state's rows in a
// template keyed by the filters as sent, and they replace the rows here, unless those filters are ones this builder
// sent: the answer to its own change, or to an earlier one still on its way back.
const sent = new WeakMap();

// What this builder has sent since the last render that answered it, starting from what the page came with.
function sentBy(root) {
    if (!sent.has(root)) {
        sent.set(root, [root.querySelector(':scope > [data-filter-rows]')?.dataset.filterInput ?? '']);
    }

    return sent.get(root);
}

function reload(root) {
    const source = root.querySelector(':scope > template[data-filter-rows-source]');
    if (!source) {
        return;
    }
    const now = source.dataset.filterInput ?? '';
    const ours = sentBy(root);
    if (now === ours.at(-1)) {
        ours.splice(0, ours.length - 1);

        return;
    }
    if (ours.includes(now)) {
        return;
    }
    ours.splice(0, ours.length, now);
    const fresh = source.content.cloneNode(true);
    uniqueIds(fresh, `copy${++copies}x`);
    root.querySelector(':scope > [data-filter-rows] > [data-filter-shown]').replaceWith(fresh);
    clearTimeout(timers.get(root));
    notice(root, 0);
}

onLivewireMorph((scope) => {
    (scope.matches?.(ROOT) ? [scope] : [...scope.querySelectorAll(ROOT)]).forEach(reload);
});
