// Double-submit protection for <x-widget.button type="submit">: once its form submits, the button shows
// its loading state, so a slow request can't be sent twice. Opt out with :submit-guard="false".

// A form that downloads a file (or otherwise never leaves the page) would keep its button busy for good;
// after this long with the page still showing, the guard lets go.
const RELEASE_AFTER_MS = 15_000;

function release(button) {
    delete button.dataset.guarded;
    button.removeAttribute('aria-busy');
    button.removeAttribute('aria-disabled');
    if (button.dataset.idleLabel !== undefined) {
        button.setAttribute('aria-label', button.dataset.idleLabel);
        delete button.dataset.idleLabel;
    }
}

// aria-disabled rather than disabled: a disabled button loses keyboard focus to <body>, and screen readers then hear
// nothing. The submit listener below blocks a second submit instead.
function busy(button) {
    button.dataset.guarded = '';
    button.setAttribute('aria-busy', 'true');
    button.setAttribute('aria-disabled', 'true');
    // An icon-only button's aria-label hides its "Loading" text, so say it in the label for now.
    const label = button.getAttribute('aria-label');
    const loading = button.querySelector('.sr-only')?.textContent.trim();
    if (label && loading) {
        button.dataset.idleLabel = label;
        button.setAttribute('aria-label', `${label}, ${loading.toLowerCase()}`);
    }
}

// What had focus as the submit began, before anything else could disable the button (see wire:submit below).
let focusedAtSubmit = null;

// While a form's button is busy, a second submit (Enter in a field, a double click) goes nowhere.
document.addEventListener('submit', (event) => {
    focusedAtSubmit = document.activeElement;
    if (event.target.querySelector?.('[data-button][data-guarded]')) {
        event.preventDefault();
    }
}, true);

// wire:submit: Livewire cancels the browser's submit, sends the form itself and disables the button until the answer
// comes back, which turns it grey and drops focus to <body>. Show the loading state instead, and when Livewire enables
// the button again, let go and give focus back if the button had it.
function guardLivewire(button) {
    const hadFocus = focusedAtSubmit === button;
    busy(button);
    const watch = new MutationObserver(() => {
        if (button.disabled) {
            return;
        }
        watch.disconnect();
        release(button);
        if (hadFocus && (document.activeElement === document.body || document.activeElement === null)) {
            button.focus();
        }
    });
    watch.observe(button, { attributes: true, attributeFilter: ['disabled'] });
}

const livewireSubmit = (form) => Boolean(form.closest('[wire\\:id]')) && [...form.attributes].some((attribute) => attribute.name.startsWith('wire:submit'));

document.addEventListener('submit', (event) => {
    const button = event.submitter;
    const form = event.target;
    if (!button?.matches('[data-submit-guard]')) {
        return;
    }
    // A form that opens somewhere else (target="_blank") leaves this page as it is, so there's nothing to guard.
    const target = button.getAttribute('formtarget') ?? form.getAttribute('target');
    if (target && target !== '_self') {
        return;
    }

    // Decided on the next tick, after every other submit handler has run: one registered later (or on window)
    // may still cancel the submit, and then the button must stay as it was.
    setTimeout(() => {
        if (!button.isConnected) {
            return;
        }
        if (event.defaultPrevented) {
            // Only while Livewire holds the button disabled: that's what says when its answer is in.
            if (livewireSubmit(form) && button.disabled) {
                guardLivewire(button);
            }

            return;
        }
        busy(button);
        setTimeout(() => {
            if (button.isConnected && 'guarded' in button.dataset && document.visibilityState === 'visible') {
                release(button);
            }
        }, RELEASE_AFTER_MS);
    });
});

// Coming back with the Back button can restore the page as it was left, busy button included.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }
    document.querySelectorAll('[data-button][data-guarded]').forEach(release);
});

// --- confirm="…" ----------------------------------------------------------------------------------------------------

// The styled dialog when the app has the modal widget (window.modal), otherwise the browser's own: the button doesn't
// require the modal, so an app without it still gets the question.
// The button's visible words: not its spinner, or the "Loading" kept in it for screen readers.
function wordsOf(button) {
    const copy = button.cloneNode(true);
    copy.querySelectorAll('.sr-only, [aria-hidden="true"]').forEach((hidden) => hidden.remove());

    return copy.textContent.replace(/\s+/g, ' ').trim();
}

function ask(button) {
    const { confirm: question, confirmMessage: message, confirmLabel, confirmCancel } = button.dataset;
    if (window.modal?.confirm) {
        return window.modal.confirm({
            title: question,
            message: message ?? '',
            confirm: confirmLabel || button.getAttribute('aria-label') || wordsOf(button),
            cancel: confirmCancel || undefined,
            danger: button.hasAttribute('data-danger'),
        });
    }

    return Promise.resolve(window.confirm(message ? `${question}\n\n${message}` : question));
}

// Capture phase, so the click goes nowhere until it's answered: no submit, no link, and wire:click or your own listener
// never sees it. Once confirmed, the click is replayed and does what it would have.
document.addEventListener('click', (event) => {
    const button = event.target.closest?.('[data-button][data-confirm]');
    if (!button || button.hasAttribute('data-confirmed') || button.getAttribute('aria-disabled') === 'true') {
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    ask(button).then((confirmed) => {
        if (confirmed) {
            button.setAttribute('data-confirmed', '');
            button.click();
            button.removeAttribute('data-confirmed');
        }
    });
}, true);
