// Drives <x-widget.color-picker>. The hex box, the browser's own colour input and the swatches stay in step: picking a
// swatch or a custom colour fills the box (and tells wire:model and other listeners), and typing a full #rrggbb or #rgb
// moves the others. On leaving the box, its text is tidied (#ABC → #aabbcc), or put back to the last colour if it isn't
// one, and a screen reader hears why. Colours are painted through CSS variables, set here, as no style attribute may be
// in the markup.

import { onLivewireMorph } from '../field';

const ROOT = '[data-color-picker]';

const fieldOf = (element) => element.closest('[data-field]');
const partsOf = (element) => {
    const field = fieldOf(element);

    return {
        hex: field.querySelector('[data-color-hex]'),
        native: field.querySelector('[data-color-native]'),
        preview: field.querySelector('[data-color-preview]'),
        swatches: [...field.querySelectorAll('[data-color-swatch]')],
        status: field.querySelector('[data-color-status]'),
    };
};

// #abc or #aabbcc (with or without #, any case) as #aabbcc; anything else, null.
function normalise(text) {
    const match = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(text.trim());
    if (!match) {
        return null;
    }
    const digits = match[1].toLowerCase();

    return `#${digits.length === 3 ? digits.replace(/./g, '$&$&') : digits}`;
}

// Black or white text on the colour, whichever has more contrast (WCAG relative luminance).
function ink(color) {
    const [r, g, b] = [1, 3, 5].map((at) => {
        const channel = parseInt(color.slice(at, at + 2), 16) / 255;

        return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });
    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;

    return (luminance + 0.05) / 0.05 > 1.05 / (luminance + 0.05) ? '#000000' : '#ffffff';
}

// Paints the preview and every swatch, and moves the others to the colour in the box.
function show(parts, color) {
    if (parts.native && color) {
        parts.native.value = color;
    }
    parts.swatches.forEach((swatch) => {
        swatch.checked = swatch.value === color;
        swatch.nextElementSibling.style.setProperty('--color-swatch', swatch.value);
    });
    if (parts.preview) {
        parts.preview.textContent = color ? 'Aa' : '';
        parts.preview.style.setProperty('--color-picked', color || 'transparent');
        parts.preview.style.setProperty('--color-ink', color ? ink(color) : 'inherit');
    }
}

function pick(parts, color) {
    parts.hex.value = color;
    parts.hex.dataset.lastColor = color;
    show(parts, color);
    parts.hex.dispatchEvent(new Event('input', { bubbles: true }));
    parts.hex.dispatchEvent(new Event('change', { bubbles: true }));
}

document.addEventListener('change', (event) => {
    if (event.target.matches?.('[data-color-swatch]')) {
        pick(partsOf(event.target), event.target.value);
    }
});

document.addEventListener('input', (event) => {
    const target = event.target;
    if (target.matches?.('[data-color-native]')) {
        pick(partsOf(target), target.value);
    } else if (target.matches?.('[data-color-hex]')) {
        // A full colour typed moves the others at once; a half-typed one waits. Emptied, nothing is picked, as nothing
        // is what submits.
        const color = target.value.trim() === '' ? '' : normalise(target.value);
        if (color !== null) {
            target.dataset.lastColor = color;
            show(partsOf(target), color);
        }
    }
});

document.addEventListener('focusout', (event) => {
    const hex = event.target.matches?.('[data-color-hex]') ? event.target : null;
    if (!hex || hex.value.trim() === '') {
        return;
    }
    const parts = partsOf(hex);
    const color = normalise(hex.value);
    if (color) {
        if (hex.value !== color) {
            pick(parts, color);
        }

        return;
    }
    const last = hex.dataset.lastColor ?? '';
    parts.status.textContent = `${hex.value.trim()} isn't a colour, so it's back to ${last || 'empty'}.`;
    pick(parts, last);
});

// As the page loads, after a Livewire render, and for pages swapped in by wire:navigate: paint from the box.
function paint(scope = document) {
    const roots = scope instanceof Element && scope.matches(ROOT) ? [scope] : [...(scope.querySelectorAll?.(ROOT) ?? [])];
    roots.forEach((root) => {
        const parts = partsOf(root);
        const color = normalise(parts.hex.value) ?? '';
        parts.hex.dataset.lastColor = color;
        show(parts, color);
    });
}
// A frame later: Livewire 3 sets the bound box's value just after the render it reports (checked in a browser against
// Livewire 3.8 and 4.4), and painting straight away would show the old colour.
onLivewireMorph((element) => requestAnimationFrame(() => paint(fieldOf(element) ?? element)));
document.addEventListener('livewire:navigated', () => paint());

paint();
