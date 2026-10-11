// Behaviour for <x-widget.verification-code>: digits (or letters) only, up to the length. Delegated from `document`, so fields added later work without
// re-initialising. Client-side filtering is only for convenience; the Form Request must validate the same rules.
import { on, replaceValue } from '../field';

// --- Verification code: single field, fixed length --------------------------------

on('input', '[data-code-input]', (event, input) => {
    const disallowed = input.dataset.codeInput === 'alphanumeric' ? /[^a-zA-Z0-9]/g : /\D/g;
    replaceValue(input, input.value.replace(disallowed, '').slice(0, Number(input.dataset.length)));
    // Hook for auto-submit, as on the OTP: input.addEventListener('verification-code:complete', (e) => form.requestSubmit()).
    if (input.value.length === Number(input.dataset.length)) {
        input.dispatchEvent(new CustomEvent('verification-code:complete', { bubbles: true, detail: { value: input.value } }));
    }
});
