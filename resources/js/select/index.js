// Drives <x-widget.select>. Open/close, Esc and click-outside come from the native popover;
// this adds keyboard navigation, type-to-jump, search filtering (or, with search-url, searching your app) and positioning
// under the field.
import { closeIfOutOfView, placeBeside } from '../field';

const VIEWPORT_EDGE = 16;
const GAP = 4;

// Set up once per element. Not a data attribute: Livewire's morph removes attributes the server didn't render, and a
// second set-up would add every listener twice.
const ready = new WeakSet();

// One per <x-widget.select>: its parts, read once, and what changes while it's used. Every function below takes it, so
// what a function reads and changes is in its arguments rather than in a scope it happens to sit in.
function selectOf(root) {
    const trigger = root.querySelector('[data-select-trigger]');
    const popup = root.querySelector('[data-select-popup]');

    return {
        root,
        trigger,
        popup,
        input: root.querySelector('[data-select-input]'),
        display: root.querySelector('[data-select-display]'),
        meta: root.querySelector('[data-select-meta]'),
        search: popup.querySelector('[data-select-search]'),
        empty: popup.querySelector('[data-select-empty]'),
        // multiple: picking toggles an option and the list stays open; each choice is its own hidden name[] input.
        multiple: root.dataset.multiple === 'true',
        values: root.querySelector('[data-select-values]'),
        // multiple with wire:model or x-model: the hidden native multi-select they're bound to.
        model: root.querySelector('[data-select-model]'),
        clearButton: root.querySelector('[data-select-clear]'),
        // multiple only: list (labels joined), chips (one removable chip each) or count ("3 selected").
        displayMode: root.dataset.display ?? 'list',
        chips: root.querySelector('[data-select-chips]'),
        chipTemplate: root.querySelector('[data-select-chip-template]'),
        // The field frame's box, so the list lines up with the whole field rather than just the button.
        anchor: trigger.parentElement,
        // search-url: typing asks your app, and its answers become the options. What's chosen stays in the list (hidden
        // when it doesn't match), so it's never lost; under search-min-length, the options the server drew show again.
        searchUrl: root.dataset.searchUrl,
        listbox: popup.querySelector('[role="listbox"]'),
        optionTemplate: popup.querySelector('template[data-select-option-template]'),
        status: popup.querySelector('[data-select-status]'),

        // The highlighted option's place among the visible ones, or -1.
        active: -1,
        // Type-to-jump: what's been typed, forgotten after half a second.
        typed: '',
        typedTimer: null,
        // The server-drawn options' order as a search began: results move them, and clearing the search puts them back.
        drawnOrder: null,
        answers: new Map(),
        searchTimer: null,
        searchRequest: null,
        made: 0,
        // The one listener that follows scrolls and resizes while the list is open.
        reposition: null,
    };
}

// Read each time, not once: a Livewire render or other script can replace the options (a city list that follows the
// country), and the new ones must be pickable.
const optionsOf = (select) => [...select.popup.querySelectorAll('[role="option"]')];
// Readonly: shown and submitted, but the list never opens, so nothing can be picked or removed.
const isReadonly = (select) => select.trigger.getAttribute('aria-readonly') === 'true';
// The options the server drew are the ones a search didn't add (data-select-found), read as they are now: a Livewire
// render or a script may have replaced them since (a city list that follows the country). They show before any search,
// in their order, along with whatever is chosen.
const isFound = (option) => option.hasAttribute('data-select-found');
const isEnabled = (option) => !option.hasAttribute('aria-disabled');
const isOpen = (select) => select.popup.matches(':popover-open');
const visibleOf = (select) => optionsOf(select).filter((option) => !option.hidden);
// With a search box, focus lives there; otherwise it stays on the trigger.
const focusOwner = (select) => select.search ?? select.trigger;
const chosenOf = (select) => optionsOf(select).filter((option) => option.getAttribute('aria-selected') === 'true');

function setActive(select, index) {
    const list = visibleOf(select);
    optionsOf(select).forEach((option) => option.removeAttribute('data-active'));
    select.active = list[index] ? index : -1;

    if (select.active === -1) {
        focusOwner(select).removeAttribute('aria-activedescendant');

        return;
    }
    list[select.active].setAttribute('data-active', '');
    list[select.active].scrollIntoView({ block: 'nearest' });
    focusOwner(select).setAttribute('aria-activedescendant', list[select.active].id);
}

