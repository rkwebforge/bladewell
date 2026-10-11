// Drives <x-widget.dropdown>. Opening, Esc and click-outside come from the native popover (popovertarget);
// this adds the menu keyboard (arrows, Home/End, type-to-jump), positioning next to the trigger, closing
// after a choice, and confirm="…" on items via modal.confirm(). A shared <x-widget.dropdown.menu> opens from
// many triggers: it follows whichever opened it and fills in that trigger's values. From JS: dropdown.open(target),
// dropdown.close(target) and dropdown.toggle(target), by the trigger's id or element; also on window.dropdown.
// Everything is delegated from `document`, so menus added to the page later work without setup.

import { modal } from '../modal';

const VIEWPORT_EDGE = 8;
const GAP = 6;
const MENU = '[data-dropdown-menu]';
const ITEM = '[role="menuitem"]:not([aria-disabled="true"]):not([hidden])';
const TRIGGER = '[data-dropdown-trigger]';
// :name, or %3Aname where a URL's query string encoded the colon. A name starts with a letter, so 09:58 is left alone.
const PLACEHOLDER = /(?::|%3A)([A-Za-z_]\w*)/gi;
// The attributes a placeholder is filled in. Never wire:* or other script: a value is data, not code.
const FILLABLE = /^(href|action|title|aria-label|data-(?!dropdown-|confirmed$).+)$/;
const URLS = new Set(['href', 'action']);

let openMenu = null;
let focusLastOnOpen = false;
let typed = '';
let typedTimer = null;

// The trigger that last asked for each menu, and the one it's open for: a shared menu has many triggers.
const askedBy = new WeakMap();
const openFor = new WeakMap();
// Each node's text and attributes as written, with placeholders, so the menu can be filled again for another row.
const written = new WeakMap();

const ownTrigger = (menu) => menu.closest('[data-dropdown]')?.querySelector(TRIGGER);
const triggerOf = (menu) => openFor.get(menu) ?? askedBy.get(menu) ?? ownTrigger(menu);
const menuOf = (trigger) => document.getElementById(trigger.getAttribute('aria-controls'));
// The row's values sit on the trigger's wrapper; null for a dropdown with its own menu.
const valuesOf = (trigger) => {
    const values = trigger?.closest('[data-dropdown]')?.dataset.dropdownValues;

    return values === undefined ? null : JSON.parse(values);
};
const itemsOf = (menu) => [...menu.querySelectorAll(ITEM)];
const isOpen = (menu) => menu.matches(':popover-open');

// Fills one text or attribute from its placeholders. What was written is kept, and written again for each row; a value
// that is no longer what was filled in last (a Livewire render put new markup there) is taken as the new original.
function fillOne(node, key, current, values, write) {
    const byKey = written.get(node) ?? new Map();
    let entry = byKey.get(key);
    if (!entry || current !== entry.filled) {
        if (!current.match(PLACEHOLDER)) {
            return;
        }
        entry = { template: current };
    }
    const encode = URLS.has(key) ? encodeURIComponent : String;
    entry.filled = entry.template.replace(PLACEHOLDER, (match, name) => (Object.hasOwn(values, name) ? encode(values[name] ?? '') : match));
    byKey.set(key, entry);
    written.set(node, byKey);
    if (entry.filled !== current) {
        write(entry.filled);
    }
}

function fill(menu, values) {
    const walker = document.createTreeWalker(menu, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (node.nodeType === Node.TEXT_NODE) {
            fillOne(node, '#text', node.data, values, (value) => { node.data = value; });
            continue;
        }
        for (const { name, value } of [...node.attributes]) {
            if (FILLABLE.test(name)) {
                fillOne(node, name, value, values, (filled) => node.setAttribute(name, filled));
            }
        }
        if (node.dataset.dropdownOnlyIf !== undefined) {
            node.hidden = !values[node.dataset.dropdownOnlyIf];
        }
    }
}

