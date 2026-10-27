// Drives <x-widget.time-picker>. The server paints the time and the choices; this opens the popover, moves through the
// times with the keyboard, writes the choice into the hidden input (HH:MM, 24-hour) and shows it the locale's way.
// Slots are plain radio buttons and need nothing from here. Delegated from `document`, so pickers added later (a
// Livewire render, fetched HTML) work without setting up.
import { closeIfOutOfView, onLivewireMorph, placeBeside, typingIn } from '../field';

const ROOT = '[data-time-picker]';
const POPOVER = '[data-time-picker-popover]';
const VIEWPORT_EDGE = 16;
const GAP = 4;

const pad = (n) => String(n).padStart(2, '0');
const toMinutes = (time) => {
    const [hour, minute] = time.split(':').map(Number);

    return hour * 60 + minute;
};
const fromMinutes = (minutes) => `${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`;

// The same pattern and words the server used (TimeOfDay::format), filled in the same way, so a time picked here reads
// exactly as the server writes it, whatever this browser's own Intl data would say.
export function formatTime(time, pattern, periods) {
    const [hour, minute] = time.split(':').map(Number);

    return pattern.replace(/'((?:[^']|'')*)'|h{1,2}|H{1,2}|K{1,2}|k{1,2}|m{1,2}|a+/g, (token, quoted) => {
        const width = token.length;
        switch (token[0]) {
            case "'":
                // Quoted text is literal; '' is a quote mark, inside quotes or on its own.
                return quoted === '' ? "'" : quoted.replaceAll("''", "'");
            case 'h':
                return String(hour % 12 || 12).padStart(width, '0');
            case 'H':
                return String(hour).padStart(width, '0');
            case 'K':
                return String(hour % 12).padStart(width, '0');
            case 'k':
                return String(hour || 24).padStart(width, '0');
            case 'm':
                return String(minute).padStart(width, '0');
            default:
                return periods[hour < 12 ? 0 : 1];
        }
    });
}

function parts(root) {
    return {
        input: root.querySelector('[data-time-picker-input]'),
        trigger: root.querySelector('[data-time-picker-trigger]'),
        display: root.querySelector('[data-time-picker-display]'),
        popover: root.querySelector(POPOVER),
        pattern: root.dataset.pattern,
        periods: JSON.parse(root.dataset.periods ?? '["AM","PM"]'),
        min: toMinutes(root.dataset.min ?? '00:00'),
        max: toMinutes(root.dataset.max ?? '23:59'),
        step: Number(root.dataset.step) || 1,
        hour12: root.dataset.hour12 === 'true',
    };
}