// Next enabled option from `from` in `step` direction; stays put when there isn't one.
function move(select, step, from = select.active) {
    const list = visibleOf(select);
    for (let i = from + step; i >= 0 && i < list.length; i += step) {
        if (isEnabled(list[i])) {
            setActive(select, i);

            return;
        }
    }
}

function drawChips(select, picked) {
    const { chips, chipTemplate, trigger } = select;
    chips.querySelectorAll('[data-select-chip]').forEach((chip) => chip.remove());
    const placeholder = chips.querySelector('[data-select-chips-placeholder]');
    for (const option of picked) {
        const chip = chipTemplate.content.firstElementChild.cloneNode(true);
        const remove = chip.querySelector('[data-select-chip-remove]');
        chip.querySelector('[data-select-chip-label]').textContent = option.dataset.label;
        remove.dataset.value = option.dataset.value;
        remove.setAttribute('aria-label', `Remove ${option.dataset.label}`);
        remove.disabled = trigger.disabled;
        remove.hidden = isReadonly(select);
        placeholder.before(chip);
    }
    placeholder.hidden = picked.length > 0;
}

// A Livewire render can change what the select is (multiple, how several show, where searches go) or draw again the
// parts the script writes into; the script sets up once, so those are read again where they're used.
function reread(select) {
    const { root, popup } = select;
    select.multiple = root.dataset.multiple === 'true';
    select.displayMode = root.dataset.display ?? 'list';
    select.searchUrl = root.dataset.searchUrl;
    select.display = root.querySelector('[data-select-display]');
    select.meta = root.querySelector('[data-select-meta]');
    select.values = root.querySelector('[data-select-values]');
    select.model = root.querySelector('[data-select-model]');
    select.clearButton = root.querySelector('[data-select-clear]');
    select.chips = root.querySelector('[data-select-chips]');
    select.chipTemplate = root.querySelector('[data-select-chip-template]');
    select.empty = popup.querySelector('[data-select-empty]');
}

// Redraws the trigger from the options' aria-selected: one label, several joined, a count, or chips.
function refresh(select) {
    reread(select);
    const { root, display, meta, clearButton } = select;
    const picked = chosenOf(select);
    const text = picked.map((option) => option.dataset.label).join(', ');
    const shown = select.displayMode === 'count' && picked.length > 1 ? root.dataset.countLabel.replace(':count', String(picked.length)) : text;
    display.textContent = shown || display.dataset.placeholder;
    display.toggleAttribute('data-empty', !text);
    text ? (display.title = text) : display.removeAttribute('title');
    if (select.chips) {
        drawChips(select, picked);
    }
    meta.textContent = select.multiple ? '' : (picked[0]?.dataset.meta ?? '');
    if (clearButton) {
        clearButton.hidden = picked.length === 0;
    }
}

// Writes the choice to the hidden input(s) and announces it.
function commit(select) {
    const { root, input, values, model, multiple } = select;
    const picked = chosenOf(select);
    const target = multiple ? (model ?? values) : input;
    if (model) {
        // A choice a search brought isn't among the bound select's options yet; add it, or it couldn't be selected.
        picked.filter((option) => ![...model.options].some((item) => item.value === option.dataset.value))
            .forEach((option) => model.add(new Option('', option.dataset.value)));
        const chosenValues = new Set(picked.map((option) => option.dataset.value));
        [...model.options].forEach((item) => {
            item.selected = chosenValues.has(item.value);
        });
    }
    if (multiple) {
        values.replaceChildren(...picked.map((option) => {
            const hidden = Object.assign(document.createElement('input'), {
                type: 'hidden',
                name: values.dataset.name ? `${values.dataset.name}[]` : '',
                value: option.dataset.value,
            });
            // A select outside its <form> (form="…") keeps submitting with it, as the server-rendered inputs did.
            if (values.dataset.form) {
                hidden.setAttribute('form', values.dataset.form);
            }

            return hidden;
        }));
    } else {
        input.value = picked[0]?.dataset.value ?? '';
    }
    // Both events: tools like Alpine x-model and Livewire wire:model listen for `input`, plain forms for `change`.
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.dispatchEvent(new Event('change', { bubbles: true }));
    const detail = multiple
        ? { values: picked.map((option) => option.dataset.value), labels: picked.map((option) => option.dataset.label) }
        : { value: picked[0]?.dataset.value ?? '', label: picked[0]?.dataset.label ?? '' };
    // select:change matches the other widgets' events; select-change is the name it was first sent as, and stays.
    for (const name of ['select:change', 'select-change']) {
        root.dispatchEvent(new CustomEvent(name, { bubbles: true, detail }));
    }
}

