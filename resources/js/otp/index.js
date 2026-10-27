// Behaviour for <x-widget.otp>: one box per character, typing and pasting across them. Delegated from `document`, so fields added later work without
// re-initialising. Client-side filtering is only for convenience; the Form Request must validate the same rules.
import { latinDigits, on, onLivewireMorph, typingIn } from '../field';

// --- OTP: one box per digit --------------------------------------------------------

const otpBoxes = (root) => [...root.querySelectorAll('[data-otp-box]')];
// Digits only, or letters and digits in capitals when the field is alphanumeric (A7K2QX).
// Digits typed on any keyboard (Arabic ١٢٣, Persian ۱۲۳…) count as 0-9.
const otpClean = (root, text) => ('alphanumeric' in root.dataset ? latinDigits(text).replace(/[^a-z0-9]/gi, '').toUpperCase() : latinDigits(text).replace(/\D/g, ''));

function otpSync(root) {
    const boxes = otpBoxes(root);
    const hidden = root.querySelector('[data-otp-value]');
    const value = boxes.map((box) => box.value).join('');

    hidden.value = value;
    // Both events: tools like Alpine x-model and Livewire wire:model listen for `input`, plain forms for `change`.
    hidden.dispatchEvent(new Event('input', { bubbles: true }));
    hidden.dispatchEvent(new Event('change', { bubbles: true }));
    if (value.length === boxes.length) {
        // Hook for auto-submit: root.addEventListener('otp:complete', (e) => form.requestSubmit()). The colon name matches the
        // other widgets' events; otp-complete is the name it was first sent as, and stays.
        for (const name of ['otp:complete', 'otp-complete']) {
            root.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { value } }));
        }
    }
}

// The boxes always fill from the first, with no gaps, so the code submitted is the code shown. A whole code (pasted
// into any box) starts at the first box; anything shorter at the box it went into, or the first empty one before it.
function otpFill(root, index, digits) {
    const boxes = otpBoxes(root);
    const empty = boxes.findIndex((box) => box.value === '');
    const start = digits.length >= boxes.length ? 0 : Math.min(index, empty === -1 ? index : empty);
    [...digits].slice(0, boxes.length - start).forEach((digit, i) => {
        boxes[start + i].value = digit;
    });
    boxes[Math.min(start + digits.length, boxes.length - 1)].focus();
    otpSync(root);
}

on('input', '[data-otp-box]', (event, box) => {
    const root = box.closest('[data-otp]');
    const index = otpBoxes(root).indexOf(box);

    // Typing into a filled box: keep only the newly typed character.
    // Paste and SMS autofill arrive as other input types with the whole code in the value.
    let digits;
    if (event.inputType === 'insertText') {
        digits = otpClean(root, event.data ?? '');
        if (!digits) {
            box.value = otpClean(root, box.value).slice(0, 1);

            return;
        }
    } else {
        digits = otpClean(root, box.value);
    }

    box.value = '';
    if (!digits) {
        otpSync(root);

        return;
    }
    otpFill(root, index, digits);
});

// Takes out the character at `at`, moving the ones after it back a box, as deleting does in a text field.
function otpRemove(boxes, at) {
    for (let i = at; i < boxes.length; i++) {
        boxes[i].value = boxes[i + 1]?.value ?? '';
    }
}

on('keydown', '[data-otp-box]', (event, box) => {
    const root = box.closest('[data-otp]');
    const boxes = otpBoxes(root);
    const index = boxes.indexOf(box);

    // Readonly boxes take no Backspace or Delete, as the browser's own readonly takes no typing.
    if ((event.key === 'Backspace' || event.key === 'Delete') && box.readOnly) {
        event.preventDefault();
    } else if (event.key === 'Backspace') {
        event.preventDefault();
        if (box.value) {
            otpRemove(boxes, index);
        } else if (index > 0) {
            otpRemove(boxes, index - 1);
            boxes[index - 1].focus();
        }
        otpSync(root);
    } else if (event.key === 'Delete') {
        event.preventDefault();
        otpRemove(boxes, index);
        otpSync(root);
    } else if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault();
        boxes[index - 1].focus();
    } else if (event.key === 'ArrowRight' && index < boxes.length - 1) {
        event.preventDefault();
        boxes[index + 1].focus();
    }
});

on('paste', '[data-otp-box]', (event, box) => {
    event.preventDefault();
    if (box.readOnly) {
        return;
    }
    const root = box.closest('[data-otp]');
    const digits = otpClean(root, event.clipboardData?.getData('text') ?? '');
    if (digits) {
        otpFill(root, otpBoxes(root).indexOf(box), digits);
    }
});

on('focusin', '[data-otp-box]', (event, box) => box.select());

// Livewire: a render brings back the hidden value; the boxes follow it, unless they're being typed in.
onLivewireMorph((scope) => scope.querySelectorAll('[data-otp]').forEach((root) => {
    if (typingIn(root)) {
        return;
    }
    const value = root.querySelector('[data-otp-value]')?.value ?? '';
    otpBoxes(root).forEach((box, i) => {
        if (box.value !== (value[i] ?? '')) {
            box.value = value[i] ?? '';
        }
    });
}));
