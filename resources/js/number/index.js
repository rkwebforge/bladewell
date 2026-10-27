// Behaviour for <x-widget.number>: digits only, decimals, the stepper and grouping. Delegated from `document`, so fields added later work without
// re-initialising. Client-side filtering is only for convenience; the Form Request must validate the same rules.
import { latinDigits, on, replaceValue, onLivewireMorph, typingIn } from '../field';

// --- Number: digits, one decimal point, limited decimal places --------------------

// Much of the world writes 12,50 for 12.50, and dropping the comma would turn it into 1250. Whichever
// separator comes last is the decimal point and becomes "."; any others are thousands grouping, so a
// pasted 1.234,56 or 1,234.56 both become 1234.56. One separator used more than once (1,234,567) is
// grouping too. The field keeps "." so what it submits is a number Laravel's validation accepts.
function normaliseDecimal(raw) {
    const lastDot = raw.lastIndexOf('.');
    const lastComma = raw.lastIndexOf(',');
    if (lastDot === -1 && lastComma === -1) {
        return raw;
    }
    if (lastDot !== -1 && lastComma !== -1) {
        const decimal = lastDot > lastComma ? '.' : ',';
        const grouping = decimal === '.' ? ',' : '.';

        return raw.split(grouping).join('').replace(decimal, '.');
    }
    const separator = lastDot !== -1 ? '.' : ',';

    return raw.split(separator).length > 2 ? raw.split(separator).join('') : raw.replace(separator, '.');
}

// Filters on `input` rather than `keydown` so paste, autofill and mobile keyboards are covered too.
// `negative` keeps one leading minus sign; anything else that isn't a digit or the decimal point goes.
function sanitizeNumber(typed, decimals, negative = false) {
    const raw = latinDigits(typed);
    const minus = negative && raw.trim().startsWith('-') ? '-' : '';
    if (decimals === 0) {
        // Whole numbers: a decimal part is dropped, not glued on, so a pasted 2.00 or 1,5 stays 2 or 1.
        return minus + normaliseDecimal(raw).replace(/[^0-9.]/g, '').split('.')[0];
    }

    const cleaned = normaliseDecimal(raw).replace(/[^0-9.]/g, '');
    const point = cleaned.indexOf('.');
    if (point === -1) {
        return minus + cleaned;
    }

    return minus + cleaned.slice(0, point + 1) + cleaned.slice(point + 1).replace(/\./g, '').slice(0, decimals);
}

// grouped: the field shows 1,250,000.50 the locale's way (1.250.000,50 in de, 12,50,000 in en-IN) and a
// hidden input carries 1250000.50. Typing follows the locale's separators; digits stay 0-9 so they read back.
const separatorCache = new Map();
function separators(locale) {
    if (!separatorCache.has(locale)) {
        // Same numbering as the display (0-9), so the separators it looks for are the ones it writes.
        const parts = new Intl.NumberFormat(locale, { numberingSystem: 'latn' }).formatToParts(12345.6);
        separatorCache.set(locale, {
            group: parts.find((part) => part.type === 'group')?.value ?? ',',
            decimal: parts.find((part) => part.type === 'decimal')?.value ?? '.',
        });
    }

    return separatorCache.get(locale);
}

function parseGrouped(display, input) {
    const { group, decimal } = separators(input.dataset.locale);
    // Spaces count as grouping too, since some locales group with a (narrow) no-break space people can't type.
    const plain = latinDigits(display).split(group).join('').replace(/\s/g, '').replace(decimal, '.');

    return sanitizeNumber(plain, Number(input.dataset.decimals), 'negative' in input.dataset);
}

function formatGrouped(canonical, input) {
    const { decimal } = separators(input.dataset.locale);
    const minus = canonical.startsWith('-') ? '-' : '';
    const [whole, fraction] = canonical.replace('-', '').split('.');
    const grouped = whole ? new Intl.NumberFormat(input.dataset.locale, { numberingSystem: 'latn' }).format(BigInt(whole)) : '';

    return minus + grouped + (fraction !== undefined ? decimal + fraction : '');
}

