// Behaviour for <x-widget.search>: the clear button, the keyboard shortcut, and suggestions as you type (suggest-url). Delegated from `document`, so fields added later work without
// re-initialising. Client-side filtering is only for convenience; the Form Request must validate the same rules.
import { closeIfOutOfView, on, placeBeside } from '../field';

// --- Search: the clear button ----------------------------------------------------------

// Empties the box and tells listeners, as deleting the text would: a table's filters refilter, wire:model updates.
// Showing and hiding the button is CSS (see search.blade.php).
on('click', '[data-search-clear]', (event, button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input || input.value === '') {
        return;
    }
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.focus();
});

// shortcut="/": the key jumps to the box, unless you're typing somewhere else or a dialog is open over it (then only
// a box inside that dialog counts). The first visible box with that key wins.
document.addEventListener('keydown', (event) => {
    if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey || event.key.length !== 1) {
        return;
    }
    const target = event.target;
    if (target instanceof Element && target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])')) {
        return;
    }
    const dialog = document.querySelector('dialog[open]');
    const box = [...document.querySelectorAll('[data-search-shortcut]')].find((input) => input.dataset.searchShortcut.toLowerCase() === event.key.toLowerCase()
        && input.offsetParent !== null && (!dialog || dialog.contains(input)));
    if (box) {
        event.preventDefault();
        box.focus();
        box.select();
    }
});

// --- Suggestions (suggest-url) ---------------------------------------------------------

// As you type, the box asks suggest-url (GET ?q=…) and lists what comes back under it. The arrow keys move through the
// list while focus stays in the box; Enter takes the highlighted one, or with none highlighted submits what was typed,
// as a search box does. A suggestion with an href opens that page; otherwise its value fills the box and, in a form,
// searches. Requests wait for a pause in typing, an earlier one still running is dropped, and answers are kept per
// query for as long as the page is open.
const WAIT_MS = 200;
const GAP = 6;
const VIEWPORT_EDGE = 8;
const boxes = new WeakMap();

function suggestionsFor(input) {
    if (!boxes.has(input)) {
        // The box around the input holds the list, the status line and the templates.
        const root = input.parentElement;
        boxes.set(input, {
            input,
            anchor: root,
            list: root.querySelector('[data-search-suggestions]'),
            status: root.querySelector('[data-search-status]'),
            template: root.querySelector('template[data-search-suggestion]'),
            groupTemplate: root.querySelector('template[data-search-group]'),
            messages: JSON.parse(input.dataset.suggestMessages ?? '{}'),
            cache: new Map(),
            options: [],
            active: -1,
            timer: null,
            request: null,
            choosing: false,
        });
    }

    return boxes.get(input);
}

const isOpen = (box) => box.list.matches(':popover-open');

function say(box, text) {
    box.status.textContent = text;
}

function close(box) {
    if (isOpen(box)) {
        box.list.hidePopover();
    }
    box.input.setAttribute('aria-expanded', 'false');
    box.input.removeAttribute('aria-activedescendant');
    box.active = -1;
}

function position(box) {
    if (closeIfOutOfView(box.anchor, box.list)) {
        box.input.setAttribute('aria-expanded', 'false');

        return;
    }
    const rect = box.anchor.getBoundingClientRect();
    box.list.style.width = `${rect.width}px`;
    box.list.style.top = `${placeBeside(box.list, rect, { gap: GAP, edge: VIEWPORT_EDGE, scroller: box.list })}px`;
    box.list.style.left = `${rect.left}px`;
}

function setActive(box, index) {
    box.options.forEach((option, i) => option.setAttribute('aria-selected', String(i === index)));
    box.active = index;
    if (index === -1) {
        box.input.removeAttribute('aria-activedescendant');

        return;
    }
    box.input.setAttribute('aria-activedescendant', box.options[index].id);
    box.options[index].scrollIntoView({ block: 'nearest' });
}

// The label, with the typed text in bold wherever it appears. Built from text nodes, never markup, so a suggestion
// can't inject anything.
function fillLabel(element, label, query) {
    element.replaceChildren();
    const lower = label.toLowerCase();
    const needle = query.toLowerCase();
    let at = 0;
    for (let found = lower.indexOf(needle); needle !== '' && found !== -1; found = lower.indexOf(needle, at)) {
        element.append(label.slice(at, found), Object.assign(document.createElement('mark'), { textContent: label.slice(found, found + needle.length) }));
        at = found + needle.length;
    }
    element.append(label.slice(at));
}

// A suggestion as the server sent it: a string, or an object with label (value, meta, href and group optional).
function normalise(item) {
    const entry = typeof item === 'string' ? { label: item } : (item ?? {});
    const label = String(entry.label ?? entry.value ?? '');

    return { label, value: String(entry.value ?? label), meta: entry.meta ?? '', href: entry.href ?? null, group: entry.group ?? null };
}