function choose(select, option) {
    if (!option || option.hasAttribute('aria-disabled')) {
        return;
    }
    if (select.multiple) {
        option.setAttribute('aria-selected', String(option.getAttribute('aria-selected') !== 'true'));
        refresh(select);
        commit(select);

        return;
    }
    optionsOf(select).forEach((item) => item.setAttribute('aria-selected', String(item === option)));
    refresh(select);
    commit(select);
    select.popup.hidePopover();
    select.trigger.focus();
}

// A chip's own remove button, and Backspace on the closed field for the last chip.
function unpick(select, option) {
    option?.setAttribute('aria-selected', 'false');
    refresh(select);
    commit(select);
}

// An option as your app sent it: a string, or value and label (id and name work too), with meta and disabled.
function normalise(item) {
    const entry = typeof item === 'string' || typeof item === 'number' ? { value: item, label: item } : (item ?? {});
    const value = String(entry.value ?? entry.id ?? entry.label ?? '');

    return { value, label: String(entry.label ?? entry.name ?? value), meta: entry.meta ?? '', disabled: Boolean(entry.disabled) };
}

// An option shows an answer as your app sent it now: its label, meta and whether it's disabled, which may have
// changed since the option was drawn or last found.
function fill(option, item) {
    Object.assign(option.dataset, { value: item.value, label: item.label, meta: item.meta });
    option.title = item.label;
    option.querySelector('[data-select-option-label]').textContent = item.label;
    const meta = option.querySelector('[data-select-option-meta]');
    if (meta) {
        meta.textContent = item.meta;
    }
    if (item.disabled) {
        option.setAttribute('aria-disabled', 'true');
    } else {
        option.removeAttribute('aria-disabled');
    }
}

function optionFor(select, item) {
    const option = select.optionTemplate.content.firstElementChild.cloneNode(true);
    select.made += 1;
    option.id = `${select.listbox.id.replace(/-listbox$/, '')}-found-${select.made}`;
    option.setAttribute('data-select-found', '');
    fill(option, item);

    return option;
}

function say(select, text) {
    if (select.status) {
        select.status.textContent = text;
    }
}

// Shows the options for these answers, in their order; null brings back the ones the server drew, in its order,
// and whatever is chosen.
function showFound(select, items) {
    const { empty } = select;
    const wanted = new Map((items ?? []).map((item) => [item.value, item]));
    if (items) {
        select.drawnOrder ??= optionsOf(select).filter((option) => !isFound(option));
    } else {
        const drawn = optionsOf(select).filter((option) => !isFound(option));
        const before = (select.drawnOrder ?? []).filter((option) => drawn.includes(option));
        [...before, ...drawn.filter((option) => !before.includes(option))].forEach((option) => empty.before(option));
        optionsOf(select).filter(isFound).forEach((option) => empty.before(option));
        select.drawnOrder = null;
    }
    optionsOf(select).forEach((option) => {
        const picked = option.getAttribute('aria-selected') === 'true';
        const keep = items ? wanted.has(option.dataset.value) : !isFound(option) || picked;
        if (!keep && !picked && isFound(option)) {
            option.remove();
        } else {
            option.hidden = !keep;
        }
    });
    for (const item of wanted.values()) {
        const existing = optionsOf(select).find((option) => option.dataset.value === item.value);
        if (existing) {
            fill(existing, item);
        }
        const option = existing ?? optionFor(select, item);
        option.hidden = false;
        empty.before(option);
    }
    // A chosen option's label may have changed with the answer: the field shows the new one.
    if (items) {
        refresh(select);
    }
    empty.textContent = empty.dataset.noResults;
    empty.hidden = visibleOf(select).length > 0;
    setActive(select, -1);
    move(select, 1, -1);
    if (items) {
        say(select, items.length === 0 ? empty.dataset.noResults : '');
    }
}

