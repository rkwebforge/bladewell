// Drives <x-widget.modal>. Open: <button data-modal-open="id"> or modal.open('id').
// Close: any [data-modal-close] inside, Esc, or the backdrop when close-on-backdrop is set, which also lets a bottom
// sheet be swiped down. A disable-close modal only
// closes through modal.close(id, { force: true }); closing it any other way (even dialog.close()) opens it again.
// Initial focus: put data-autofocus (not autofocus) on an element inside the dialog.
// Events on the dialog: modal:before-open, modal:opened, modal:closed (detail.returnValue).
// Window events modal-open and modal-close with detail.id open and close one, e.g. from Livewire:
// $this->dispatch('modal-close', id: 'edit'). modal-close closes a disable-close modal too: the app asked.
// modal.confirm({ title, message, confirm, cancel, danger }) asks a yes/no question without any markup.

const MODAL = 'dialog[data-modal]';

function resolve(target) {
    const dialog = typeof target === 'string' ? document.getElementById(target) : target;
    if (!dialog) {
        console.warn('Bladewell modal: no modal on this page for', target);
    }

    return dialog;
}

function open(target) {
    const dialog = resolve(target);
    if (!dialog || dialog.open) {
        return;
    }
    // Lets content be built just in time (e.g. the pagination page list) before focus is placed.
    dialog.dispatchEvent(new CustomEvent('modal:before-open'));
    swipeToClose(dialog);
    dialog.showModal();
    // data-autofocus rather than the native attribute: the browser also runs page-load autofocus on
    // elements inside closed dialogs, and when the URL has a #fragment (e.g. pagination links) it
    // blocks that and logs a console warning. [autofocus] is still honoured for existing markup.
    // Without a target, focus the dialog itself; the browser would pick the close button.
    const initial = dialog.querySelector('[data-autofocus], [autofocus]');
    (initial ?? dialog).focus();
    dialog.dispatchEvent(new CustomEvent('modal:opened', { bubbles: true }));
}

// `force` closes even a disable-close modal, for when the app itself decides it's done.
function close(target, { force = false } = {}) {
    const dialog = resolve(target);
    if (!dialog?.open || (dialog.hasAttribute('data-disable-close') && !force)) {
        return;
    }
    // Marks a close the app asked for, which a locked modal lets through (see the close listener).
    dialog.dataset.closing = '';
    dialog.close();
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest?.('[data-modal-open]');
    if (opener) {
        open(opener.dataset.modalOpen);

        return;
    }

    const closer = event.target.closest?.('[data-modal-close]')?.closest(MODAL);
    if (closer) {
        close(closer);
    }
});

// Backdrop close only when the press also started on the backdrop: a text selection
// dragged out of the panel ends with a click on the dialog and must not close it.
let pressedOnBackdrop = false;
document.addEventListener('pointerdown', (event) => {
    pressedOnBackdrop = event.target.matches?.(`${MODAL}[data-close-on-backdrop]`) ?? false;
});
document.addEventListener('click', (event) => {
    if (pressedOnBackdrop && event.target.matches?.(`${MODAL}[data-close-on-backdrop]`)) {
        close(event.target);
    }
    pressedOnBackdrop = false;
});

// A locked modal (disable-close) has to survive Esc. Cancelling the dialog's `cancel` event isn't enough on its
// own: Chromium only honours that after the user has interacted with the page (its CloseWatcher rules). So Esc
// is stopped before it becomes a close request, `cancel` is still cancelled, and as a last resort (the Android
// back gesture) a locked modal that closes without the app asking is opened again.
const topModal = () => [...document.querySelectorAll(`${MODAL}:modal`)].at(-1);

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && topModal()?.hasAttribute('data-disable-close')) {
        event.preventDefault();
    }
}, true);

// `cancel` (Esc) and `close` don't bubble, so listen in the capture phase.
document.addEventListener('cancel', (event) => {
    if (event.target.matches?.(`${MODAL}[data-disable-close]`)) {
        event.preventDefault();
    }
}, true);