// Writes the time (or '' for none) where the form and wire:model read it, and shows it.
function setValue(root, time) {
    const { input, display, pattern, periods } = parts(root);
    if (display) {
        display.textContent = time ? formatTime(time, pattern, periods) : display.dataset.placeholder;
        display.toggleAttribute('data-empty', !time);
    }
    root.querySelectorAll('[role="listbox"]:not([data-time-picker-column]) [role="option"]').forEach((option) => {
        option.setAttribute('aria-selected', String(option.dataset.value === time));
    });
    if (input.value === time) {
        return;
    }
    input.value = time;
    // Both: Alpine x-model and Livewire wire:model listen for `input`, plain forms for `change`.
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

// --- The popover (list and columns) ---------------------------------------------------------------------

// Under the field, lined up with it and at least as wide; above it when there's no room below.
function position(root) {
    const { trigger, popover } = parts(root);
    if (closeIfOutOfView(trigger, popover)) {
        return;
    }
    const box = trigger.getBoundingClientRect();
    if (root.dataset.timePicker === 'list') {
        popover.style.minWidth = `${box.width}px`;
    }
    const width = popover.offsetWidth;
    const rtl = getComputedStyle(root).direction === 'rtl';
    const left = rtl ? box.right - width : box.left;
    popover.style.left = `${Math.min(Math.max(left, VIEWPORT_EDGE), window.innerWidth - width - VIEWPORT_EDGE)}px`;
    // The list scrolls, so it can be cut down to fit; the columns keep their size.
    popover.style.top = `${placeBeside(popover, box, { gap: GAP, edge: VIEWPORT_EDGE, scroller: root.dataset.timePicker === 'list' ? popover : null })}px`;
}

let openRoot = null;
const reposition = () => openRoot && position(openRoot);

document.addEventListener('beforetoggle', (event) => {
    if (event.target.matches?.(POPOVER) && event.newState === 'open') {
        // Hidden until it's placed, or it flashes in the middle of the screen first.
        event.target.style.visibility = 'hidden';
    }
}, true);

document.addEventListener('toggle', (event) => {
    const popover = event.target;
    if (!popover.matches?.(POPOVER)) {
        return;
    }
    const root = popover.closest(ROOT);
    const { trigger } = parts(root);
    const open = event.newState === 'open';
    trigger.setAttribute('aria-expanded', String(open));
    if (open) {
        openRoot = root;
        if (root.dataset.timePicker === 'columns') {
            refreshColumns(root);
        }
        position(root);
        popover.style.visibility = '';
        focusChosen(root);
        window.addEventListener('scroll', reposition, true);
        window.addEventListener('resize', reposition);
        window.visualViewport?.addEventListener('resize', reposition);

        return;
    }
    if (openRoot === root) {
        openRoot = null;
        window.removeEventListener('scroll', reposition, true);
        window.removeEventListener('resize', reposition);
        window.visualViewport?.removeEventListener('resize', reposition);
    }
    // Esc, a choice or Done leave focus nowhere; put it back on the field. A click elsewhere keeps its own focus.
    if (document.activeElement === document.body || popover.contains(document.activeElement)) {
        trigger.focus({ preventScroll: true });
    }
}, true);

const enabled = (options) => options.filter((option) => option.getAttribute('aria-disabled') !== 'true');

// Opens on the chosen time, or the first one that can be picked, scrolled into the middle of the list.
function focusChosen(root) {
    const lists = [...root.querySelectorAll(`${POPOVER} [role="listbox"], ${POPOVER}[role="listbox"]`)];
    for (const list of lists) {
        const options = [...list.querySelectorAll('[role="option"]')];
        const chosen = options.find((option) => option.getAttribute('aria-selected') === 'true') ?? enabled(options)[0];
        chosen?.scrollIntoView({ block: 'center' });
    }
    const first = lists[0];
    const target = first?.querySelector('[role="option"][aria-selected="true"]') ?? enabled([...(first?.querySelectorAll('[role="option"]') ?? [])])[0];
    target?.focus({ preventScroll: true });
}

function moveIn(list, from, by) {
    const options = enabled([...list.querySelectorAll('[role="option"]')]);
    if (options.length === 0) {
        return null;
    }
    const index = options.indexOf(from);
    const next = by === Infinity ? options.at(-1) : by === -Infinity ? options[0] : options[Math.min(options.length - 1, Math.max(0, (index === -1 ? -1 : index) + by))];
    next.focus();
    next.scrollIntoView({ block: 'nearest' });

    return next;
}

// --- List: a menu of times ------------------------------------------------------------------------------

let typed = '';
let typedTimer = null;

function chooseFromList(root, option) {
    if (option.getAttribute('aria-disabled') === 'true') {
        return;
    }
    setValue(root, option.dataset.value);
    parts(root).popover.hidePopover();
}

// Like a native select: typing "9" jumps to the first 9-o'clock time, "93" to 9:30. Digits only, matched against the
// hour and minute the option shows and its 24-hour time, so on a 12-hour clock both "2" and "14" reach 2:00 PM. The hour
// is matched whole first, so "1" is 1:00, not 12:00; failing that, any time whose digits start with what was typed.
function jump(list, key) {
    typed += key;
    clearTimeout(typedTimer);
    typedTimer = setTimeout(() => { typed = ''; }, 700);
    const forms = (option) => [option.textContent, option.dataset.value].map((text) => text.match(/\d+/g) ?? []);
    const whole = ([hour = '', minute = '']) => [hour, hour.padStart(2, '0')].some((h) => (typed.length <= h.length ? typed === h : typed.startsWith(h) && minute.startsWith(typed.slice(h.length))));
    const starts = (digits) => digits.join('').startsWith(typed) || digits.join('').startsWith(`0${typed}`);
    const options = enabled([...list.querySelectorAll('[role="option"]')]);
    const match = options.find((option) => forms(option).some(whole)) ?? options.find((option) => forms(option).some(starts));
    match?.focus();
    match?.scrollIntoView({ block: 'nearest' });
}

// --- Columns: hour, minute and AM/PM ------------------------------------------------------------------

// The time the columns show, filling in what's missing from the first time that can be picked.
function columnsTime(root) {
    const { input, min } = parts(root);

    return input.value || fromMinutes(min);
}

function columnValue(root, part, value) {
    const { hour12, min, max, step } = parts(root);
    let [hour, minute] = columnsTime(root).split(':').map(Number);
    if (part === 'hour') {
        hour = hour12 ? (Number(value) % 12) + (hour >= 12 ? 12 : 0) : Number(value);
    } else if (part === 'minute') {
        minute = Number(value);
    } else {
        hour = (hour % 12) + (Number(value) === 1 ? 12 : 0);
    }
    // A new hour keeps its minute where it can; otherwise the nearest one that's in range.
    let total = hour * 60 + minute;
    if (total < min || total > max) {
        const candidates = [];
        for (let m = 0; m < 60; m += step) {
            const t = hour * 60 + m;
            if (t >= min && t <= max) {
                candidates.push(t);
            }
        }
        total = candidates.sort((a, b) => Math.abs(a - total) - Math.abs(b - total))[0] ?? Math.min(max, Math.max(min, total));
    }

    return fromMinutes(total);
}

// Marks the chosen hour, minute and period, and greys out what would leave the range given the rest of the time.
function refreshColumns(root) {
    const { input, hour12, min, max, step } = parts(root);
    const time = input.value;
    const [hour, minute] = (time || columnsTime(root)).split(':').map(Number);
    const inRange = (h, m) => h * 60 + m >= min && h * 60 + m <= max;
    const anyMinute = (h) => {
        for (let m = 0; m < 60; m += step) {
            if (inRange(h, m)) {
                return true;
            }
        }

        return false;
    };
    root.querySelectorAll('[data-time-picker-column]').forEach((column) => {
        const part = column.dataset.timePickerColumn;
        column.querySelectorAll('[role="option"]').forEach((option) => {
            const value = Number(option.dataset.value);
            let chosen;
            let allowed;
            if (part === 'hour') {
                const h = hour12 ? (value % 12) + (hour >= 12 ? 12 : 0) : value;
                chosen = Boolean(time) && h === hour;
                allowed = anyMinute(h);
            } else if (part === 'minute') {
                chosen = Boolean(time) && value === minute;
                allowed = inRange(hour, value);
            } else {
                chosen = Boolean(time) && value === (hour >= 12 ? 1 : 0);
                allowed = Array.from({ length: 12 }, (_, i) => i + value * 12).some(anyMinute);
            }
            option.setAttribute('aria-selected', String(chosen));
            allowed ? option.removeAttribute('aria-disabled') : option.setAttribute('aria-disabled', 'true');
        });
    });
}

function chooseInColumn(root, option) {
    if (option.getAttribute('aria-disabled') === 'true') {
        return;
    }
    const column = option.closest('[data-time-picker-column]');
    setValue(root, columnValue(root, column.dataset.timePickerColumn, option.dataset.value));
    refreshColumns(root);
}

// --- Segmented: typed into the field ------------------------------------------------------------------

// Per picker, what's been typed: { hour, minute, pm }, each null until set. The hour as shown (1-12 on a 12-hour clock).
const segmentState = new WeakMap();
// Per segment, the first digit while a second may follow.
const pendingDigit = new WeakMap();

function readSegments(root) {
    const state = { hour: null, minute: null, pm: null };
    root.querySelectorAll('[data-time-picker-segment]').forEach((segment) => {
        const now = segment.getAttribute('aria-valuenow');
        const value = now === null ? null : Number(now);
        const part = segment.dataset.timePickerSegment;
        if (part === 'period') {
            state.pm = value === null ? null : value === 1;
        } else {
            state[part] = value;
        }
    });
    segmentState.set(root, state);

    return state;
}

const stateOf = (root) => segmentState.get(root) ?? readSegments(root);

function renderSegments(root) {
    const state = stateOf(root);
    const { periods } = parts(root);
    root.querySelectorAll('[data-time-picker-segment]').forEach((segment) => {
        const part = segment.dataset.timePickerSegment;
        const value = part === 'period' ? (state.pm === null ? null : Number(state.pm)) : state[part];
        const text = value === null ? segment.dataset.placeholder : part === 'period' ? periods[value] : pad(value);
        segment.textContent = text;
        segment.toggleAttribute('data-empty', value === null);
        if (value === null) {
            segment.removeAttribute('aria-valuenow');
            segment.setAttribute('aria-valuetext', 'Empty');
        } else {
            segment.setAttribute('aria-valuenow', String(value));
            segment.setAttribute('aria-valuetext', part === 'period' ? periods[value] : String(value));
        }
    });
}

// The time typed so far, in minutes, and that time kept within min and max; null while any part is missing.
function typedTime(root) {
    const state = stateOf(root);
    const { hour12, min, max } = parts(root);
    if (state.hour === null || state.minute === null || (hour12 && state.pm === null)) {
        return null;
    }
    const hour = hour12 ? (state.hour % 12) + (state.pm ? 12 : 0) : state.hour;
    const typed = hour * 60 + state.minute;

    return { typed, kept: Math.min(max, Math.max(min, typed)) };
}

// A whole time once every part is in, kept within min and max; '' while any part is missing. What was typed stays as it
// is while the field has focus, even out of range: changing 11:00 AM to 5:00 PM goes through 5:00 AM on the way.
function commitSegments(root) {
    const time = typedTime(root);
    setValue(root, time ? fromMinutes(time.kept) : '');
}

// Once focus leaves the field, it shows what's submitted, not what was typed.
function settleSegments(root) {
    const time = typedTime(root);
    if (!time || time.kept === time.typed) {
        return;
    }
    const { hour12 } = parts(root);
    const hour = Math.floor(time.kept / 60);
    Object.assign(stateOf(root), { hour: hour12 ? hour % 12 || 12 : hour, minute: time.kept % 60, pm: hour >= 12 });
    renderSegments(root);
}

function segmentKey(root, segment, event) {
    const state = stateOf(root);
    const { step, periods } = parts(root);
    const part = segment.dataset.timePickerSegment;
    const low = Number(segment.getAttribute('aria-valuemin'));
    const high = Number(segment.getAttribute('aria-valuemax'));
    const segments = [...root.querySelectorAll('[data-time-picker-segment]')];
    const index = segments.indexOf(segment);
    const rtl = getComputedStyle(root).direction === 'rtl';
    const wrap = (n) => ((n - low + (high - low + 1)) % (high - low + 1)) + low;
    const key = event.key;
    let changed = false;

    if (key === 'ArrowUp' || key === 'ArrowDown') {
        const by = key === 'ArrowUp' ? 1 : -1;
        if (part === 'period') {
            state.pm = state.pm === null ? by === 1 : !state.pm;
        } else if (part === 'minute') {
            // To the next minute on the step (from :00), wrapping round the hour: :07 goes up to :15, not :22.
            const last = Math.floor(59 / step) * step;
            if (state.minute === null) {
                state.minute = by === 1 ? 0 : last;
            } else if (by === 1) {
                const next = (Math.floor(state.minute / step) + 1) * step;
                state.minute = next > last ? 0 : next;
            } else {
                const previous = (Math.ceil(state.minute / step) - 1) * step;
                state.minute = previous < 0 ? last : previous;
            }
        } else {
            state.hour = state.hour === null ? (by === 1 ? low : high) : wrap(state.hour + by);
        }
        changed = true;
    } else if (key === 'ArrowLeft' || key === 'ArrowRight') {
        const by = (key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1);
        segments[index + by]?.focus();
    } else if (key === 'Backspace' || key === 'Delete') {
        part === 'period' ? (state.pm = null) : (state[part] = null);
        pendingDigit.delete(segment);
        changed = true;
    } else if (/^\d$/.test(key) && part !== 'period') {
        const digit = Number(key);
        const first = pendingDigit.get(segment);
        let value = first === undefined ? digit : first * 10 + digit;
        // "1" then "5" on a 1-12 hour can't be 15: the 5 starts again.
        if (value > high) {
            value = digit;
        }
        pendingDigit.delete(segment);
        // Another digit may follow ("1" could become "12", "0" become "09"); wait for it, then move on.
        if (first === undefined && value * 10 <= high) {
            pendingDigit.set(segment, value);
        }
        // A lone 0 on a 1-12 hour isn't an hour yet.
        state[part] = value < low ? null : value;
        if (!pendingDigit.has(segment)) {
            segments[index + 1]?.focus();
        }
        changed = true;
    } else if (part === 'period' && key.length === 1) {
        // The first letter of either word: a or p, v or n (vorm., nachm.).
        const letter = key.toLowerCase();
        const pick = periods.findIndex((word) => word.toLowerCase().startsWith(letter));
        if (pick !== -1) {
            state.pm = pick === 1;
            changed = true;
        }
    } else {
        return;
    }
    if (key !== 'Tab') {
        event.preventDefault();
    }
    if (changed) {
        renderSegments(root);
        commitSegments(root);
    }
}

// --- Events ------------------------------------------------------------------------------------------

document.addEventListener('click', (event) => {
    const option = event.target.closest?.(`${ROOT} ${POPOVER} [role="option"]`);
    if (option) {
        const root = option.closest(ROOT);
        option.closest('[data-time-picker-column]') ? chooseInColumn(root, option) : chooseFromList(root, option);

        return;
    }
    const done = event.target.closest?.('[data-time-picker-done]');
    if (done) {
        done.closest(POPOVER).hidePopover();
    }
});

document.addEventListener('keydown', (event) => {
    // Ctrl, Cmd or Alt with a key is the browser's (select all, copy, print): Ctrl+A isn't the a of AM.
    if (event.ctrlKey || event.metaKey || event.altKey) {
        return;
    }
    const segment = event.target.closest?.('[data-time-picker-segment]');
    // A readonly time keeps its keys to itself: Tab still moves on, nothing else changes it.
    if (segment && segment.getAttribute('aria-disabled') !== 'true' && segment.getAttribute('aria-readonly') !== 'true') {
        segmentKey(segment.closest(ROOT), segment, event);

        return;
    }
    const option = event.target.closest?.(`${ROOT} ${POPOVER} [role="option"]`);
    if (!option) {
        return;
    }
    const root = option.closest(ROOT);
    const column = option.closest('[data-time-picker-column]');
    const list = option.closest('[role="listbox"]');
    const rtl = getComputedStyle(root).direction === 'rtl';
    const moves = { ArrowDown: 1, ArrowUp: -1, Home: -Infinity, End: Infinity, PageDown: 5, PageUp: -5 };

    if (event.key in moves) {
        event.preventDefault();
        const next = moveIn(list, option, moves[event.key]);
        // In the columns, moving is choosing, like turning a wheel.
        if (column && next) {
            chooseInColumn(root, next);
            next.focus();
        }
    } else if (column && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
        event.preventDefault();
        const columns = [...root.querySelectorAll('[data-time-picker-column]')];
        const by = (event.key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1);
        const to = columns[columns.indexOf(column) + by];
        (to?.querySelector('[role="option"][aria-selected="true"]') ?? enabled([...(to?.querySelectorAll('[role="option"]') ?? [])])[0])?.focus();
    } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        column ? parts(root).popover.hidePopover() : chooseFromList(root, option);
    } else if (event.key === 'Tab' && !column) {
        // Back to the field first, so Tab carries on from there.
        parts(root).popover.hidePopover();
    } else if (!column && /^\d$/.test(event.key)) {
        jump(list, event.key);
    }
});

