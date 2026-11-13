// Behaviour for <x-widget.verification-code>: digits (or letters) only, up to the length. Delegated from `document`, so fields added later work without
// re-initialising. Client-side filtering is only for convenience; the Form Request must validate the same rules.
import { latinDigits, on, replaceValue } from '../field';

// --- Verification code: single field, fixed length --------------------------------

// The code each field last announced as complete: an extra key on a full field leaves the same code, and a second
// event would make an auto-submit hook post it twice.
const completed = new WeakMap();

// A whole code pasted into a field that already holds one (an old code, or the start of one) replaces it. Going in at the
// caret, the two would run together and be cut to the length: the old code would stay, and the new one be lost.
document.addEventListener('beforeinput', (event) => {
    const input = event.target;
    if (!input.matches?.('[data-code-input]') || !['insertFromPaste', 'insertFromDrop'].includes(event.inputType) || input.value === '') {
        return;
    }
    const pasted = event.data ?? event.dataTransfer?.getData('text/plain') ?? '';
    const disallowed = input.dataset.codeInput === 'alphanumeric' ? /[^a-zA-Z0-9]/g : /\D/g;
    if (latinDigits(pasted).replace(disallowed, '').length < Number(input.dataset.length)) {
        return;
    }
    event.preventDefault();
    input.value = pasted;
    input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: 'insertFromPaste' }));
});

on('input', '[data-code-input]', (event, input) => {
    const disallowed = input.dataset.codeInput === 'alphanumeric' ? /[^a-zA-Z0-9]/g : /\D/g;
    // Digits typed on any keyboard (Arabic ١٢٣, Persian ۱۲۳…) count as 0-9.
    replaceValue(input, latinDigits(input.value).replace(disallowed, '').slice(0, Number(input.dataset.length)));
    // Hook for auto-submit, as on the OTP: input.addEventListener('verification-code:complete', (e) => form.requestSubmit()).
    if (input.value.length < Number(input.dataset.length)) {
        completed.delete(input);
    } else if (completed.get(input) !== input.value) {
        completed.set(input, input.value);
        input.dispatchEvent(new CustomEvent('verification-code:complete', { bubbles: true, detail: { value: input.value } }));
    }
});
