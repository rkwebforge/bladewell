// Drives <x-widget.tags>. Enter or a comma adds what's typed; a pasted list (commas, semicolons or lines) adds each
// item; picking one of the browser's suggestions adds it at once; leaving the box adds what's left in it. Backspace in
// the empty box removes the last tag, and each tag's × removes that one. A tag already there (in any case) isn't added
// twice, and past max nothing more is. The hidden select, which is what submits and what wire:model binds, follows
// every change; a screen reader hears each one.

import { on } from '../field';

const ROOT = '[data-field]';

const partsOf = (element) => {
    const root = element.closest(ROOT);

    return {
        root,
        input: root.querySelector('[data-tags-input]'),
        list: root.querySelector('[data-tags-list]'),
        select: root.querySelector('select[data-tags-value]'),
        template: root.querySelector('template[data-tags-template]'),
        status: root.querySelector('[data-tags-status]'),
    };
};
const tagsOf = (parts) => [...parts.list.querySelectorAll('[data-tags-chip]')].map((chip) => chip.dataset.value);

function say(parts, message) {
    // Emptied first, so the same message twice is still read out.
    parts.status.textContent = '';
    requestAnimationFrame(() => {
        parts.status.textContent = message;
    });
}

// The select follows the chips, then tells wire:model and anything else listening.
function sync(parts) {
    parts.select.replaceChildren(...tagsOf(parts).map((tag) => new Option(tag, tag, true, true)));
    parts.select.dispatchEvent(new Event('input', { bubbles: true }));
    parts.select.dispatchEvent(new Event('change', { bubbles: true }));
}

function add(parts, texts) {
    const added = [];
    const max = Number(parts.input.dataset.max) || Infinity;
    for (const text of texts.map((part) => part.trim()).filter(Boolean)) {
        const tags = tagsOf(parts);
        if (tags.some((tag) => tag.toLowerCase() === text.toLowerCase())) {
            say(parts, `${text} is already added.`);
            continue;
        }
        if (tags.length >= max) {
            say(parts, `You can add up to ${max}.`);
            break;
        }
        const chip = parts.template.content.firstElementChild.cloneNode(true);
        chip.dataset.value = text;
        chip.querySelector('span').textContent = text;
        chip.querySelector('[data-tags-remove]').setAttribute('aria-label', `Remove ${text}`);
        parts.list.append(chip);
        added.push(text);
    }
    if (added.length > 0) {
        sync(parts);
        say(parts, `Added ${added.join(', ')}.`);
    }
}

function remove(parts, chip) {
    const text = chip.dataset.value;
    chip.remove();
    sync(parts);
    say(parts, `Removed ${text}.`);
}

// What's typed, split on the separators: a comma, a semicolon or a new line.
const split = (text) => text.split(/[,;\n]+/);

on('keydown', '[data-tags-input]', (event, input) => {
    // Readonly: the browser stops typing; this stops Enter, the comma and Backspace adding or removing tags.
    if (input.readOnly) {
        return;
    }
    const parts = partsOf(input);
    if ((event.key === 'Enter' || event.key === ',') && !event.isComposing) {
        // An empty box lets Enter submit the form, as in any text box.
        if (event.key === 'Enter' && input.value.trim() === '') {
            return;
        }
        event.preventDefault();
        add(parts, split(input.value));
        input.value = '';
    } else if (event.key === 'Backspace' && input.value === '') {
        const last = parts.list.querySelector('[data-tags-chip]:last-of-type');
        if (last) {
            event.preventDefault();
            remove(parts, last);
        }
    }
});

on('paste', '[data-tags-input]', (event, input) => {
    if (input.readOnly) {
        return;
    }
    const text = event.clipboardData?.getData('text') ?? '';
    // One word pastes as typing does; a list is added straight away.
    if (/[,;\n]/.test(text)) {
        event.preventDefault();
        // In place of whatever is selected, as a paste is anywhere else.
        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? input.value.length;
        add(partsOf(input), split(`${input.value.slice(0, start)}${text}${input.value.slice(end)}`));
        input.value = '';
    }
});

// The browser's suggestions: picking one sets the box without any typing (no inputType), so add it at once.
on('input', '[data-tags-input]', (event, input) => {
    if (!event.inputType && input.list && [...input.list.options].some((option) => option.value === input.value)) {
        add(partsOf(input), [input.value]);
        input.value = '';
    }
});

// Leaving the box keeps what was typed, as a tag, rather than dropping it.
document.addEventListener('focusout', (event) => {
    const input = event.target.matches?.('[data-tags-input]') ? event.target : null;
    if (input && input.value.trim() !== '') {
        add(partsOf(input), split(input.value));
        input.value = '';
    }
});

on('click', '[data-tags-remove]', (event, button) => {
    const parts = partsOf(button);
    // Readonly keeps its tags, however the field came to be readonly.
    if (parts.input.readOnly) {
        return;
    }
    remove(parts, button.closest('[data-tags-chip]'));
    parts.input.focus();
});