function render(box, items, query) {
    const suggestions = items.map(normalise).filter((item) => item.label !== '');
    box.list.replaceChildren();
    box.options = [];
    const groups = new Map();
    suggestions.forEach((item, i) => {
        const option = box.template.content.firstElementChild.cloneNode(true);
        option.id = `${box.list.id}-${i}`;
        fillLabel(option.querySelector('[data-suggestion-label]'), item.label, query);
        option.querySelector('[data-suggestion-meta]').textContent = item.meta;
        option.suggestion = item;
        let parent = box.list;
        if (item.group) {
            if (!groups.has(item.group)) {
                const group = box.groupTemplate.content.firstElementChild.cloneNode(true);
                const heading = group.querySelector('[data-group-label]');
                heading.id = `${box.list.id}-group-${groups.size}`;
                heading.textContent = item.group;
                group.setAttribute('aria-labelledby', heading.id);
                box.list.append(group);
                groups.set(item.group, group);
            }
            parent = groups.get(item.group);
        }
        parent.append(option);
        box.options.push(option);
    });
    // Grouped lists show group by group, so the arrow keys follow what's on screen.
    box.options = [...box.list.querySelectorAll('[role="option"]')];
    box.active = -1;
    box.input.removeAttribute('aria-activedescendant');

    if (box.options.length === 0) {
        close(box);
        say(box, box.messages.none ?? '');

        return;
    }
    if (!isOpen(box)) {
        box.list.showPopover();
    }
    box.input.setAttribute('aria-expanded', 'true');
    position(box);
    say(box, (box.messages.count ?? '').replace(':count', String(box.options.length)));
}

async function ask(box) {
    const query = box.input.value.trim();
    box.request?.abort();
    if (query.length < Number(box.input.dataset.suggestMin ?? 2)) {
        close(box);
        say(box, '');

        return;
    }
    if (box.cache.has(query)) {
        render(box, box.cache.get(query), query);

        return;
    }
    const request = new AbortController();
    box.request = request;
    const url = new URL(box.input.dataset.searchSuggest, window.location.href);
    url.searchParams.set('q', query);
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: request.signal });
        if (!response.ok) {
            throw new Error(String(response.status));
        }
        const body = await response.json();
        const items = Array.isArray(body) ? body : (Array.isArray(body?.data) ? body.data : []);
        box.cache.set(query, items);
        // The box may have changed while this was on its way; only the latest answer counts.
        if (box.input.value.trim() === query) {
            render(box, items, query);
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            close(box);
            say(box, box.messages.failed ?? '');
        }
    }
}

// A suggestion's link is opened only if it's a page (http or https, this site's or another's), an email or a phone
// number. A javascript: or data: link, from a name or URL someone typed into your app, would run as code here.
const OPENABLE = new Set(['http:', 'https:', 'mailto:', 'tel:']);
function openable(href) {
    try {
        return OPENABLE.has(new URL(href, window.location.href).protocol);
    } catch {
        return false;
    }
}

function choose(box, option) {
    const { value, href } = option.suggestion;
    close(box);
    if (href) {
        if (openable(href)) {
            window.location.assign(href);
        } else {
            console.warn('Bladewell search: not opening a suggestion whose link isn\'t a page, email or phone number:', href);
        }

        return;
    }
    box.choosing = true;
    box.input.value = value;
    box.input.dispatchEvent(new Event('input', { bubbles: true }));
    box.input.dispatchEvent(new Event('change', { bubbles: true }));
    box.choosing = false;
    box.input.form?.requestSubmit();
}

document.addEventListener('input', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.dataset.searchSuggest) {
        return;
    }
    const box = suggestionsFor(input);
    if (box.choosing) {
        return;
    }
    clearTimeout(box.timer);
    box.timer = setTimeout(() => ask(box), WAIT_MS);
});

document.addEventListener('keydown', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || !input.dataset.searchSuggest) {
        return;
    }
    const box = suggestionsFor(input);
    const count = box.options.length;
    switch (event.key) {
        case 'ArrowDown':
        case 'ArrowUp':
            if (count === 0) {
                return;
            }
            event.preventDefault();
            if (!isOpen(box)) {
                box.list.showPopover();
                input.setAttribute('aria-expanded', 'true');
                position(box);
            }
            setActive(box, event.key === 'ArrowDown' ? (box.active + 1) % count : (box.active <= 0 ? count - 1 : box.active - 1));
            break;
        case 'Enter':
            if (isOpen(box) && box.active !== -1) {
                event.preventDefault();
                choose(box, box.options[box.active]);
            }
            break;
        case 'Escape':
            // First Esc closes the list; the next one is the browser's, which empties a search box.
            if (isOpen(box)) {
                event.preventDefault();
                close(box);
            }
            break;
        case 'Tab':
            close(box);
            break;
    }
});

// A press on a suggestion mustn't take focus from the box (the list would close on blur first).
document.addEventListener('pointerdown', (event) => {
    if (event.target.closest?.('[data-search-suggestions] [role="option"]')) {
        event.preventDefault();
    }
});

on('click', '[data-search-suggestions] [role="option"]', (event, option) => {
    const input = document.querySelector(`[aria-controls="${CSS.escape(option.closest('[data-search-suggestions]').id)}"][data-search-suggest]`);
    if (input) {
        choose(suggestionsFor(input), option);
    }
});

document.addEventListener('focusout', (event) => {
    const input = event.target;
    if (input instanceof HTMLInputElement && input.dataset.searchSuggest) {
        close(suggestionsFor(input));
    }
});

// Kept under the box as the page scrolls or resizes; closed when the box scrolls out of sight.
const reposition = () => document.querySelectorAll('[data-search-suggestions]:popover-open').forEach((list) => {
    const input = document.querySelector(`[aria-controls="${CSS.escape(list.id)}"][data-search-suggest]`);
    if (input) {
        position(suggestionsFor(input));
    }
});
window.addEventListener('scroll', reposition, { passive: true, capture: true });
window.addEventListener('resize', reposition);
window.visualViewport?.addEventListener('resize', reposition);