// Remembers which trigger is opening a menu. One already open for another trigger closes first, and the trigger's own
// action (popovertarget, or showPopover() from the arrow keys) opens it again for this one.
function ask(trigger) {
    const menu = menuOf(trigger);
    if (!menu) {
        console.warn(`Bladewell dropdown: no menu with id "${trigger.getAttribute('aria-controls')}" on this page.`, trigger);

        return null;
    }
    if (isOpen(menu) && openFor.get(menu) !== trigger) {
        menu.hidePopover();
    }
    askedBy.set(menu, trigger);

    return menu;
}

// Wraps around, so ArrowDown on the last item lands on the first.
function focusItem(menu, index) {
    const items = itemsOf(menu);
    if (items.length > 0) {
        items[(index + items.length) % items.length].focus();
    }
}

// Whether any of the trigger can still be seen: not scrolled out of a container that clips it (a table that scrolls
// inside itself, a modal's body) or the window, and not covered there (a table's sticky header).
function inView(trigger, menu) {
    const box = trigger.getBoundingClientRect();
    let [top, right, bottom, left] = [Math.max(box.top, 0), Math.min(box.right, window.innerWidth), Math.min(box.bottom, window.innerHeight), Math.max(box.left, 0)];
    for (let parent = trigger.parentElement; parent; parent = parent.parentElement) {
        const style = getComputedStyle(parent);
        if (/auto|scroll|hidden|clip/.test(`${style.overflowX} ${style.overflowY}`)) {
            const clip = parent.getBoundingClientRect();
            [top, right, bottom, left] = [Math.max(top, clip.top), Math.min(right, clip.right), Math.min(bottom, clip.bottom), Math.max(left, clip.left)];
        }
    }
    if (bottom - top < 1 || right - left < 1) {
        return false;
    }
    // The middle of what's left of it, under anything but the menu itself.
    const hit = document.elementsFromPoint((left + right) / 2, (top + bottom) / 2).find((element) => !menu.contains(element));

    return !hit || trigger.contains(hit);
}

function position() {
    if (!openMenu) {
        return;
    }
    const menu = openMenu;
    const trigger = triggerOf(menu);
    // The menu sits in the top layer, so nothing clips it: scrolled out of view, the trigger would leave it hanging
    // there, pointing at nothing. Close it instead (focus goes back to the trigger without scrolling to it).
    if (!inView(trigger, menu)) {
        menu.hidePopover();

        return;
    }
    // The trigger shrinks while it's pressed (active:scale-95), and the menu opens mid-press. Its layout box
    // (offsetWidth/Height around the same centre) is where it settles, so line the menu up with that instead.
    const scaled = trigger.getBoundingClientRect();
    const centreX = scaled.left + scaled.width / 2;
    const centreY = scaled.top + scaled.height / 2;
    const box = {
        left: centreX - trigger.offsetWidth / 2,
        right: centreX + trigger.offsetWidth / 2,
        top: centreY - trigger.offsetHeight / 2,
        bottom: centreY + trigger.offsetHeight / 2,
    };
    const width = menu.offsetWidth;
    const height = menu.offsetHeight;

    // align="end" lines the menu up with the trigger's end edge, which is the left one in right-to-left pages.
    const rtl = getComputedStyle(trigger).direction === 'rtl';
    const toRight = ((menu.dataset.align ?? menu.closest('[data-dropdown]').dataset.align) === 'end') !== rtl;
    const left = toRight ? box.right - width : box.left;
    menu.style.left = `${Math.min(Math.max(left, VIEWPORT_EDGE), window.innerWidth - width - VIEWPORT_EDGE)}px`;

    // Below by default; above only when it doesn't fit below and does fit above. When it fits neither, the
    // side with more room wins and the menu is held to that room (it scrolls), so no item ends up off-screen.
    menu.style.maxHeight = '';
    const roomBelow = window.innerHeight - VIEWPORT_EDGE - (box.bottom + GAP);
    const roomAbove = box.top - GAP - VIEWPORT_EDGE;
    const up = height > roomBelow && (height <= roomAbove || roomAbove > roomBelow);
    const room = up ? roomAbove : roomBelow;
    if (height > room) {
        menu.style.maxHeight = `${Math.max(room, 0)}px`;
    }
    menu.style.top = `${up ? box.top - GAP - Math.min(height, room) : box.bottom + GAP}px`;
    menu.dataset.side = up ? 'top' : 'bottom';
}

