// Behaviour of the frame every field sits in, <x-widget.field>: the inputs, the date pickers and the file upload.
// Delegated from `document`, so fields added to the page later (fetched HTML, modals) work without re-initialising.
// Also the few helpers every field's own script uses, exported so each one doesn't carry a copy.

// Runs handler(event, element) for events on, or inside, an element matching selector.
export const on = (type, selector, handler) => {
    document.addEventListener(type, (event) => {
        const target = event.target instanceof Element ? event.target.closest(selector) : null;
        if (target) {
            handler(event, target);
        }
    });
};

// --- Readonly checkboxes, switches and radios ----------------------------------------

// HTML's readonly only works on text. A click on a readonly box, switch or radio (or its label, or Space, which clicks
// it) is cancelled, which puts it back as it was; in a readonly radio group the arrow keys don't pick another. Capture
// phase, so wire:model and your own listeners never see a change.
document.addEventListener('click', (event) => {
    if (event.target.matches?.('input[data-readonly]')) {
        event.preventDefault();
    }
}, true);
document.addEventListener('keydown', (event) => {
    if (event.target.matches?.('input[type="radio"][data-readonly]') && event.key.startsWith('Arrow')) {
        event.preventDefault();
    }
}, true);

// --- Clear a server-side error as soon as the user edits the field --------------------

// The error was about the old value; once it changes, showing it is just noise. The server
// re-validates on submit and puts it back if it still applies. The frame (widget/field)
// styles off data-invalid, so removing it drops the red look and brings any hint back.
function clearInvalid(event) {
    const field = event.target.closest?.('[data-field][data-invalid]');
    // Inside a popover is looking, not editing: a picker's month and year menus, a select's search box.
    if (!field || event.target.closest('[popover]')) {
        return;
    }
    field.removeAttribute('data-invalid');
    field.querySelector('[data-field-error]')?.remove();
    // The hint shows again, so it's announced again: it stood aside for the error (FormField::aria).
    const hint = field.querySelector('[data-field-info]')?.id;
    field.querySelectorAll('[aria-invalid="true"]').forEach((control) => {
        control.removeAttribute('aria-invalid');
        // Drop the (now removed) error from what screen readers announce, keep the rest.
        const describedBy = new Set([hint, ...(control.getAttribute('aria-describedby') ?? '').split(' ')].filter((id) => id && document.getElementById(id)));
        describedBy.size ? control.setAttribute('aria-describedby', [...describedBy].join(' ')) : control.removeAttribute('aria-describedby');
    });
}

document.addEventListener('input', clearInvalid);
document.addEventListener('change', clearInvalid);

// --- Required select, date pickers and time picker -------------------------------------------------

// Their value lives in hidden inputs, which the browser never validates, so an empty required one would
// submit. Stop the submit here instead, with the message in the frame's own error style, and focus the first
// one: its accessible description then reads the message out. Capture phase, so it runs before the submit
// guard (widget/button) and any other submit handler sees defaultPrevented. The server still validates.
const CHOICE_TRIGGER = '[data-select-trigger], [data-datepicker-trigger], [data-date-range-trigger], [data-time-picker-trigger]';
const ALERT_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="text-error mt-0.5 size-3.5 shrink-0"><circle cx="12" cy="12" r="8.75"/><path d="M12 7.75v5"/><path d="M12 16.25h.01"/></svg>';

// Chosen: a multiple select has at least one value input; otherwise every named value input (both ends of a
// range) is filled in. A widget with no name submits nothing, so there's nothing to require.
function hasChoice(field) {
    const list = field.querySelector('[data-select-values]');
    if (list) {
        return !list.dataset.name || list.querySelector('input') !== null;
    }

    return [...field.querySelectorAll('input[type="hidden"][name]')].every((input) => input.value !== '');
}

function showRequired(field) {
    const trigger = field.querySelector(CHOICE_TRIGGER);
    if (field.querySelector('[data-field-error]') || !trigger) {
        return trigger;
    }
    const error = document.createElement('div');
    error.dataset.fieldError = '';
    error.className = 'mt-1 flex items-start gap-1';
    error.innerHTML = ALERT_ICON;
    const message = Object.assign(document.createElement('p'), { id: `${trigger.id}-error`, className: 'text-error break-words', textContent: field.dataset.requiredMessage });
    error.append(message);
    // Where the server would have put it: under the box, above any hint (which the invalid state hides).
    const info = field.querySelector('[data-field-info]');
    info ? info.parentElement.before(error) : field.append(error);

    field.setAttribute('data-invalid', '');
    trigger.setAttribute('aria-invalid', 'true');
    // The error instead of the hint, which the invalid state hides, as the server does it (FormField::aria).
    const describedBy = new Set((trigger.getAttribute('aria-describedby') ?? '').split(' ').filter((id) => id && id !== info?.id));
    trigger.setAttribute('aria-describedby', [message.id, ...describedBy].join(' '));

    return trigger;
}

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.noValidate || event.submitter?.formNoValidate) {
        return;
    }
    // Not inside a disabled fieldset (a hidden show-if): like the browser's own checks, those don't count.
    const missing = [...form.querySelectorAll('[data-field][data-required-message]')].filter((field) => !field.closest('fieldset:disabled') && !hasChoice(field));
    if (missing.length === 0) {
        return;
    }
    event.preventDefault();
    const triggers = missing.map(showRequired);
    triggers[0]?.focus();
}, true);

// --- Character counter (counter + maxlength) -----------------------------------------

