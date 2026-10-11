// Behaviour for <x-widget.textarea>: growing with its content. Delegated from `document`, so fields added later work without
// re-initialising. Client-side filtering is only for convenience; the Form Request must validate the same rules.
import { on } from '../field';

// --- Textarea: auto-grow -----------------------------------------------------------

// CSS field-sizing grows the textarea where the browser supports it; elsewhere fit() does, and also has to
// catch what the browser would: width changes (more wrapping), fonts arriving late, and values set by a
// script (Alpine, Livewire, "insert template"), which fire no input event. Either way, each new height is
// announced as a `textarea-resize` event (detail.height), e.g. to keep a chat scrolled to the bottom.
const nativeAutosize = CSS.supports('field-sizing', 'content');
const lastHeight = new WeakMap();
const lastWidth = new WeakMap();

function fit(textarea) {
    if (nativeAutosize) {
        return;
    }
    // Reset first so it can shrink as well as grow; min/max-height on the element still clamp the result.
    textarea.style.height = 'auto';
    textarea.style.height = `${textarea.scrollHeight}px`;
}

const textareaSizes = new ResizeObserver((entries) => {
    for (const { target } of entries) {
        if (lastWidth.get(target) !== target.offsetWidth) {
            lastWidth.set(target, target.offsetWidth);
            // Next frame: resizing the element being observed inside its own callback is a ResizeObserver loop.
            requestAnimationFrame(() => fit(target));
        }
        const height = target.offsetHeight;
        if (lastHeight.has(target) && lastHeight.get(target) !== height) {
            // textarea:resize matches the other widgets' events; textarea-resize is the name it was first sent as, and stays.
            for (const name of ['textarea:resize', 'textarea-resize']) {
                target.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { height } }));
            }
        }
        lastHeight.set(target, height);
    }
});

function watchTextarea(textarea) {
    if (textarea.dataset.autosizeReady) {
        return;
    }
    textarea.dataset.autosizeReady = 'true';
    if (!nativeAutosize) {
        // Refit whenever a script sets .value, on this element only.
        const { get, set } = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value');
        Object.defineProperty(textarea, 'value', {
            configurable: true,
            get() {
                return get.call(this);
            },
            set(value) {
                set.call(this, value);
                fit(this);
            },
        });
        fit(textarea);
    }
    textareaSizes.observe(textarea);
}

on('input', '[data-autosize]', (event, textarea) => fit(textarea));
document.querySelectorAll('[data-autosize]').forEach(watchTextarea);
// Textareas that arrive later: fetched HTML, modals, table page swaps.
new MutationObserver((mutations) => {
    for (const node of mutations.flatMap((mutation) => [...mutation.addedNodes])) {
        if (node instanceof Element) {
            (node.matches('[data-autosize]') ? [node] : node.querySelectorAll('[data-autosize]')).forEach(watchTextarea);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });
document.fonts?.addEventListener('loadingdone', () => document.querySelectorAll('[data-autosize]').forEach(fit));
