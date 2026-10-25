// Drives <x-widget.select>. Open/close, Esc and click-outside come from the native popover;
// this adds keyboard navigation, type-to-jump, search filtering (or, with search-url, searching your app) and positioning
// under the field.
import { closeIfOutOfView, placeBeside } from '../field';

const VIEWPORT_EDGE = 16;
const GAP = 4;

// Set up once per element. Not a data attribute: Livewire's morph removes attributes the server didn't render, and a
// second set-up would add every listener twice.
const ready = new WeakSet();

function initSelect(root) {
    if (ready.has(root)) {
        return;
    }
    ready.add(root);

    const trigger = root.querySelector('[data-select-trigger]');
    const input = root.querySelector('[data-select-input]');
    const display = root.querySelector('[data-select-display]');
    const meta = root.querySelector('[data-select-meta]');
    const popup = root.querySelector('[data-select-popup]');
    const search = popup.querySelector('[data-select-search]');
    const empty = popup.querySelector('[data-select-empty]');
    // Read each time, not once: a Livewire render or other script can replace the options (a city list that follows
    // the country), and the new ones must be pickable.
    const options = () => [...popup.querySelectorAll('[role="option"]')];
    // multiple: picking toggles an option and the list stays open; each choice is its own hidden name[] input.
    const multiple = root.dataset.multiple === 'true';
    const values = root.querySelector('[data-select-values]');
    // multiple with wire:model or x-model: the hidden native multi-select they're bound to.
    const model = root.querySelector('[data-select-model]');
    const clearButton = root.querySelector('[data-select-clear]');
    // Readonly: shown and submitted, but the list never opens, so nothing can be picked or removed.
    const readonly = () => trigger.getAttribute('aria-readonly') === 'true';
    // multiple only: list (labels joined), chips (one removable chip each) or count ("3 selected").
    const displayMode = root.dataset.display ?? 'list';
    const chips = root.querySelector('[data-select-chips]');
    const chipTemplate = root.querySelector('[data-select-chip-template]');
    // The field frame's box, so the list lines up with the whole field rather than just the button.
    const anchor = trigger.parentElement;

    let active = -1;
    let typed = '';
    let typedTimer = null;

    // search-url: typing asks your app, and its answers become the options. What's chosen stays in the list (hidden
    // when it doesn't match), so it's never lost; under search-min-length, the options the server drew show again.
    const searchUrl = root.dataset.searchUrl;
    const listbox = popup.querySelector('[role="listbox"]');
    const optionTemplate = popup.querySelector('template[data-select-option-template]');
    const status = popup.querySelector('[data-select-status]');
    // The options the server drew are the ones a search didn't add (data-select-found), read as they are now: a
    // Livewire render or a script may have replaced them since (a city list that follows the country). They show before
    // any search, in their order, along with whatever is chosen.
    const isFound = (option) => option.hasAttribute('data-select-found');
    // Their order as a search began: results move them, and clearing the search puts them back.
    let drawnOrder = null;
    const answers = new Map();
    let searchTimer = null;
    let searchRequest = null;
    let made = 0;

    const isOpen = () => popup.matches(':popover-open');
    const visible = () => options().filter((option) => !option.hidden);
    // With a search box, focus lives there; otherwise it stays on the trigger.
    const focusOwner = () => search ?? trigger;

    function setActive(index) {
        const list = visible();
        options().forEach((option) => option.removeAttribute('data-active'));
        active = list[index] ? index : -1;

        if (active === -1) {
            focusOwner().removeAttribute('aria-activedescendant');

            return;
        }
        list[active].setAttribute('data-active', '');
        list[active].scrollIntoView({ block: 'nearest' });
        focusOwner().setAttribute('aria-activedescendant', list[active].id);
    }

    const isEnabled = (option) => !option.hasAttribute('aria-disabled');

    // Next enabled option from `from` in `step` direction; stays put when there isn't one.
    function move(step, from = active) {
        const list = visible();
        for (let i = from + step; i >= 0 && i < list.length; i += step) {
            if (isEnabled(list[i])) {
                setActive(i);

                return;
            }
        }
    }

    const chosen = () => options().filter((option) => option.getAttribute('aria-selected') === 'true');

    function drawChips(picked) {
        chips.querySelectorAll('[data-select-chip]').forEach((chip) => chip.remove());
        const placeholder = chips.querySelector('[data-select-chips-placeholder]');
        for (const option of picked) {
            const chip = chipTemplate.content.firstElementChild.cloneNode(true);
            const remove = chip.querySelector('[data-select-chip-remove]');
            chip.querySelector('[data-select-chip-label]').textContent = option.dataset.label;
            remove.dataset.value = option.dataset.value;
            remove.setAttribute('aria-label', `Remove ${option.dataset.label}`);
            remove.disabled = trigger.disabled;
            remove.hidden = readonly();
            placeholder.before(chip);
        }
        placeholder.hidden = picked.length > 0;
    }

    // Redraws the trigger from the options' aria-selected: one label, several joined, a count, or chips.
    function refresh() {
        const picked = chosen();
        const text = picked.map((option) => option.dataset.label).join(', ');
        const shown = displayMode === 'count' && picked.length > 1 ? root.dataset.countLabel.replace(':count', String(picked.length)) : text;
        display.textContent = shown || display.dataset.placeholder;
        display.toggleAttribute('data-empty', !text);
        text ? (display.title = text) : display.removeAttribute('title');
        if (chips) {
            drawChips(picked);
        }
        meta.textContent = multiple ? '' : (picked[0]?.dataset.meta ?? '');
        if (clearButton) {
            clearButton.hidden = picked.length === 0;
        }
    }

    // Writes the choice to the hidden input(s) and announces it.
    function commit() {
        const picked = chosen();
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

    function choose(option) {
        if (!option || option.hasAttribute('aria-disabled')) {
            return;
        }
        if (multiple) {
            option.setAttribute('aria-selected', String(option.getAttribute('aria-selected') !== 'true'));
            refresh();
            commit();

            return;
        }
        options().forEach((item) => item.setAttribute('aria-selected', String(item === option)));
        refresh();
        commit();
        popup.hidePopover();
        trigger.focus();
    }

    // A chip's own remove button, and Backspace on the closed field for the last chip.
    const unpick = (option) => {
        option?.setAttribute('aria-selected', 'false');
        refresh();
        commit();
    };
    // Readonly: nothing is removed, by a chip's button, Backspace or the clear button.
    chips?.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-select-chip-remove]');
        if (remove && !readonly()) {
            unpick(options().find((option) => option.dataset.value === remove.dataset.value));
            trigger.focus();
        }
    });
    trigger.addEventListener('keydown', (event) => {
        if (chips && event.key === 'Backspace' && !isOpen() && !readonly()) {
            unpick(chosen().at(-1));
        }
    });

    clearButton?.addEventListener('click', () => {
        if (readonly()) {
            return;
        }
        options().forEach((option) => option.setAttribute('aria-selected', 'false'));
        refresh();
        commit();
        trigger.focus();
    });

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

    function optionFor(item) {
        const option = optionTemplate.content.firstElementChild.cloneNode(true);
        made += 1;
        option.id = `${listbox.id.replace(/-listbox$/, '')}-found-${made}`;
        option.setAttribute('data-select-found', '');
        fill(option, item);

        return option;
    }

    function say(text) {
        if (status) {
            status.textContent = text;
        }
    }

    // Shows the options for these answers, in their order; null brings back the ones the server drew, in its order,
    // and whatever is chosen.
    function showFound(items) {
        const wanted = new Map((items ?? []).map((item) => [item.value, item]));
        if (items) {
            drawnOrder ??= options().filter((option) => !isFound(option));
        } else {
            const drawn = options().filter((option) => !isFound(option));
            const before = (drawnOrder ?? []).filter((option) => drawn.includes(option));
            [...before, ...drawn.filter((option) => !before.includes(option))].forEach((option) => empty.before(option));
            options().filter(isFound).forEach((option) => empty.before(option));
            drawnOrder = null;
        }
        options().forEach((option) => {
            const picked = option.getAttribute('aria-selected') === 'true';
            const keep = items ? wanted.has(option.dataset.value) : !isFound(option) || picked;
            if (!keep && !picked && isFound(option)) {
                option.remove();
            } else {
                option.hidden = !keep;
            }
        });
        for (const item of wanted.values()) {
            const existing = options().find((option) => option.dataset.value === item.value);
            if (existing) {
                fill(existing, item);
            }
            const option = existing ?? optionFor(item);
            option.hidden = false;
            empty.before(option);
        }
        // A chosen option's label may have changed with the answer: the field shows the new one.
        if (items) {
            refresh();
        }
        empty.textContent = empty.dataset.noResults;
        empty.hidden = visible().length > 0;
        setActive(-1);
        move(1, -1);
        if (items) {
            say(items.length === 0 ? empty.dataset.noResults : '');
        }
    }

    async function searchApp(query) {
        const term = query.trim();
        clearTimeout(searchTimer);
        searchRequest?.abort();
        listbox.removeAttribute('aria-busy');
        if (term.length < Number(root.dataset.searchMinLength ?? 2)) {
            showFound(null);
            say('');

            return;
        }
        if (answers.has(term)) {
            showFound(answers.get(term));

            return;
        }
        searchTimer = setTimeout(async () => {
            const request = new AbortController();
            searchRequest = request;
            listbox.setAttribute('aria-busy', 'true');
            if (visible().length === 0) {
                empty.textContent = empty.dataset.searching;
                empty.hidden = false;
            }
            say(empty.dataset.searching);
            const url = new URL(searchUrl, window.location.href);
            url.searchParams.set('q', term);
            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: request.signal });
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                const body = await response.json();
                const items = (Array.isArray(body) ? body : (Array.isArray(body?.data) ? body.data : [])).map(normalise).filter((item) => item.value !== '');
                answers.set(term, items);
                // Typing may have moved on while this was on its way; only the latest answer counts.
                if (search.value.trim() === term) {
                    showFound(items);
                }
            } catch (error) {
                // Only the latest search's failure shows: an older one finishing late mustn't replace newer results.
                if (error.name !== 'AbortError' && searchRequest === request && search.value.trim() === term) {
                    options().forEach((option) => { option.hidden = true; });
                    empty.textContent = empty.dataset.searchFailed;
                    empty.hidden = false;
                    say(empty.dataset.searchFailed);
                }
            } finally {
                if (searchRequest === request) {
                    listbox.removeAttribute('aria-busy');
                }
            }
        }, 250);
    }

    function filter(query) {
        if (searchUrl) {
            searchApp(query);

            return;
        }
        const needle = query.trim().toLowerCase();
        options().forEach((option) => {
            option.hidden = needle !== '' && !option.dataset.label.toLowerCase().includes(needle);
        });
        empty.hidden = visible().length > 0;
        setActive(-1);
        move(1, -1);
    }

    function position() {
        if (closeIfOutOfView(anchor, popup)) {
            return;
        }
        const box = anchor.getBoundingClientRect();
        popup.style.width = `${box.width}px`;

        popup.style.top = `${placeBeside(popup, box, { gap: GAP, edge: VIEWPORT_EDGE, scroller: listbox })}px`;
        popup.style.left = `${box.left}px`;
    }

    // Native <select> behaviour: typing letters jumps to the first matching option.
    function typeahead(character) {
        typed += character.toLowerCase();
        clearTimeout(typedTimer);
        typedTimer = setTimeout(() => { typed = ''; }, 500);

        const list = visible();
        const match = list.findIndex((option) => isEnabled(option) && option.dataset.label.toLowerCase().startsWith(typed));
        if (match !== -1) {
            setActive(match);
        }
    }

    function handleListKeys(event) {
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                isOpen() ? move(1) : popup.showPopover();
                break;
            case 'ArrowUp':
                event.preventDefault();
                isOpen() ? move(-1) : popup.showPopover();
                break;
            case 'Home':
            case 'End':
                if (isOpen()) {
                    event.preventDefault();
                    // Start just outside the list so the first/last enabled option wins.
                    event.key === 'Home' ? move(1, -1) : move(-1, visible().length);
                }
                break;
            case 'Enter':
                // Stops the button's native popover toggle so Enter picks instead. While the list is open, Enter
                // never falls through, even with nothing highlighted: in the search box it would submit the form.
                if (isOpen()) {
                    event.preventDefault();
                    if (active !== -1) {
                        choose(visible()[active]);
                    }
                }
                break;
            case 'Tab':
                if (isOpen()) {
                    popup.hidePopover();
                }
                break;
            default:
                return false;
        }

        return true;
    }

    trigger.addEventListener('keydown', (event) => {
        if (handleListKeys(event)) {
            return;
        }
        // Space in the middle of type-ahead ("new z…") is part of the search, as in a native select; otherwise it picks.
        if (event.key === ' ' && isOpen() && typed !== '') {
            event.preventDefault();
            typeahead(' ');
        } else if (event.key === ' ' && isOpen() && active !== -1) {
            event.preventDefault();
            choose(visible()[active]);
        } else if (isOpen() && event.key.length === 1 && event.key !== ' ' && !event.ctrlKey && !event.metaKey && !event.altKey) {
            typeahead(event.key);
        }
    });

    search?.addEventListener('keydown', handleListKeys);
    search?.addEventListener('input', () => filter(search.value));

    popup.addEventListener('click', (event) => {
        const option = event.target.closest('[role="option"]');
        if (option) {
            choose(option);
        }
    });
    popup.addEventListener('mousemove', (event) => {
        const option = event.target.closest('[role="option"]:not([aria-disabled])');
        if (option && !option.hasAttribute('data-active')) {
            setActive(visible().indexOf(option));
        }
    });
    // Keep focus on the trigger/search while clicking an option.
    popup.addEventListener('mousedown', (event) => {
        if (event.target.closest('[role="option"]')) {
            event.preventDefault();
        }
    });

    popup.addEventListener('beforetoggle', (event) => {
        if (event.newState === 'open' && readonly()) {
            event.preventDefault();

            return;
        }
        if (event.newState === 'open') {
            // Hidden until positioned, otherwise it flashes at the popover default (screen centre).
            popup.style.visibility = 'hidden';
        }
    });

    popup.addEventListener('toggle', (event) => {
        const open = event.newState === 'open';
        trigger.setAttribute('aria-expanded', String(open));

        if (!open) {
            window.removeEventListener('scroll', position, true);
            window.removeEventListener('resize', position);
            window.visualViewport?.removeEventListener('resize', position);
            setActive(-1);
            if (search) {
                search.value = '';
                filter('');
                setActive(-1);
            }

            return;
        }

        position();
        popup.style.visibility = '';
        // Start on the selected option, like a native select.
        setActive(visible().findIndex((option) => option.getAttribute('aria-selected') === 'true'));
        search?.focus();
        // Capture phase so scrolling any ancestor also repositions it.
        window.addEventListener('scroll', position, true);
        window.addEventListener('resize', position);
        window.visualViewport?.addEventListener('resize', position);
    });
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