document.addEventListener('close', (event) => {
    if (!event.target.matches?.(MODAL)) {
        return;
    }
    const byApp = 'closing' in event.target.dataset;
    delete event.target.dataset.closing;
    if (event.target.hasAttribute('data-disable-close') && !byApp) {
        event.target.showModal();

        return;
    }
    if (event.target.matches('[data-reset-on-close]')) {
        event.target.querySelectorAll('form').forEach((form) => {
            form.reset();
            // reset() fires no events, so wire:model, x-model and the fields' own scripts (counters) would keep the old
            // values; tell them, as if the person had cleared each field.
            for (const control of form.elements) {
                control.dispatchEvent(new Event('input', { bubbles: true }));
                control.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }
    // e.g. refresh a list after a form modal closes. The native close event doesn't bubble; this does.
    event.target.dispatchEvent(new CustomEvent('modal:closed', { bubbles: true, detail: { returnValue: event.target.returnValue } }));
}, true);

// --- Swipe down to close -------------------------------------------------------------------

// A sheet (data-modal-sheet: the panel at the bottom of the screen, or the dialog itself where that is what slides)
// follows a finger dragged down, then closes past a third of its height or on a flick, and springs back otherwise.
// Only where the backdrop may close it too: a sheet that can't be dismissed stays that way.
const SWIPE_DISTANCE = 0.3;
// Pixels per millisecond: a flick closes it however short.
const SWIPE_SPEED = 0.5;
const swipeBound = new WeakSet();

const sheetOf = (dialog) => (dialog.matches('[data-modal-sheet]') ? dialog : dialog.querySelector(':scope > [data-modal-sheet]'));

// Anything between the finger and the sheet that is scrolled down takes the drag first, so scrolling back up never
// closes the sheet.
function scrolledDown(target, sheet) {
    for (let node = target; node instanceof Element; node = node.parentElement) {
        if (node.scrollTop > 0) {
            return true;
        }
        if (node === sheet) {
            return false;
        }
    }

    return false;
}

// Touch events, not pointer events: the browser cancels a pointer as soon as it starts scrolling, and Safari has no
// touch-action that leaves only upward scrolling to it. Bound on each sheet as it first opens rather than on the
// document, because a listener that can cancel touchmove makes the browser wait for it before scrolling anything.
function swipeToClose(dialog) {
    if (swipeBound.has(dialog) || !sheetOf(dialog)) {
        return;
    }
    swipeBound.add(dialog);
    let drag = null;

    dialog.addEventListener('touchstart', (event) => {
        drag = null;
        const sheet = sheetOf(dialog);
        if (
            event.touches.length !== 1
            || !sheet?.contains(event.target)
            || !dialog.matches('[data-close-on-backdrop]:not([data-disable-close])')
            // mobile="sheet" is a centred dialog from the sm breakpoint up, where there is nothing to drag down.
            || Math.abs(sheet.getBoundingClientRect().bottom - window.innerHeight) > 2
            || event.target.closest('input, textarea, select, [contenteditable]')
            || scrolledDown(event.target, sheet)
        ) {
            return;
        }
        const { clientX: x, clientY: y } = event.touches[0];
        drag = { sheet, x, y, dy: 0, dragging: false, moves: [{ y, t: event.timeStamp }] };
    }, { passive: true });

    dialog.addEventListener('touchmove', (event) => {
        if (!drag) {
            return;
        }
        const { clientX: x, clientY: y } = event.touches[0];
        if (!drag.dragging) {
            const across = Math.abs(x - drag.x);
            const down = y - drag.y;
            if (across === 0 && down === 0) {
                return;
            }
            // Decided on the first movement, the only one iOS lets a script cancel: up, sideways, or a scroll the
            // browser has already taken is left alone.
            if (down <= 0 || across > down || !event.cancelable) {
                drag = null;

                return;
            }
            drag.dragging = true;
            // Through the CSSOM, which a Content Security Policy allows, unlike a style attribute in the markup.
            drag.sheet.style.transition = 'none';
        }
        event.preventDefault();
        drag.dy = Math.max(0, y - drag.y);
        drag.sheet.style.translate = `0 ${drag.dy}px`;
        drag.moves = [drag.moves.at(-1), { y, t: event.timeStamp }];
    }, { passive: false });

    const release = (event) => {
        if (!drag?.dragging) {
            drag = null;

            return;
        }
        const { sheet, dy, moves: [from, to] } = drag;
        drag = null;
        // No click on whatever the finger was lifted from.
        if (event.cancelable) {
            event.preventDefault();
        }
        // A finger that stopped before lifting isn't a flick, however fast it moved before.
        const speed = event.timeStamp - to.t > 100 ? 0 : (to.y - from.y) / Math.max(to.t - from.t, 1);
        const shut = event.type === 'touchend' && (dy > sheet.offsetHeight * SWIPE_DISTANCE || (dy > 0 && speed > SWIPE_SPEED));
        // Handing back to the classes slides it from where the finger left it: out if closing, back up if not.
        sheet.style.transition = '';
        sheet.style.translate = '';
        if (shut) {
            close(dialog);
        }
    };
    dialog.addEventListener('touchend', release);
    dialog.addEventListener('touchcancel', release);
}

// From a Livewire component ($this->dispatch('modal-open', id: 'edit')) or any script. Livewire puts the named
// arguments in detail; a plain CustomEvent can do the same.
window.addEventListener('modal-open', (event) => open(event.detail?.id));
window.addEventListener('modal-close', (event) => close(event.detail?.id, { force: true }));

// --- modal.confirm -------------------------------------------------------------------------

// Same look as <x-widget.modal size="sm"> and <x-widget.button> (neutral, primary, danger), built here so a
// confirmation needs no markup. Text goes in with textContent, so a title or message can't inject HTML.
const CONFIRM_DIALOG = 'group fixed inset-0 m-0 h-dvh max-h-none w-full max-w-none flex-col overflow-y-auto overscroll-contain bg-transparent px-2.5 py-8 opacity-0 outline-none transition-all transition-discrete duration-300 open:flex open:opacity-100 starting:open:opacity-0 motion-reduce:transition-none backdrop:bg-foreground/40 backdrop:opacity-0 backdrop:transition-opacity backdrop:duration-300 open:backdrop:opacity-100 starting:open:backdrop:opacity-0 motion-reduce:backdrop:transition-none';
const CONFIRM_PANEL = 'bg-surface text-foreground relative m-auto w-full max-w-sm scale-95 rounded-3xl p-6 shadow-xl transition-transform duration-300 group-open:scale-100 starting:group-open:scale-95 motion-reduce:transition-none';
const BUTTON = 'relative inline-flex min-w-fit items-center justify-center gap-1.5 rounded-xl border border-transparent px-4 py-3 text-sm font-medium whitespace-nowrap outline-none transition-all focus-visible:ring-primary focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface active:scale-95';
const BUTTON_LOOK = {
    neutral: 'bg-field text-foreground hover:bg-line',
    primary: 'bg-primary-fill text-on-primary hover:bg-primary-hover',
    danger: 'bg-error-fill text-white hover:brightness-90',
};
let confirmCount = 0;

// Resolves true when confirmed; false on Cancel or Esc. An alertdialog that starts on Cancel, so Enter
// can't confirm a destructive action by accident, and a backdrop click doesn't count as either answer.
function confirm({ title = 'Are you sure?', message = '', confirm: confirmText = 'Confirm', cancel: cancelText = 'Cancel', danger = false } = {}) {
    return new Promise((answer) => {
        const id = `modal-confirm-${++confirmCount}`;
        const make = (tag, className, text) => Object.assign(document.createElement(tag), { className, textContent: text ?? '' });

        const dialog = make('dialog', CONFIRM_DIALOG);
        dialog.id = id;
        dialog.dataset.modal = '';
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-labelledby', `${id}-title`);

        const panel = make('div', CONFIRM_PANEL);
        const heading = make('h2', 'text-lg font-semibold', title);
        heading.id = `${id}-title`;
        panel.append(heading);
        if (message) {
            const text = make('p', 'text-foreground/75 mt-3 text-sm', message);
            text.id = `${id}-message`;
            dialog.setAttribute('aria-describedby', text.id);
            panel.append(text);
        }

        const footer = make('div', 'mt-6 flex flex-wrap items-center justify-end gap-3');
        const cancel = make('button', `${BUTTON} ${BUTTON_LOOK.neutral}`, cancelText);
        const ok = make('button', `${BUTTON} ${danger ? BUTTON_LOOK.danger : BUTTON_LOOK.primary}`, confirmText);
        cancel.type = ok.type = 'button';
        cancel.dataset.autofocus = '';
        cancel.addEventListener('click', () => dialog.close('cancel'));
        ok.addEventListener('click', () => dialog.close('confirm'));
        footer.append(cancel, ok);
        panel.append(footer);
        dialog.append(panel);

        dialog.addEventListener('close', () => {
            answer(dialog.returnValue === 'confirm');
            // Leave time for the closing transition before removing it.
            setTimeout(() => dialog.remove(), 350);
        }, { once: true });

        document.body.append(dialog);
        open(dialog);
    });
}

export const modal = { open, close, confirm };

// Global so inline handlers and other scripts can call modal.open('id').
window.modal = modal;

// e.g. :open="$errors->any()" to reopen a form modal after failed validation.
document.querySelectorAll(`${MODAL}[data-open-on-load]`).forEach((dialog) => open(dialog));