function updateCounter(control) {
    const counter = control.closest('[data-field]').querySelector('[data-field-counter]');
    if (counter) {
        // UTF-16 units, as maxlength counts them, so the counter reaches the limit when typing stops, emoji and all.
        counter.textContent = `${control.value.length} / ${counter.dataset.max}`;
    }
}

on('input', '[data-field] input, [data-field] textarea', (event, control) => updateCounter(control));

// Swaps in a cleaned value while keeping the caret the same distance from the end,
// so stripping a character mid-string doesn't throw the cursor to the end.
export function replaceValue(input, clean) {
    if (input.value === clean) {
        return;
    }
    const fromEnd = input.value.length - (input.selectionEnd ?? input.value.length);
    input.value = clean;
    const caret = Math.max(0, clean.length - fromEnd);
    input.setSelectionRange(caret, caret);
}

// --- Livewire renders ----------------------------------------------------------------------------------------

// A render morphs each field back to the server's markup. The value itself comes back right (FormField::old draws it
// from the bound property), but what a script works out from it (a counter, the password checklist, a grouped
// number's display, the OTP boxes) only followed it as it was typed: each field's script brings its own back in step
// through onLivewireMorph. Also run for a value the server set or cleared. Not the field being typed in: a render can
// answer an earlier keystroke, and what's in the field is newer than what came back (see typingIn).
export const typingIn = (element) => element.contains(document.activeElement);

// Calls refresh(element) after every Livewire morph. Does nothing on a page without Livewire.
export function onLivewireMorph(refresh) {
    const hook = (Livewire) => Livewire.hook('morphed', ({ el }) => refresh(el));
    if (window.Livewire) {
        hook(window.Livewire);
    } else {
        document.addEventListener('livewire:init', () => hook(window.Livewire));
    }
}

function refreshCounters(scope) {
    scope.querySelectorAll('[data-field-counter]').forEach((counter) => {
        const control = counter.closest('[data-field]').querySelector('input:not([type="hidden"]), textarea');
        if (control) {
            updateCounter(control);
        }
    });
}

onLivewireMorph(refreshCounters);

// --- Popovers and the on-screen keyboard -------------------------------------------------------------------

// The part of the window that can be seen, in the coordinates getBoundingClientRect() and position: fixed use. An
// on-screen keyboard covers the bottom of the window without changing innerHeight (iOS, and Android Chrome since 108),
// so a popover placed by innerHeight opens under it; visualViewport knows where the keyboard starts. The keyboard
// opening or closing resizes only that, so a popover listens there as well as on the window.
export function visibleArea() {
    const view = window.visualViewport;

    return view ? { top: view.offsetTop, bottom: view.offsetTop + view.height } : { top: 0, bottom: window.innerHeight };
}

// Where a popover goes beside its field (`box`, the field's rectangle): below when it fits there, otherwise on the side
// with more room. When even that side is too short, as with the keyboard up on a phone, `scroller` (the part of the
// popover that scrolls) is cut down to fit, rather than the popover running under the keyboard or off the screen.
// Returns the popover's top.
export function placeBeside(popover, box, { gap, edge, scroller = null }) {
    if (scroller) {
        scroller.style.maxHeight = '';
    }
    const view = visibleArea();
    const roomBelow = view.bottom - edge - (box.bottom + gap);
    const roomAbove = box.top - gap - (view.top + edge);
    let height = popover.offsetHeight;
    const below = height <= roomBelow || roomBelow >= roomAbove;
    const room = below ? roomBelow : roomAbove;
    if (scroller && height > room) {
        // Never shorter than about three rows, so it stays usable.
        scroller.style.maxHeight = `${Math.max(scroller.offsetHeight - (height - room), 120)}px`;
        height = popover.offsetHeight;
    }

    return below ? box.bottom + gap : box.top - gap - height;
}

// --- Popovers that lose their field ------------------------------------------------------------------------

// Whether any of an element can still be seen: not scrolled out of a container that clips it (a table that scrolls
// inside itself, a modal's body) or the window, and not covered there (a table's sticky header). `popover` is left out
// of what counts as covering it.
export function inView(element, popover) {
    const box = element.getBoundingClientRect();
    let [top, right, bottom, left] = [Math.max(box.top, 0), Math.min(box.right, window.innerWidth), Math.min(box.bottom, window.innerHeight), Math.max(box.left, 0)];
    for (let parent = element.parentElement; parent; parent = parent.parentElement) {
        const style = getComputedStyle(parent);
        if (/auto|scroll|hidden|clip/.test(`${style.overflowX} ${style.overflowY}`)) {
            const clip = parent.getBoundingClientRect();
            [top, right, bottom, left] = [Math.max(top, clip.top), Math.min(right, clip.right), Math.min(bottom, clip.bottom), Math.max(left, clip.left)];
        }
    }
    if (bottom - top < 1 || right - left < 1) {
        return false;
    }
    const hit = document.elementsFromPoint((left + right) / 2, (top + bottom) / 2).find((node) => !popover?.contains(node));

    return !hit || element.contains(hit);
}

// A popover sits in the top layer, so nothing clips it: when the field it belongs to is scrolled out of view, it would
// be left hanging, pointing at nothing. Each picker calls this as it repositions on scroll; it closes the popover then,
// and puts focus back on the field (without scrolling to it) if it was inside. True when it closed it.
export function closeIfOutOfView(field, popover) {
    if (!popover.matches(':popover-open') || inView(field, popover)) {
        return false;
    }
    const hadFocus = popover.contains(document.activeElement);
    popover.hidePopover();
    if (hadFocus) {
        field.focus({ preventScroll: true });
    }

    return true;
}