async function searchApp(select, query) {
    const { root, listbox, empty, search } = select;
    const term = query.trim();
    clearTimeout(select.searchTimer);
    select.searchRequest?.abort();
    listbox.removeAttribute('aria-busy');
    if (term.length < Number(root.dataset.searchMinLength ?? 2)) {
        showFound(select, null);
        say(select, '');

        return;
    }
    if (select.answers.has(term)) {
        showFound(select, select.answers.get(term));

        return;
    }
    select.searchTimer = setTimeout(async () => {
        const request = new AbortController();
        select.searchRequest = request;
        listbox.setAttribute('aria-busy', 'true');
        if (visibleOf(select).length === 0) {
            empty.textContent = empty.dataset.searching;
            empty.hidden = false;
        }
        say(select, empty.dataset.searching);
        const url = new URL(select.searchUrl, window.location.href);
        url.searchParams.set('q', term);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: request.signal });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            const body = await response.json();
            const items = (Array.isArray(body) ? body : (Array.isArray(body?.data) ? body.data : [])).map(normalise).filter((item) => item.value !== '');
            select.answers.set(term, items);
            // Typing may have moved on while this was on its way; only the latest answer counts.
            if (search.value.trim() === term) {
                showFound(select, items);
            }
        } catch (error) {
            // Only the latest search's failure shows: an older one finishing late mustn't replace newer results.
            if (error.name !== 'AbortError' && select.searchRequest === request && search.value.trim() === term) {
                optionsOf(select).forEach((option) => { option.hidden = true; });
                empty.textContent = empty.dataset.searchFailed;
                empty.hidden = false;
                say(select, empty.dataset.searchFailed);
            }
        } finally {
            if (select.searchRequest === request) {
                listbox.removeAttribute('aria-busy');
            }
        }
    }, 250);
}

function filter(select, query) {
    if (select.searchUrl) {
        searchApp(select, query);

        return;
    }
    const needle = query.trim().toLowerCase();
    optionsOf(select).forEach((option) => {
        option.hidden = needle !== '' && !option.dataset.label.toLowerCase().includes(needle);
    });
    select.empty.hidden = visibleOf(select).length > 0;
    setActive(select, -1);
    move(select, 1, -1);
}

function position(select) {
    const { anchor, popup, listbox } = select;
    if (closeIfOutOfView(anchor, popup)) {
        return;
    }
    const box = anchor.getBoundingClientRect();
    popup.style.width = `${box.width}px`;

    popup.style.top = `${placeBeside(popup, box, { gap: GAP, edge: VIEWPORT_EDGE, scroller: listbox })}px`;
    popup.style.left = `${box.left}px`;
}

// Native <select> behaviour: typing letters jumps to the first matching option.
function typeahead(select, character) {
    select.typed += character.toLowerCase();
    clearTimeout(select.typedTimer);
    select.typedTimer = setTimeout(() => { select.typed = ''; }, 500);

    const list = visibleOf(select);
    const match = list.findIndex((option) => isEnabled(option) && option.dataset.label.toLowerCase().startsWith(select.typed));
    if (match !== -1) {
        setActive(select, match);
    }
}

// The keys the trigger and the search box share. True when it took the key.
function handleListKeys(select, event) {
    const { popup } = select;
    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            isOpen(select) ? move(select, 1) : popup.showPopover();
            break;
        case 'ArrowUp':
            event.preventDefault();
            isOpen(select) ? move(select, -1) : popup.showPopover();
            break;
        case 'Home':
        case 'End':
            if (isOpen(select)) {
                event.preventDefault();
                // Start just outside the list so the first/last enabled option wins.
                event.key === 'Home' ? move(select, 1, -1) : move(select, -1, visibleOf(select).length);
            }
            break;
        case 'Enter':
            // Stops the button's native popover toggle so Enter picks instead. While the list is open, Enter
            // never falls through, even with nothing highlighted: in the search box it would submit the form.
            if (isOpen(select)) {
                event.preventDefault();
                if (select.active !== -1) {
                    choose(select, visibleOf(select)[select.active]);
                }
            }
            break;
        case 'Tab':
            if (isOpen(select)) {
                popup.hidePopover();
            }
            break;
        default:
            return false;
    }

    return true;
}

function triggerKey(select, event) {
    if (select.chips && event.key === 'Backspace' && !isOpen(select) && !isReadonly(select)) {
        unpick(select, chosenOf(select).at(-1));
    }
    if (handleListKeys(select, event)) {
        return;
    }
    // Space in the middle of type-ahead ("new z…") is part of the search, as in a native select; otherwise it picks.
    if (event.key === ' ' && isOpen(select) && select.typed !== '') {
        event.preventDefault();
        typeahead(select, ' ');
    } else if (event.key === ' ' && isOpen(select) && select.active !== -1) {
        event.preventDefault();
        choose(select, visibleOf(select)[select.active]);
    } else if (isOpen(select) && event.key.length === 1 && event.key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey) {
        typeahead(select, event.key);
    }
}

