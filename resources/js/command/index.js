// Drives <x-widget.command>, a command palette in a native modal <dialog>. Ctrl+K (Cmd+K on a Mac), a click on
// [data-command-open="{id}"] or command.open('{id}') (also on window.command, with the id or the element) opens it. Typing filters the items by their label and keywords (every
// word must match), and with suggest-url your app's results follow them. Up and Down move the highlight while focus stays
// in the box; Enter or a click chooses: an item with an href opens that page (with wire:navigate, through Livewire), any
// other is clicked, so wire:click and your own listeners run. Esc or a click outside closes it.
// Events on the dialog: command:opened, command:closed, and command:chosen (detail.item).

const DIALOG = 'dialog[data-command]';
const ITEM = '[data-command-item]';
const WAIT_MS = 200;
const RECENT_MAX = 5;
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

const partsOf = (dialog) => ({
    input: dialog.querySelector('[data-command-input]'),
    list: dialog.querySelector('[data-command-list]'),
    recent: dialog.querySelector('[data-command-recent]'),
    results: dialog.querySelector('[data-command-results]'),
    empty: dialog.querySelector('[data-command-empty]'),
    status: dialog.querySelector('[data-command-status]'),
    busy: dialog.querySelector('[data-command-busy]'),
});
const visibleItems = (dialog) => [...dialog.querySelectorAll(ITEM)].filter((item) => !item.hidden && !item.closest('[hidden]'));
const labelOf = (item) => item.querySelector('[data-command-label]')?.textContent.trim() ?? item.textContent.trim();

// --- Highlight -----------------------------------------------------------------------------------------------------------

function highlight(dialog, item) {
    const { input } = partsOf(dialog);
    dialog.querySelectorAll(`${ITEM}[aria-selected="true"]`).forEach((other) => other.setAttribute('aria-selected', 'false'));
    if (item) {
        item.setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', item.id);
        item.scrollIntoView({ block: 'nearest' });
    } else {
        input.removeAttribute('aria-activedescendant');
    }
}

function move(dialog, step) {
    const items = visibleItems(dialog);
    if (items.length === 0) {
        return;
    }
    const at = items.findIndex((item) => item.getAttribute('aria-selected') === 'true');
    highlight(dialog, items[(at + step + items.length) % items.length]);
}

// --- Filtering ---------------------------------------------------------------------------------------------------------

// Every word typed must appear in the label or the keywords, in any order: "inv new" finds "New invoice".
function filter(dialog) {
    const { input, recent, results, empty, status } = partsOf(dialog);
    const words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
    dialog.querySelectorAll(ITEM).forEach((item) => {
        if (item.closest('[data-command-results], [data-command-recent]')) {
            return;
        }
        const text = `${labelOf(item)} ${item.dataset.keywords ?? ''}`.toLowerCase();
        item.hidden = !words.every((word) => text.includes(word));
    });
    dialog.querySelectorAll('[data-command-group]').forEach((group) => {
        group.hidden = !group.querySelector(`${ITEM}:not([hidden])`);
    });
    // Recent choices only before anything is typed; your app's results only after.
    recent.hidden = words.length > 0 || !recent.querySelector(ITEM);
    if (words.length === 0) {
        results.replaceChildren();
    }
    results.hidden = !results.querySelector(ITEM);

    const count = visibleItems(dialog).length;
    empty.hidden = count > 0 || dialog.hasAttribute('data-searching');
    status.textContent = words.length === 0 ? '' : count === 1 ? '1 result' : `${count} results`;
    highlight(dialog, visibleItems(dialog)[0] ?? null);
}

// --- Your app's results (suggest-url) ----------------------------------------------------------------------------------

const timers = new WeakMap();
const requests = new WeakMap();

