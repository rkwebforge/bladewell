// Drives <x-widget.slider>. One thumb is a native range input, whose keyboard and screen reader support stay; this
// redraws the filled track, the value under the label and what a screen reader hears, in the page's number format. Two
// thumbs (range) are role="slider" elements this moves itself, as the ARIA two-thumb slider does: Right and Up step up,
// Left and Down step down (Left and Right follow the reading direction), Page Up and Down move ten steps, Home and End
// go as far as they can. Each stops at the other. Dragging a thumb moves it; pressing the track moves the nearer one
// there. A hidden input per thumb carries its value, for the form and wire:model.

import { onLivewireMorph } from '../field';

const ROOT = '[data-slider]';
const PAGE = 10;

const fieldOf = (root) => root.closest('[data-field]');
const number = (value) => Number(value);
const bounds = (root) => ({ min: number(root.dataset.min), max: number(root.dataset.max), step: number(root.dataset.step) || 1 });

// "$1,250" in en, "1.250 €" in de: the number in the page's format, with the widget's prefix and suffix.
function show(root, value) {
    const formatted = new Intl.NumberFormat(root.dataset.locale || document.documentElement.lang || undefined, { maximumFractionDigits: 6 }).format(number(value));

    return `${root.dataset.prefix ?? ''}${formatted}${root.dataset.suffix ?? ''}`;
}

const percent = (root, value) => {
    const { min, max } = bounds(root);

    return ((number(value) - min) / (max - min)) * 100;
};

// The values as they stand: the native input's, or each thumb's hidden input.
function valuesOf(root) {
    if (root.hasAttribute('data-range')) {
        return [number(root.querySelector('[data-slider-value="from"]').value), number(root.querySelector('[data-slider-value="to"]').value)];
    }

    return [number(root.querySelector('[data-slider-to]').value)];
}

function draw(root) {
    const values = valuesOf(root);
    const range = values.length === 2;
    // Through the CSSOM, which a Content Security Policy allows, unlike a style attribute in the markup.
    root.style.setProperty('--slider-from', range ? String(percent(root, values[0])) : '0');
    root.style.setProperty('--slider-to', String(percent(root, values.at(-1))));
    if (range) {
        const { min, max } = bounds(root);
        const [from, to] = ['from', 'to'].map((end) => root.querySelector(`[data-slider-thumb="${end}"]`));
        from.setAttribute('aria-valuenow', String(values[0]));
        from.setAttribute('aria-valuemin', String(min));
        from.setAttribute('aria-valuemax', String(values[1]));
        to.setAttribute('aria-valuenow', String(values[1]));
        to.setAttribute('aria-valuemin', String(values[0]));
        to.setAttribute('aria-valuemax', String(max));
        from.setAttribute('aria-valuetext', show(root, values[0]));
        to.setAttribute('aria-valuetext', show(root, values[1]));
    } else {
        root.querySelector('[data-slider-to]').setAttribute('aria-valuetext', show(root, values[0]));
    }
    const output = fieldOf(root)?.querySelector('[data-slider-output]');
    if (output) {
        output.textContent = range ? `${show(root, values[0])} – ${show(root, values[1])}` : show(root, values[0]);
    }
    // The ends of the scale, in the same format.
    fieldOf(root)?.querySelectorAll('[data-slider-end]').forEach((end) => {
        end.textContent = show(root, end.dataset.sliderEnd);
    });
}

// --- One thumb: the native input ----------------------------------------------------------------------------------------

document.addEventListener('input', (event) => {
    if (event.target.matches?.('input[type="range"][data-slider-to]')) {
        draw(event.target.closest(ROOT));
    }
});

// --- Two thumbs ---------------------------------------------------------------------------------------------------------

// Readonly too: neither moves, by key or by pointer.
const disabled = (thumb) => thumb.getAttribute('aria-disabled') === 'true' || thumb.getAttribute('aria-readonly') === 'true';

