// Drives <x-widget.alert> and <x-widget.alert.errors>: the dismiss button, focusing the error summary
// after a failed submit so screen readers read it out, and moving focus into a field from its summary link.

// Matches the alert's duration-200 fade.
const LEAVE_MS = 200;
const FOCUSABLE = 'input:not([type="hidden"]), select, textarea, button, [tabindex]:not([tabindex="-1"])';

document.addEventListener('click', (event) => {
    const dismiss = event.target.closest?.('[data-alert-dismiss]');
    if (dismiss) {
        const alert = dismiss.closest('[data-alert]');
        // The focused button is about to go; hand focus to whatever comes next on the page, not to <body>.
        if (alert.contains(document.activeElement)) {
            focusNextAfter(alert);
        }
        alert.dataset.leaving = '';
        setTimeout(() => putAway(alert), window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : LEAVE_MS);

        return;
    }

    const link = event.target.closest?.('[data-alert-field]');
    if (link) {
        const field = fieldFor(link);
        if (field) {
            event.preventDefault();
            // Centre it so its label, above the box, is in view too.
            field.scrollIntoView({ block: 'center' });
            field.focus({ preventScroll: true });
        }
    }
});

const TABBABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

// The next thing on the page that can really take focus: checkVisibility() also rules out the inside of a closed
// <details>, where focus() quietly does nothing. Tried in order until one takes it.
function focusNextAfter(alert) {
    const all = [...document.querySelectorAll(TABBABLE)].filter((el) => !alert.contains(el) && el.checkVisibility({ visibilityProperty: true }));
    const after = all.filter((el) => alert.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING);
    for (const candidate of [...after, ...all.reverse()]) {
        candidate.focus();
        if (document.activeElement === candidate) {
            return;
        }
    }
}

// The link points at the id a field gets from its name. Failing that (a second field of the same name got
// a -2 suffix, or the control is a hidden input behind a custom picker), find it by name instead.
function fieldFor(link) {
    const byId = document.getElementById(link.hash.slice(1));
    if (byId?.matches(FOCUSABLE)) {
        return byId;
    }

    // items.0.date → items[0][date]
    const [first, ...rest] = link.dataset.alertField.split('.');
    const name = first + rest.map((part) => `[${part}]`).join('');
    const named = document.querySelector(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`) ?? byId;

    return named?.matches(FOCUSABLE) ? named : named?.closest('[data-field]')?.querySelector(FOCUSABLE) ?? null;
}

// A flashed alert (data-announce) is read out once through a live region that was already on the page, the
// only kind screen readers announce reliably. Errors and warnings interrupt; the rest wait their turn.
// Each message is read out once; one that arrives with a Livewire render is announced then (see afterRender). Keyed on
// the text, not just the element: a render keeps the alert's element and swaps in the new flash's text.
const regions = {};
const announced = new WeakMap();
const textOf = (alert) => alert.textContent.trim().replace(/\s+/g, ' ');

function announce(alerts) {
    const fresh = alerts.filter((alert) => announced.get(alert) !== textOf(alert));
    if (fresh.length === 0) {
        return;
    }
    fresh.forEach((alert) => announced.set(alert, textOf(alert)));
    for (const role of ['status', 'alert']) {
        if (!regions[role]?.isConnected) {
            regions[role] = Object.assign(document.createElement('div'), { className: 'sr-only' });
            regions[role].setAttribute('role', role);
            document.body.append(regions[role]);
        }
    }
    // Emptied now and filled a moment later, so the same message flashed twice in a row is read twice.
    const roleOf = (alert) => (alert.dataset.announce === 'alert' ? 'alert' : 'status');
    new Set(fresh.map(roleOf)).forEach((role) => { regions[role].textContent = ''; });
    setTimeout(() => {
        for (const alert of fresh) {
            const region = regions[roleOf(alert)];
            region.textContent = [region.textContent, textOf(alert)].filter(Boolean).join(' ');
        }
    }, 150);
}

announce([...document.querySelectorAll('[data-alert][data-announce]')]);

// Module scripts run after the page is parsed, so the summary is already there. Leave autofocus alone.
const summary = document.querySelector('[data-alert-errors][data-focus-on-load]');
if (summary && (document.activeElement === document.body || document.activeElement === null)) {
    summary.focus();
}

// --- Livewire ---------------------------------------------------------------------------------------------

// Removing a dismissed alert inside a Livewire component doesn't last: the next render puts back what the server
// still renders. So there it's hidden instead, and hidden again after each render for as long as it says the same
// thing; a different message in its place is news, and shows.
const dismissedText = new WeakMap();

function putAway(alert) {
    if (!alert.closest('[wire\\:id]')) {
        alert.remove();

        return;
    }
    alert.hidden = true;
    dismissedText.set(alert, textOf(alert));
}

// A form inside a Livewire component that was just submitted, until the person types again: if the render that answers
// it brings an error summary, focus moves there, as it does after a failed submit of an ordinary form. Not while
// they're typing, so live validation can't pull focus out of a field.
let submitted = null;
document.addEventListener('submit', (event) => {
    submitted = event.target.closest?.('[wire\\:id]') ? event.target : null;
}, true);
document.addEventListener('input', () => {
    submitted = null;
}, true);

function afterRender(scope) {
    for (const alert of scope.querySelectorAll?.('[data-alert]') ?? []) {
        if (!dismissedText.has(alert)) {
            continue;
        }
        if (dismissedText.get(alert) === textOf(alert)) {
            alert.hidden = true;
        } else {
            dismissedText.delete(alert);
            alert.hidden = false;
        }
    }

    // A flash set in a Livewire action arrives with the render, after the page has loaded.
    announce([...(scope.querySelectorAll?.('[data-alert][data-announce]') ?? [])]);

    if (submitted && scope.contains?.(submitted)) {
        const form = submitted;
        submitted = null;
        const errors = form.querySelector('[data-alert-errors]') ?? form.closest('[wire\\:id]')?.querySelector('[data-alert-errors]');
        errors?.focus();
    }
}

// Not '../field''s onLivewireMorph: the alert doesn't require the field, and this is all it needs of it.
const hook = (Livewire) => Livewire.hook('morphed', ({ el }) => afterRender(el));
if (window.Livewire) {
    hook(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => hook(window.Livewire));
}