// The same answer the search widget's suggest-url takes: a list (or {"data": [...]}) of strings, or of label with an
// optional meta, href and group. Built with textContent, so nothing in it can inject markup.
function showResults(dialog, answer) {
    const { results } = partsOf(dialog);
    const rows = (Array.isArray(answer) ? answer : answer?.data ?? []).map((row) => (typeof row === 'string' ? { label: row } : row));
    results.replaceChildren(...rows.filter((row) => row?.label).map((row) => {
        const item = Object.assign(document.createElement('div'), {
            className: 'text-foreground aria-selected:bg-field flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm',
            id: `command-result-${Math.random().toString(36).slice(2, 10)}`,
        });
        item.setAttribute('role', 'option');
        item.setAttribute('aria-selected', 'false');
        item.dataset.commandItem = '';
        if (row.href) {
            item.dataset.href = row.href;
        }
        const label = Object.assign(document.createElement('span'), { className: 'min-w-0 flex-1 truncate', textContent: row.label });
        label.dataset.commandLabel = '';
        item.append(label);
        if (row.meta || row.group) {
            item.append(Object.assign(document.createElement('span'), { className: 'text-muted shrink-0 text-xs', textContent: row.meta ?? row.group }));
        }

        return item;
    }));
}

function search(dialog) {
    const url = dialog.dataset.suggestUrl;
    const query = partsOf(dialog).input.value.trim();
    clearTimeout(timers.get(dialog));
    requests.get(dialog)?.abort();
    if (!url || query === '') {
        dialog.removeAttribute('data-searching');
        partsOf(dialog).busy.hidden = true;

        return;
    }
    dialog.setAttribute('data-searching', '');
    partsOf(dialog).busy.hidden = false;
    timers.set(dialog, setTimeout(async () => {
        const controller = new AbortController();
        requests.set(dialog, controller);
        try {
            const target = new URL(url, location.href);
            target.searchParams.set('q', query);
            const response = await fetch(target, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal });
            showResults(dialog, response.ok ? await response.json() : []);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            showResults(dialog, []);
        }
        dialog.removeAttribute('data-searching');
        partsOf(dialog).busy.hidden = true;
        filter(dialog);
    }, WAIT_MS));
}

// --- Recent choices (recent) -------------------------------------------------------------------------------------------

const recentKey = (dialog) => `bladewell-command-recent:${dialog.id}`;

function readRecent(dialog) {
    try {
        return JSON.parse(localStorage.getItem(recentKey(dialog)) ?? '[]');
    } catch {
        return [];
    }
}

// Only items that open a page are remembered: a click's action may not make sense to repeat out of its context.
function remember(dialog, item) {
    if (!dialog.hasAttribute('data-recent') || !item.dataset.href) {
        return;
    }
    const entry = { label: labelOf(item), href: item.dataset.href };
    const kept = readRecent(dialog).filter((other) => other.href !== entry.href);
    try {
        localStorage.setItem(recentKey(dialog), JSON.stringify([entry, ...kept].slice(0, RECENT_MAX)));
    } catch {
        // Storage full or blocked: the palette works the same, without recent choices.
    }
}

function showRecent(dialog) {
    if (!dialog.hasAttribute('data-recent')) {
        return;
    }
    const { recent } = partsOf(dialog);
    const heading = Object.assign(document.createElement('div'), { className: 'text-muted px-3 pt-2 pb-1 text-xs font-medium', textContent: 'Recent' });
    showResults(dialog, readRecent(dialog));
    const { results } = partsOf(dialog);
    recent.replaceChildren(heading, ...results.children);
}

// --- Opening, choosing -------------------------------------------------------------------------------------------------

const dialogOf = (target) => {
    const dialog = typeof target === 'string' ? document.getElementById(target) : target;
    if (!dialog?.matches?.(DIALOG)) {
        console.warn('Bladewell command: no command palette on this page for', target);

        return null;
    }

    return dialog;
};

function open(target) {
    const dialog = dialogOf(target);
    if (!dialog || dialog.open) {
        return;
    }
    const { input } = partsOf(dialog);
    input.value = '';
    showRecent(dialog);
    filter(dialog);
    dialog.showModal();
    input.focus();
    dialog.dispatchEvent(new CustomEvent('command:opened', { bubbles: true }));
}

function close(target) {
    const dialog = dialogOf(target);
    if (dialog?.open) {
        dialog.close();
    }
}

// Set while a keyboard choice clicks an item, so the click listener below doesn't choose it a second time.
let choosing = false;

// An item's link is opened only if it's a page (http or https, this site's or another's), an email or a phone number.
// A javascript: or data: link, from your app's results or the recent list, would run as code here.
const OPENABLE = new Set(['http:', 'https:', 'mailto:', 'tel:']);
function openable(href) {
    try {
        return OPENABLE.has(new URL(href, window.location.href).protocol);
    } catch {
        return false;
    }
}