// Onto a step from min, and between the scale's end and the other thumb.
function place(root, end, value, { commit = false } = {}) {
    const { min, max, step } = bounds(root);
    const [from, to] = valuesOf(root);
    const low = end === 'from' ? min : from;
    const high = end === 'from' ? to : max;
    const snapped = Math.min(high, Math.max(low, min + Math.round((value - min) / step) * step));
    // Floating steps (0.1) would otherwise leave 0.30000000000000004.
    const tidy = Number(snapped.toFixed(10));
    const input = root.querySelector(`[data-slider-value="${end}"]`);
    root.querySelectorAll('[data-slider-thumb]').forEach((thumb) => thumb.toggleAttribute('data-front', thumb.dataset.sliderThumb === end));
    if (number(input.value) !== tidy) {
        input.value = String(tidy);
        draw(root);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (commit) {
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

// One thumb is the browser's own range, which has no readonly: its keys are stopped here, and pointers by its
// pointer-events-none.
document.addEventListener('keydown', (event) => {
    if (event.target.matches?.('input[type="range"][data-readonly]') && /^(Arrow|Page|Home|End)/.test(event.key)) {
        event.preventDefault();
    }
});

document.addEventListener('keydown', (event) => {
    const thumb = event.target.matches?.('[data-slider-thumb]') ? event.target : null;
    if (!thumb || disabled(thumb)) {
        return;
    }
    const root = thumb.closest(ROOT);
    const { min, max, step } = bounds(root);
    const end = thumb.dataset.sliderThumb;
    const value = valuesOf(root)[end === 'from' ? 0 : 1];
    const rtl = getComputedStyle(root).direction === 'rtl';
    const moves = {
        ArrowRight: rtl ? -step : step,
        ArrowLeft: rtl ? step : -step,
        ArrowUp: step,
        ArrowDown: -step,
        PageUp: step * PAGE,
        PageDown: -step * PAGE,
    };
    let target = null;
    if (event.key in moves) {
        target = value + moves[event.key];
    } else if (event.key === 'Home') {
        target = min;
    } else if (event.key === 'End') {
        target = max;
    }
    if (target === null) {
        return;
    }
    event.preventDefault();
    place(root, end, target, { commit: true });
});

// The value under a pointer, from where it is along the track (whose ends are the thumbs' centres at min and max).
function valueAt(root, clientX) {
    const { min, max } = bounds(root);
    const box = root.getBoundingClientRect();
    const knob = root.querySelector('[data-slider-thumb]').offsetWidth;
    let ratio = (clientX - box.left - knob / 2) / (box.width - knob);
    if (getComputedStyle(root).direction === 'rtl') {
        ratio = 1 - ratio;
    }

    return min + Math.min(1, Math.max(0, ratio)) * (max - min);
}

let dragging = null;

document.addEventListener('pointerdown', (event) => {
    const root = event.target.closest?.(`${ROOT}[data-range]`);
    if (!root || event.button !== 0) {
        return;
    }
    let thumb = event.target.closest('[data-slider-thumb]');
    if (!thumb) {
        // The track: the nearer thumb comes to the pointer, and carries on from there.
        const value = valueAt(root, event.clientX);
        const [from, to] = valuesOf(root);
        const end = Math.abs(value - from) <= Math.abs(value - to) && !(value > to) ? 'from' : 'to';
        thumb = root.querySelector(`[data-slider-thumb="${end}"]`);
        if (disabled(thumb)) {
            return;
        }
        place(root, end, value);
    }
    if (disabled(thumb)) {
        return;
    }
    event.preventDefault();
    thumb.focus();
    // Capture keeps the moves coming when the pointer leaves the thumb; the drag goes on without it if it can't be had.
    try {
        thumb.setPointerCapture(event.pointerId);
    } catch {
        // A pointer the browser doesn't know as active (a synthetic one): moves still reach the document.
    }
    dragging = { root, thumb, pointerId: event.pointerId };
});

document.addEventListener('pointermove', (event) => {
    if (dragging?.pointerId === event.pointerId) {
        place(dragging.root, dragging.thumb.dataset.sliderThumb, valueAt(dragging.root, event.clientX));
    }
});

const stop = (event) => {
    if (dragging?.pointerId === event.pointerId) {
        const { root, thumb } = dragging;
        dragging = null;
        place(root, thumb.dataset.sliderThumb, valuesOf(root)[thumb.dataset.sliderThumb === 'from' ? 0 : 1], { commit: true });
    }
};
document.addEventListener('pointerup', stop);
document.addEventListener('pointercancel', stop);

// --- Redrawing --------------------------------------------------------------------------------------------------------

// A Livewire render (or a form reset) may change the values without an input event: draw them again.
const drawAll = (scope = document) => {
    if (scope instanceof Element && scope.matches(ROOT)) {
        draw(scope);
    }
    scope.querySelectorAll?.(ROOT).forEach(draw);
};
// A frame later: Livewire 3 sets a bound input's value just after the render it reports (checked in a browser against
// Livewire 3.8 and 4.4), and drawing straight away would show the old one.
onLivewireMorph((element) => requestAnimationFrame(() => drawAll(element)));
document.addEventListener('reset', (event) => requestAnimationFrame(() => drawAll(event.target)));

drawAll();