// Native <select> behaviour: typing letters jumps to the next item that starts with them.
function typeahead(menu, character) {
    typed += character.toLowerCase();
    clearTimeout(typedTimer);
    typedTimer = setTimeout(() => { typed = ''; }, 500);

    const items = itemsOf(menu);
    const start = items.indexOf(document.activeElement);
    // A repeated single letter cycles through the items that start with it.
    const offset = typed.length === 1 ? 1 : 0;
    for (let step = 0; step < items.length; step++) {
        const item = items[(start + offset + step + items.length) % items.length];
        if (item.textContent.trim().toLowerCase().startsWith(typed)) {
            item.focus();

            return;
        }
    }
}

document.addEventListener('keydown', (event) => {
    const trigger = event.target.closest?.(TRIGGER);
    if (trigger && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        event.preventDefault();
        const menu = ask(trigger);
        if (!menu) {
            return;
        }
        focusLastOnOpen = event.key === 'ArrowUp';
        isOpen(menu) ? focusItem(menu, focusLastOnOpen ? -1 : 0) : menu.showPopover();

        return;
    }

    const menu = event.target.closest?.(MENU);
    if (!menu) {
        return;
    }
    const current = itemsOf(menu).indexOf(document.activeElement);

    switch (event.key) {
        case 'ArrowDown':
            event.preventDefault();
            focusItem(menu, current + 1);
            break;
        case 'ArrowUp':
            event.preventDefault();
            focusItem(menu, current === -1 ? -1 : current - 1);
            break;
        case 'Home':
            event.preventDefault();
            focusItem(menu, 0);
            break;
        case 'End':
            event.preventDefault();
            focusItem(menu, -1);
            break;
        case 'Tab':
            // Back to the trigger first, so Tab carries on from there rather than from the top of the page.
            menu.hidePopover();
            triggerOf(menu).focus();
            break;
        case ' ':
            // Space activates a button natively; on a link it would scroll the page instead.
            if (event.target.matches('a[role="menuitem"]')) {
                event.preventDefault();
                event.target.click();
            }
            break;
        default:
            if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                typeahead(menu, event.key);
            }
    }
});

// Capture phase, before popovertarget acts on the click.
document.addEventListener('click', (event) => {
    const trigger = event.target.closest?.(TRIGGER);
    if (trigger) {
        ask(trigger);
    }
}, true);