function choose(dialog, item, { viaClick = false } = {}) {
    const href = item.dataset.href;
    if (href && !openable(href)) {
        console.warn('Bladewell command: not opening an item whose link isn\'t a page, email or phone number:', href);
        close(dialog);

        return;
    }
    remember(dialog, item);
    dialog.dispatchEvent(new CustomEvent('command:chosen', { bubbles: true, detail: { item } }));
    close(dialog);
    if (href) {
        if (item.hasAttribute('wire:navigate') && window.Livewire?.navigate) {
            window.Livewire.navigate(href);
        } else {
            window.location.assign(href);
        }

        return;
    }
    // A keyboard choice clicks the item, so wire:click and your own listeners run as they do for a pointer.
    if (!viaClick) {
        choosing = true;
        item.click();
        choosing = false;
    }
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest?.('[data-command-open]');
    if (opener) {
        open(opener.dataset.commandOpen);

        return;
    }
    const dialog = event.target.closest?.(DIALOG);
    if (!dialog) {
        return;
    }
    // The dimmed area around the panel is the dialog itself: a click there closes it.
    if (event.target === dialog) {
        close(dialog);

        return;
    }
    const item = event.target.closest(ITEM);
    if (item && !choosing) {
        choose(dialog, item, { viaClick: true });
    }
});

// The pointer moves the highlight too, so Enter after pointing chooses what's under it.
document.addEventListener('pointermove', (event) => {
    const item = event.target.closest?.(`${DIALOG} ${ITEM}`);
    if (item && item.getAttribute('aria-selected') !== 'true') {
        highlight(item.closest(DIALOG), item);
    }
});

document.addEventListener('input', (event) => {
    const dialog = event.target.matches?.('[data-command-input]') ? event.target.closest(DIALOG) : null;
    if (dialog) {
        search(dialog);
        filter(dialog);
    }
});

document.addEventListener('keydown', (event) => {
    // The shortcut: Ctrl (Cmd on a Mac) and the palette's key, from anywhere. Again, it closes.
    if ((isMac ? event.metaKey : event.ctrlKey) && !event.altKey && !event.shiftKey && event.key.length === 1) {
        const dialog = document.querySelector(`${DIALOG}[data-shortcut="${CSS.escape(event.key.toLowerCase())}"]`);
        if (dialog) {
            event.preventDefault();
            dialog.open ? close(dialog) : open(dialog);

            return;
        }
    }
    const dialog = event.target.matches?.('[data-command-input]') ? event.target.closest(DIALOG) : null;
    if (!dialog) {
        return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        move(dialog, event.key === 'ArrowDown' ? 1 : -1);
    } else if (event.key === 'Enter' && !event.isComposing) {
        const item = dialog.querySelector(`${ITEM}[aria-selected="true"]`);
        if (item) {
            event.preventDefault();
            choose(dialog, item);
        }
    }
});

// `close` doesn't bubble, so listen in the capture phase.
document.addEventListener('close', (event) => {
    if (event.target.matches?.(DIALOG)) {
        clearTimeout(timers.get(event.target));
        requests.get(event.target)?.abort();
        event.target.dispatchEvent(new CustomEvent('command:closed', { bubbles: true }));
    }
}, true);

// The shortcut's key reads ⌘ on a Mac.
function labelKeys(scope = document) {
    if (isMac) {
        scope.querySelectorAll('kbd[data-command-key]').forEach((kbd) => {
            kbd.textContent = `⌘${kbd.dataset.commandKey.toUpperCase()}`;
        });
    }
}

// From a Livewire component ($this->dispatch('command-open', id: 'palette')) or any script.
window.addEventListener('command-open', (event) => open(event.detail?.id));
window.addEventListener('command-close', (event) => close(event.detail?.id));

// After a Livewire render the items may have changed: filter them again by what's typed, and relabel the keys.
const hookIntoLivewire = (Livewire) => Livewire.hook('morphed', ({ el }) => {
    labelKeys(el);
    document.querySelectorAll(`${DIALOG}[open]`).forEach(filter);
});
if (window.Livewire) {
    hookIntoLivewire(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => hookIntoLivewire(window.Livewire));
}

export const command = { open, close };

// Global, as modal and toast are, so a script on the page can call it without an import.
window.command = command;

labelKeys();