// Roving tabindex: the option with focus is its list's one Tab stop, so Tab leaves the list and comes back to it.
document.addEventListener('focusin', (event) => {
    const option = event.target.closest?.(`${ROOT} [role="listbox"] > [role="option"]`);
    if (!option) {
        return;
    }
    option.parentElement.querySelectorAll(':scope > [role="option"][tabindex="0"]').forEach((other) => {
        other.tabIndex = -1;
    });
    option.tabIndex = 0;
});

// The first digit typed into a segment waits for a second only while the segment has focus.
document.addEventListener('focusout', (event) => {
    if (event.target.matches?.('[data-time-picker-segment]')) {
        pendingDigit.delete(event.target);
        const segments = event.target.closest('[data-time-picker-segments]');
        if (!segments.contains(event.relatedTarget)) {
            settleSegments(event.target.closest(ROOT));
        }
    }
});

// --- Livewire renders ---------------------------------------------------------------------------------

// A render puts back the server's markup. The list and the display come back right (they're drawn from the bound
// property), but the columns' greyed-out options and what's being typed into the segments are this script's: redo the
// one, and keep the other while it's being typed in (the render may answer an earlier keystroke).
onLivewireMorph((scope) => {
    const roots = [...(scope.matches?.(ROOT) ? [scope] : []), ...(scope.querySelectorAll?.(ROOT) ?? [])];
    for (const root of roots) {
        if (root.dataset.timePicker === 'columns') {
            refreshColumns(root);
        } else if (root.dataset.timePicker === 'segmented') {
            typingIn(root) ? renderSegments(root) : readSegments(root);
        }
    }
});