// Reformatting inserts and removes separators, so the caret is put back after the same number of digits.
function regroup(input) {
    const significant = (text) => text.replace(/[^0-9\-]/g, '').length + (text.includes(separators(input.dataset.locale).decimal) ? 1 : 0);
    const before = significant(input.value.slice(0, input.selectionStart ?? input.value.length));
    const canonical = parseGrouped(input.value, input);
    input.value = formatGrouped(canonical, input);
    let caret = 0;
    while (caret < input.value.length && significant(input.value.slice(0, caret)) < before) {
        caret++;
    }
    input.setSelectionRange(caret, caret);

    return canonical;
}

const numberValue = (input) => ('grouped' in input.dataset ? parseGrouped(input.value, input) : input.value);

// Writes a clean number to the field (and, when grouped, the hidden input) and tells listeners.
function setNumber(input, canonical, { format = true } = {}) {
    const hidden = 'grouped' in input.dataset ? input.closest('[data-field]').querySelector('[data-number-value]') : null;
    if (format) {
        input.value = hidden ? formatGrouped(canonical, input) : canonical;
    }
    const target = hidden ?? input;
    if (hidden) {
        hidden.value = canonical;
    }
    syncValueNow(input);
    target.dispatchEvent(new Event('input', { bubbles: true }));
    target.dispatchEvent(new Event('change', { bubbles: true }));
}

// A stepper field is a spinbutton; screen readers read its value from aria-valuenow.
function syncValueNow(input) {
    if (input.getAttribute('role') !== 'spinbutton') {
        return;
    }
    const value = numberValue(input);
    value === '' || Number.isNaN(Number(value)) ? input.removeAttribute('aria-valuenow') : input.setAttribute('aria-valuenow', value);
}

on('input', '[data-number-input]', (event, input) => {
    if ('grouped' in input.dataset) {
        setNumber(input, regroup(input), { format: false });

        return;
    }
    replaceValue(input, sanitizeNumber(input.value, Number(input.dataset.decimals), 'negative' in input.dataset));
    syncValueNow(input);
});

// min / max: typing isn't blocked mid-way (10 on the way to 100), so the value is pulled into range on leaving. Without
// a min, a field that takes no negatives stops at 0: the − button and ArrowDown can't go below what can be typed.
const clamp = (input, number) => {
    const min = input.dataset.min !== undefined ? Number(input.dataset.min) : ('negative' in input.dataset ? -Infinity : 0);
    const max = input.dataset.max !== undefined ? Number(input.dataset.max) : Infinity;

    return Math.min(max, Math.max(min, number));
};

on('focusout', '[data-number-input]', (event, input) => {
    const current = numberValue(input);
    if (current === '' || current === '-' || Number.isNaN(Number(current))) {
        return;
    }
    const kept = clamp(input, Number(current));
    if (kept !== Number(current)) {
        setNumber(input, String(kept));
    }
});

// Stepping: the - / + buttons, or ArrowUp / ArrowDown when the field has them. 12.50 + 1 stays 13.50.
function stepNumber(input, direction) {
    // Readonly or disabled, however it came to be: the value stays, from the arrow keys or a script.
    if (input.readOnly || input.disabled) {
        return;
    }
    const decimals = Number(input.dataset.decimals);
    const step = Number(input.dataset.step || 1);
    const current = numberValue(input);
    const base = current === '' || Number.isNaN(Number(current)) ? null : Number(current);
    const next = clamp(input, base === null ? (input.dataset.min !== undefined ? Number(input.dataset.min) : 0) : base + direction * step);
    const text = decimals > 0 && current.includes('.') ? next.toFixed(decimals) : String(Number(next.toFixed(decimals)));
    setNumber(input, text);
}

on('click', '[data-number-step]', (event, button) => {
    stepNumber(document.getElementById(button.getAttribute('aria-controls')), Number(button.dataset.numberStep));
});

on('keydown', '[data-number-input]', (event, input) => {
    if ((event.key === 'ArrowUp' || event.key === 'ArrowDown') && document.querySelector(`[data-number-step][aria-controls="${input.id}"]`)) {
        event.preventDefault();
        stepNumber(input, event.key === 'ArrowUp' ? 1 : -1);
    }
});

// Livewire: a render brings back the hidden canonical value; the display follows it, unless it's being typed in.
onLivewireMorph((scope) => scope.querySelectorAll('[data-number-input][data-grouped]').forEach((input) => {
    const hidden = input.closest('[data-field]').querySelector('[data-number-value]');
    if (hidden && !typingIn(input) && parseGrouped(input.value, input) !== hidden.value) {
        input.value = formatGrouped(hidden.value, input);
    }
}));
