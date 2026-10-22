// Drives <x-widget.phone>: a country picker plus the number, joined into one international number
// (+60123456789) in the hidden input. The list is built on first open from the compact code table on the
// field, with names from Intl.DisplayNames in the page's language and emoji flags, so it costs nothing until used.
// Pasting or autofilling a full +44… number switches the country to match.

import { closeIfOutOfView, onLivewireMorph, placeBeside } from '../field';

const VIEWPORT_EDGE = 16;
const GAP = 4;
const KEEPS_LEADING_ZERO = new Set(['IT', 'SM', 'VA']);

const flag = (iso) => String.fromCodePoint(...[...iso].map((letter) => 0x1F1E6 + letter.charCodeAt(0) - 65));
// Case and accent-insensitive: "cote" finds Côte d'Ivoire.
const fold = (text) => text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

// Set up once per element. Not a data attribute: Livewire's morph removes attributes the server didn't render.
const ready = new WeakSet();
// Each field's way to take on a value set from outside (see refreshPhones).
const followers = new WeakMap();

function initPhone(root) {
    if (ready.has(root)) {
        return;
    }
    ready.add(root);

    const trigger = root.querySelector('[data-phone-country]');
    const flagEl = root.querySelector('[data-phone-flag]');
    const dialEl = root.querySelector('[data-phone-dial]');
    const national = root.querySelector('[data-phone-national]');
    const hidden = root.querySelector('[data-phone-value]');
    const popover = root.querySelector('[data-phone-popover]');
    const search = popover.querySelector('[data-phone-search]');
    const list = popover.querySelector('[data-phone-list]');
    const empty = popover.querySelector('[data-phone-empty]');
    const anchor = trigger.parentElement;

    const locale = root.dataset.locale || document.documentElement.lang || undefined;
    const codes = Object.fromEntries(root.dataset.countries.split(',').map((pair) => pair.split(':')));
    const names = typeof Intl.DisplayNames === 'function' ? new Intl.DisplayNames([locale], { type: 'region' }) : null;
    const nameOf = (iso) => names?.of(iso) ?? iso;

    let country = hidden.dataset.country;
    let options = [];
    let active = -1;

    function international() {
        const digits = national.value.replace(/\D/g, '');
        if (!digits) {
            return '';
        }

        return `+${codes[country]}${KEEPS_LEADING_ZERO.has(country) ? digits : digits.replace(/^0/, '')}`;
    }

    function commit() {
        hidden.value = international();
        hidden.dataset.country = country;
        // Both events: x-model / wire:model listen for `input`, plain forms for `change`.
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function showCountry() {
        flagEl.textContent = flag(country);
        dialEl.textContent = `+${codes[country]}`;
        trigger.setAttribute('aria-label', `${nameOf(country)}, +${codes[country]}`);
    }

    // A full international number (pasted, or autofilled into the national field): pick its country.
    // The longest code wins; on a shared code, the current country if it fits, else the main one (listed first).
    function adopt(full) {
        const digits = full.replace(/\D/g, '');
        let best = null;
        for (const [iso, dial] of Object.entries(codes)) {
            if (digits.startsWith(dial) && (!best || dial.length > codes[best].length || (dial.length === codes[best].length && iso === country))) {
                best = iso;
            }
        }
        if (best) {
            country = best;
            national.value = digits.slice(codes[best].length);
            showCountry();
        }
    }

    national.addEventListener('input', () => {
        if (national.value.trim().startsWith('+')) {
            adopt(national.value);
        } else {
            // Keep the separators people type (spaces, dashes, brackets); drop anything else.
            const clean = national.value.replace(/[^\d\s\-().]/g, '');
            if (clean !== national.value) {
                national.value = clean;
            }
        }
        commit();
    });

    function build() {
        const collator = new Intl.Collator(locale);
        options = Object.keys(codes)
            .map((iso) => ({ iso, name: nameOf(iso) }))
            .sort((a, b) => collator.compare(a.name, b.name))
            .map(({ iso, name }) => {
                const option = document.createElement('div');
                option.id = `${list.id}-${iso}`;
                option.setAttribute('role', 'option');
                option.dataset.iso = iso;
                option.dataset.search = `${fold(name)} +${codes[iso]} ${iso.toLowerCase()}`;
                option.className = 'flex shrink-0 cursor-pointer items-center gap-3 px-4 py-2 select-none aria-selected:bg-primary/10 data-active:bg-field';
                option.innerHTML = '<span aria-hidden="true" class="text-base leading-none"></span><span class="min-w-0 flex-1 truncate"></span><span class="text-foreground/70 tabular-nums"></span>';
                option.children[0].textContent = flag(iso);
                option.children[1].textContent = name;
                option.children[2].textContent = `+${codes[iso]}`;
                list.append(option);

                return option;
            });
    }

    const visible = () => options.filter((option) => !option.hidden);

    function setActive(index) {
        const shown = visible();
        options.forEach((option) => option.removeAttribute('data-active'));
        active = shown[index] ? index : -1;
        if (active === -1) {
            search.removeAttribute('aria-activedescendant');

            return;
        }
        shown[active].setAttribute('data-active', '');
        shown[active].scrollIntoView({ block: 'nearest' });
        search.setAttribute('aria-activedescendant', shown[active].id);
    }

    function filter() {
        const query = fold(search.value.trim()).replace(/^\+/, '+');
        options.forEach((option) => {
            option.hidden = query !== '' && !option.dataset.search.includes(query);
        });
        empty.hidden = visible().length > 0;
        setActive(0);
    }

    function choose(option) {
        if (!option) {
            return;
        }
        country = option.dataset.iso;
        showCountry();
        commit();
        popover.hidePopover();
        national.focus();
    }

    search.addEventListener('input', filter);
    search.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setActive(Math.max(0, Math.min(visible().length - 1, active + (event.key === 'ArrowDown' ? 1 : -1))));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            choose(visible()[active]);
        } else if (event.key === 'Tab') {
            // Focus is leaving the list, so the list goes too, as the select does.
            popover.hidePopover();
        }
    });
    list.addEventListener('click', (event) => choose(event.target.closest('[role="option"]')));
    list.addEventListener('mousedown', (event) => event.preventDefault());

    function position() {
        if (closeIfOutOfView(anchor, popover)) {
            return;
        }
        const box = anchor.getBoundingClientRect();
        popover.style.width = `${box.width}px`;
        popover.style.top = `${placeBeside(popover, box, { gap: GAP, edge: VIEWPORT_EDGE, scroller: list })}px`;
        popover.style.left = `${box.left}px`;
    }

    popover.addEventListener('beforetoggle', (event) => {
        if (event.newState === 'open') {
            // Built on first open; again if a Livewire render emptied the list (the server sends it empty).
            if (options.length === 0 || !options[0].isConnected) {
                options = [];
                list.replaceChildren();
                build();
            }
            popover.style.visibility = 'hidden';
        }
    });

    popover.addEventListener('toggle', (event) => {
        const open = event.newState === 'open';
        trigger.setAttribute('aria-expanded', String(open));
        if (!open) {
            window.removeEventListener('scroll', position, true);
            window.removeEventListener('resize', position);
            window.visualViewport?.removeEventListener('resize', position);
            search.value = '';
            filter();

            return;
        }
        options.forEach((option) => option.setAttribute('aria-selected', String(option.dataset.iso === country)));
        position();
        popover.style.visibility = '';
        search.focus();
        setActive(visible().findIndex((option) => option.dataset.iso === country));
        window.addEventListener('scroll', position, true);
        window.addEventListener('resize', position);
        window.visualViewport?.addEventListener('resize', position);
    });

    showCountry();

    // The hidden input changed from outside (a Livewire render, the server setting or clearing the number): show it.
    followers.set(root, () => {
        // Not while it's being typed in: a render can answer an earlier keystroke, and the field holds the newer value.
        if (root.contains(document.activeElement) || (hidden.value === international() && hidden.dataset.country === country)) {
            return;
        }
        country = hidden.dataset.country || country;
        if (hidden.value) {
            adopt(hidden.value);
        } else {
            national.value = '';
        }
        showCountry();
    });
}

export function initPhones(scope = document) {
    scope.querySelectorAll('[data-phone]').forEach(initPhone);
}

export function refreshPhones(scope = document) {
    (scope.matches?.('[data-phone]') ? [scope] : [...scope.querySelectorAll('[data-phone]')]).forEach((root) => followers.get(root)?.());
}

initPhones();

// Phone fields added to the page later (a Livewire render or wire:navigate, fetched HTML) set themselves up.
new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            (node.matches('[data-phone]') ? [node] : node.querySelectorAll('[data-phone]')).forEach(initPhone);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

// Livewire: a render brings back the server's markup; each phone takes on the value it came back with.
onLivewireMorph(refreshPhones);