// Capture phase, so a disabled item or one still waiting on confirm stops the click before the item's own listeners
// see it: wire:click, Alpine's @click or yours would otherwise act on it, since preventDefault() only stops a link
// or a form.
document.addEventListener('click', (event) => {
    const item = event.target.closest?.(`${MENU} [role="menuitem"]`);
    if (!item) {
        return;
    }
    // A Livewire render while the menu was open may have put the placeholders back; act on this row's values.
    const owner = item.closest(MENU);
    const values = owner.hasAttribute('data-dropdown-shared') ? valuesOf(triggerOf(owner)) : null;
    if (values) {
        fill(owner, values);
    }
    const disabled = item.getAttribute('aria-disabled') === 'true';
    const unconfirmed = item.dataset.confirm && !item.hasAttribute('data-confirmed');
    if (!disabled && !unconfirmed) {
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    if (disabled) {
        return;
    }

    const menu = item.closest(MENU);
    if (isOpen(menu)) {
        menu.hidePopover();
    }
    // Ask first, then replay the click, which follows the link, submits the form or runs the action as it would have.
    modal.confirm({
        title: item.dataset.confirm,
        message: item.dataset.confirmMessage ?? '',
        confirm: item.dataset.confirmLabel || item.textContent.trim(),
        cancel: item.dataset.confirmCancel || undefined,
        danger: item.hasAttribute('data-danger'),
    }).then((confirmed) => {
        if (confirmed) {
            item.setAttribute('data-confirmed', '');
            item.click();
            item.removeAttribute('data-confirmed');
        }
    });
}, true);

// A choice closes the menu.
document.addEventListener('click', (event) => {
    const menu = event.target.closest?.(`${MENU} [role="menuitem"]`)?.closest(MENU);
    if (menu && isOpen(menu)) {
        menu.hidePopover();
    }
});

// Popover toggle events don't bubble, so listen in the capture phase.
document.addEventListener('beforetoggle', (event) => {
    const menu = event.target;
    if (!menu.matches?.(MENU)) {
        return;
    }
    // Here rather than on toggle, which comes later: by then another trigger may have asked for the menu.
    if (event.newState === 'closed') {
        openFor.get(menu)?.setAttribute('aria-expanded', 'false');
        openFor.delete(menu);

        return;
    }
    const trigger = askedBy.get(menu) ?? ownTrigger(menu);
    openFor.set(menu, trigger);
    trigger.setAttribute('aria-expanded', 'true');
    const values = menu.hasAttribute('data-dropdown-shared') ? valuesOf(trigger) : null;
    if (values) {
        fill(menu, values);
        menu.setAttribute('aria-labelledby', trigger.id);
    }
    // Hidden until positioned, otherwise it flashes at the popover default (screen centre).
    menu.style.visibility = 'hidden';
}, true);

document.addEventListener('toggle', (event) => {
    const menu = event.target;
    if (!menu.matches?.(MENU)) {
        return;
    }
    const trigger = triggerOf(menu);

    if (event.newState === 'open') {
        openMenu = menu;
        position();
        menu.style.visibility = '';
        focusItem(menu, focusLastOnOpen ? -1 : 0);
        focusLastOnOpen = false;
        // Capture phase so scrolling any ancestor (a table, a modal) also moves it.
        window.addEventListener('scroll', position, true);
        window.addEventListener('resize', position);

        return;
    }

    if (openMenu === menu) {
        openMenu = null;
        window.removeEventListener('scroll', position, true);
        window.removeEventListener('resize', position);
    }
    // Esc or choosing an item leaves focus nowhere; put it back on the trigger. A click elsewhere keeps its own focus.
    if (document.activeElement === document.body || menu.contains(document.activeElement)) {
        trigger.focus({ preventScroll: true });
    }
}, true);

// --- From your own script ------------------------------------------------------------------------------------------

// A dropdown by its trigger's id, or the trigger, the wrapper or anything in its own menu. A shared menu opens for a
// row, so give the row's trigger.
const triggerFor = (target) => {
    const element = typeof target === 'string' ? document.getElementById(target) : target;
    const trigger = element?.matches?.(TRIGGER) ? element : element?.closest?.('[data-dropdown]')?.querySelector(TRIGGER);
    if (!trigger) {
        console.warn('Bladewell dropdown: no dropdown trigger on this page for', target);
    }

    return trigger ?? null;
};

function open(target) {
    const trigger = triggerFor(target);
    const menu = trigger && ask(trigger);
    if (menu && !isOpen(menu)) {
        menu.showPopover();
    }
}

function close(target) {
    const trigger = triggerFor(target);
    const menu = trigger && menuOf(trigger);
    if (menu && isOpen(menu) && openFor.get(menu) === trigger) {
        menu.hidePopover();
    }
}

function toggle(target) {
    const trigger = triggerFor(target);
    if (!trigger) {
        return;
    }
    const menu = menuOf(trigger);
    menu && isOpen(menu) && openFor.get(menu) === trigger ? menu.hidePopover() : open(trigger);
}

export const dropdown = { open, close, toggle };

// Global, as modal and toast are, so a script on the page can call it without an import.
window.dropdown = dropdown;

// From a Livewire component ($this->dispatch('dropdown-open', id: 'order-1042-actions')) or any script.
window.addEventListener('dropdown-open', (event) => open(event.detail?.id));
window.addEventListener('dropdown-close', (event) => close(event.detail?.id));