// The list opened or closed: the trigger says so, and while it's open it follows the field as the page scrolls or
// resizes, and starts on the chosen option, like a native select.
function toggled(select, open) {
    // Before a search starts with the old address.
    reread(select);
    const { trigger, popup, search } = select;
    trigger.setAttribute('aria-expanded', String(open));

    if (!open) {
        window.removeEventListener('scroll', select.reposition, true);
        window.removeEventListener('resize', select.reposition);
        window.visualViewport?.removeEventListener('resize', select.reposition);
        setActive(select, -1);
        if (search) {
            search.value = '';
            filter(select, '');
            setActive(select, -1);
        }

        return;
    }

    position(select);
    popup.style.visibility = '';
    setActive(select, visibleOf(select).findIndex((option) => option.getAttribute('aria-selected') === 'true'));
    search?.focus();
    // Capture phase so scrolling any ancestor also repositions it.
    window.addEventListener('scroll', select.reposition, true);
    window.addEventListener('resize', select.reposition);
    window.visualViewport?.addEventListener('resize', select.reposition);
}

function initSelect(root) {
    if (ready.has(root)) {
        return;
    }
    ready.add(root);

    const select = selectOf(root);
    const { trigger, popup, search } = select;
    select.reposition = () => position(select);

    // Readonly: nothing is removed, by a chip's button, Backspace or the clear button. On the root, so chips and a clear
    // button a render draws again still work.
    root.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-select-chip-remove]');
        if (remove?.closest('[data-select-chips]') && !isReadonly(select)) {
            unpick(select, optionsOf(select).find((option) => option.dataset.value === remove.dataset.value));
            trigger.focus();
        }
    });
    root.addEventListener('click', (event) => {
        if (!event.target.closest('[data-select-clear]') || isReadonly(select)) {
            return;
        }
        optionsOf(select).forEach((option) => option.setAttribute('aria-selected', 'false'));
        refresh(select);
        commit(select);
        trigger.focus();
    });

    trigger.addEventListener('keydown', (event) => triggerKey(select, event));
    search?.addEventListener('keydown', (event) => handleListKeys(select, event));
    search?.addEventListener('input', () => filter(select, search.value));

    popup.addEventListener('click', (event) => {
        const option = event.target.closest('[role="option"]');
        if (option) {
            choose(select, option);
        }
    });
    popup.addEventListener('mousemove', (event) => {
        const option = event.target.closest('[role="option"]:not([aria-disabled])');
        if (option && !option.hasAttribute('data-active')) {
            setActive(select, visibleOf(select).indexOf(option));
        }
    });
    // Keep focus on the trigger/search while clicking an option.
    popup.addEventListener('mousedown', (event) => {
        if (event.target.closest('[role="option"]')) {
            event.preventDefault();
        }
    });

    popup.addEventListener('beforetoggle', (event) => {
        if (event.newState === 'open' && isReadonly(select)) {
            event.preventDefault();

            return;
        }
        if (event.newState === 'open') {
            // Hidden until positioned, otherwise it flashes at the popover default (screen centre).
            popup.style.visibility = 'hidden';
        }
    });
    popup.addEventListener('toggle', (event) => toggled(select, event.newState === 'open'));
}

// For a select that stays put while the markup around it is swapped (a table's filters toolbar): takes the fresh
// copy's option details, each option's meta (e.g. a count) and whether it's disabled, and keeps what's chosen.
export function refreshOptions(select, fresh) {
    const next = new Map([...fresh.querySelectorAll('[role="option"][data-value]')].map((option) => [option.dataset.value, option]));
    select.querySelectorAll('[role="option"][data-value]').forEach((option) => {
        const source = next.get(option.dataset.value);
        if (!source) {
            return;
        }
        option.dataset.meta = source.dataset.meta ?? '';
        const meta = option.querySelector('[data-select-option-meta]');
        if (meta) {
            meta.textContent = source.dataset.meta ?? '';
        }
        if (source.hasAttribute('aria-disabled')) {
            option.setAttribute('aria-disabled', 'true');
        } else {
            option.removeAttribute('aria-disabled');
        }
    });
    const shown = select.querySelector('[data-select-meta]');
    if (shown && select.dataset.multiple !== 'true') {
        shown.textContent = select.querySelector('[role="option"][aria-selected="true"]')?.dataset.meta ?? '';
    }
}

export function initSelects(scope = document) {
    scope.querySelectorAll('[data-select]').forEach(initSelect);
}

initSelects();

// Selects added to the page later (a Livewire render or wire:navigate, fetched HTML) set themselves up.
new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            (node.matches('[data-select]') ? [node] : node.querySelectorAll('[data-select]')).forEach(initSelect);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });
