// progress.set(target, value, { text }) updates <x-widget.progress> or <x-widget.progress.circular>:
// the fill, the visible value and what screen readers hear, together. Setting style.width by hand
// would leave aria-valuenow behind. target: the element, its id, or anything inside the component.
//
//   progress.set('upload', 60);
//   progress.set('upload', 3, { text: '3 of 5 files' });   // value out of the bar's max
//
// An indeterminate bar becomes a normal one on its first set().

function find(target) {
    const el = typeof target === 'string' ? document.getElementById(target) : target;
    const bar = el?.closest?.('[role=progressbar][data-progress]') ?? el?.querySelector?.('[role=progressbar][data-progress]') ?? null;
    if (!bar) {
        console.warn('Bladewell progress: no progress bar on this page for', target);
    }

    return bar;
}

function set(target, value, { text } = {}) {
    const bar = find(target);
    if (!bar) {
        return;
    }
    const max = Number(bar.getAttribute('aria-valuemax')) || 100;
    const clamped = Math.min(max, Math.max(0, Number(value) || 0));
    const percent = Math.round((clamped / max) * 100);
    const label = text ?? `${percent}%`;
    const state = bar.hasAttribute('data-failed') ? ', failed' : bar.getAttribute('aria-disabled') === 'true' ? ', disabled' : '';

    bar.removeAttribute('data-indeterminate');
    bar.setAttribute('aria-valuenow', String(clamped));
    bar.setAttribute('aria-valuetext', label + state);
    bar.toggleAttribute('data-complete', clamped >= max && !state);
    bar.toggleAttribute('data-empty', percent === 0);

    const fill = bar.querySelector('[data-progress-fill]');
    if (bar.dataset.progress === 'circular') {
        fill?.style.setProperty('--progress', String(percent));
        const centre = bar.querySelector('[data-progress-percent]');
        if (centre) {
            centre.textContent = `${percent}%`;
        }
    } else if (fill) {
        fill.style.width = `${percent}%`;
    }

    // The visible value sits beside, above or inside the bar, so look in the whole component.
    bar.parentElement.querySelectorAll('[data-progress-value]').forEach((el) => {
        el.textContent = label;
        el.hidden = false;
    });
}

export const progress = { set };

// Global so inline handlers and other scripts can call progress.set().
window.progress = progress;
