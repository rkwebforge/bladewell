// Drives <x-widget.datepicker>. Dates are plain local calendar days (no time
// part) so a timezone offset can never shift the selected day. The date helpers are
// exported for <x-widget.date-range-picker>, which works on the same calendar days.
import { closeIfOutOfView, visibleArea } from '../field';

export const pad = (n) => String(n).padStart(2, '0');
export const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
export const daysInMonth = (year, month) => new Date(year, month + 1, 0).getDate();

// Keeps the day where possible but never overflows: 31 Jan + 1 month is 28/29 Feb, not 3 Mar.
export const addMonths = (d, n) => {
    const target = new Date(d.getFullYear(), d.getMonth() + n, 1);
    const day = Math.min(d.getDate(), daysInMonth(target.getFullYear(), target.getMonth()));

    return new Date(target.getFullYear(), target.getMonth(), day);
};

export const parseIso = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');

    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
};

// Without a limit the calendar still ends somewhere: the first and last days a YYYY-MM-DD value can hold.
export const EARLIEST = new Date(1000, 0, 1);
export const LATEST = new Date(9999, 11, 31);

// The years the year list offers, newest first: up to each limit, or, without one, a stretch around today (`back`
// years before it, ten after) that grows as the calendar is moved past it. Rebuilt only when that changes.
export function fillYears(select, { min, max, today, shown, back, tools }) {
    const first = min > EARLIEST ? min.getFullYear() : Math.max(EARLIEST.getFullYear(), Math.min(today.getFullYear() - back, shown - 10));
    const last = max < LATEST ? max.getFullYear() : Math.min(LATEST.getFullYear(), Math.max(today.getFullYear() + 10, shown + 10));
    if (select.options[0]?.value === String(last) && select.options[select.options.length - 1].value === String(first)) {
        return;
    }
    select.replaceChildren();
    for (let year = last; year >= first; year--) {
        select.add(new Option(tools.number(year), String(year)));
    }
}

// How the picker's locale writes dates, shared with <x-widget.date-range-picker>. The locale comes from the
// server (data-locale), which rendered the first paint in the same format. Always the Gregorian calendar,
// because the grid is: some locales (ar-SA, th) would otherwise show Hijri or Buddhist dates above it.
// Names, word order and digits stay the locale's own.
export function localeTools(root) {
    const locale = root.dataset.locale || document.documentElement.lang || undefined;
    const gregory = { calendar: 'gregory' };
    const date = new Intl.DateTimeFormat(locale, { ...gregory, year: 'numeric', month: 'short', day: 'numeric' });
    const number = new Intl.NumberFormat(locale, { useGrouping: false });

    return {
        locale,
        rtl: getComputedStyle(root).direction === 'rtl',
        format: (d) => date.format(d),
        formatRange: (from, to) => date.formatRange(from, to),
        number: (n) => number.format(n),
        monthName: new Intl.DateTimeFormat(locale, { ...gregory, month: 'long' }),
        monthCaption: new Intl.DateTimeFormat(locale, { ...gregory, month: 'long', year: 'numeric' }),
        shortWeekday: new Intl.DateTimeFormat(locale, { ...gregory, weekday: 'short' }),
        longWeekday: new Intl.DateTimeFormat(locale, { ...gregory, weekday: 'long' }),
        dayLabel: new Intl.DateTimeFormat(locale, { ...gregory, dateStyle: 'full' }),
    };
}

// Where a popover sits: under the field, lined up with its start edge (the right one in RTL), never off-screen.
export function alignedLeft(box, width, rtl) {
    const edge = rtl ? box.right - width : box.left;

    return Math.max(VIEWPORT_EDGE, Math.min(edge, window.innerWidth - width - VIEWPORT_EDGE));
}

// Arrow keys follow the reading direction: in RTL, left is forward in time.
export const horizontalStep = (key, rtl) => (key === 'ArrowRight' ? 1 : -1) * (rtl ? -1 : 1);

// One tone per state so no two classes fight over the same property.
// Fluid days: each fills its (equal, table-fixed) column up to 44px and stays round,
// so the grid scales with the dropdown instead of forcing a fixed width.
const DAY_BASE = 'mx-auto grid aspect-square w-full max-w-11 place-items-center rounded-full text-sm outline-none transition-colors focus-visible:ring-2 focus-visible:ring-primary';
const DAY_TONE = {
    selected: 'bg-primary-fill font-semibold text-on-primary',
    disabled: 'cursor-not-allowed text-muted/50',
    outside: 'text-muted hover:bg-field',
    today: 'font-semibold text-primary hover:bg-field',
    default: 'text-foreground hover:bg-field',
};
// Added on top of whichever tone applies, so today stays marked even when disabled or outside the month.
const TODAY_MARK = 'ring-1 ring-inset ring-primary';

export const VIEWPORT_EDGE = 16;
export const POPOVER_GAP = 4;
// Narrowest the calendar gets (32px days); a narrower field lets it overhang instead.
export const MIN_WIDTH = 252;

// Set up once per element. Not a data attribute: Livewire's morph removes attributes the server didn't render.
const ready = new WeakSet();

function initDatepicker(root) {
    if (ready.has(root)) {
        return;
    }
    ready.add(root);

    const trigger = root.querySelector('[data-datepicker-trigger]');
    const display = root.querySelector('[data-datepicker-display]');
    const input = root.querySelector('[data-datepicker-input]');
    const popover = root.querySelector('[data-datepicker-popover]');
    const monthSelect = popover.querySelector('[data-datepicker-month]');
    const yearSelect = popover.querySelector('[data-datepicker-year]');
    const prevButton = popover.querySelector('[data-datepicker-prev]');
    const nextButton = popover.querySelector('[data-datepicker-next]');
    const grid = popover.querySelector('[data-datepicker-grid]');

    const tools = localeTools(root);
    const weekStart = Number(root.dataset.weekStart || 0);
    // "today" means the user's local date, not the server's (see the Blade component). It and the limits are worked
    // out again each time the calendar opens (refreshLimits): a page left open past midnight moves today on, and
    // limits a Livewire render has changed since (data-min, data-max) apply.
    let today;
    let min;
    let max;
    const parseLimit = (value) => (value === 'today' ? today : parseIso(value));
    // On 29 February, 18 years back is the 28th when that year has no 29th: someone born on 1 March isn't 18 until
    // tomorrow.
    const yearsBack = (date, years) => {
        if (!date || !years) {
            return date;
        }
        const year = date.getFullYear() - years;

        return new Date(year, date.getMonth(), Math.min(date.getDate(), daysInMonth(year, date.getMonth())));
    };
    // A birthday picker (max-years-back) has to reach the oldest people using the form, not stop at 100.
    const defaultSpan = root.dataset.maxYearsBack ? 120 : 100;

    function refreshLimits() {
        const now = new Date();
        today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        min = parseLimit(root.dataset.min) ?? EARLIEST;
        max = yearsBack(parseLimit(root.dataset.max), Number(root.dataset.maxYearsBack || 0)) ?? LATEST;
    }
    refreshLimits();

    let selected = parseIso(input.value);
    let focused = today;

    const isOutOfRange = (d) => d < min || d > max;
    const clamp = (d) => (d > max ? max : d < min ? min : d);
    const weekdayOffset = (d) => (d.getDay() - weekStart + 7) % 7;

    for (let month = 0; month < 12; month++) {
        monthSelect.add(new Option(tools.monthName.format(new Date(2000, month, 1)), String(month)));
    }

    const headRow = grid.createTHead().insertRow();
    for (let i = 0; i < 7; i++) {
        // 1 Jan 2023 was a Sunday, so offsetting from it lists weekdays in order.
        const day = new Date(2023, 0, 1 + ((weekStart + i) % 7));
        const cell = document.createElement('th');
        cell.scope = 'col';
        cell.abbr = tools.longWeekday.format(day);
        cell.textContent = tools.shortWeekday.format(day);
        cell.className = 'pb-1 text-xs font-medium text-muted';
        headRow.append(cell);
    }
    const body = grid.createTBody();

    function render() {
        const year = focused.getFullYear();
        const month = focused.getMonth();

        fillYears(yearSelect, { min, max, today, shown: year, back: defaultSpan, tools });
        yearSelect.value = String(year);
        monthSelect.value = String(month);
        for (const option of monthSelect.options) {
            const m = Number(option.value);
            option.disabled = new Date(year, m + 1, 0) < min || new Date(year, m, 1) > max;
        }
        prevButton.disabled = new Date(year, month, 0) < min;
        nextButton.disabled = new Date(year, month + 1, 1) > max;

        const focusedIso = toIso(focused);
        const selectedIso = selected ? toIso(selected) : null;
        const todayIso = toIso(today);
        let day = addDays(new Date(year, month, 1), -weekdayOffset(new Date(year, month, 1)));

        body.replaceChildren();
        // Always six weeks so the popover height doesn't jump between months.
        for (let week = 0; week < 6; week++) {
            const row = body.insertRow();

            for (let i = 0; i < 7; i++, day = addDays(day, 1)) {
                const iso = toIso(day);
                const disabled = isOutOfRange(day);
                const tone = iso === selectedIso ? 'selected'
                    : disabled ? 'disabled'
                    : day.getMonth() !== month ? 'outside'
                    : iso === todayIso ? 'today'
                    : 'default';

                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = tools.number(day.getDate());
                button.dataset.date = iso;
                button.disabled = disabled;
                button.tabIndex = iso === focusedIso ? 0 : -1;
                button.className = `${DAY_BASE} ${DAY_TONE[tone]}${iso === todayIso ? ` ${TODAY_MARK}` : ''}`;
                button.setAttribute('aria-label', tools.dayLabel.format(day));
                button.setAttribute('aria-pressed', String(iso === selectedIso));
                if (iso === todayIso) {
                    button.setAttribute('aria-current', 'date');
                }

                row.insertCell().append(button);
            }
        }
    }

    const focusDay = () => grid.querySelector('button[tabindex="0"]')?.focus();

    function moveFocus(date) {
        focused = clamp(date);
        render();
        focusDay();
    }

    function changeMonth(date, sourceButton) {
        focused = clamp(date);
        render();
        // A nav button that just became disabled drops focus to <body>; keep keyboard users in the grid.
        if (sourceButton?.disabled) {
            focusDay();
        }
    }

    function select(date) {
        selected = date;
        input.value = toIso(date);
        display.textContent = tools.format(date);
        display.removeAttribute('data-empty');
        // Both events: tools like Alpine x-model and Livewire wire:model listen for `input`, plain forms for `change`.
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        popover.hidePopover();
        trigger.focus();
    }

    function position() {
        if (closeIfOutOfView(trigger, popover)) {
            return;
        }
        const box = trigger.getBoundingClientRect();
        // Same width as the field (edges line up), but at least MIN_WIDTH and never wider than the screen.
        const fit = Math.min(Math.max(box.width, MIN_WIDTH), window.innerWidth - VIEWPORT_EDGE * 2);
        popover.style.width = `${fit}px`;
        // On very short screens the calendar scrolls inside itself rather than running off-screen.
        const view = visibleArea();
        popover.style.maxHeight = `${view.bottom - view.top - VIEWPORT_EDGE * 2}px`;

        const { offsetWidth: width, offsetHeight: height } = popover;
        const below = box.bottom + POPOVER_GAP;
        const above = box.top - POPOVER_GAP - height;

        let top;
        if (below + height <= view.bottom - VIEWPORT_EDGE) {
            top = below;
        } else if (above >= view.top + VIEWPORT_EDGE) {
            top = above;
        } else {
            // No room either side (e.g. a phone in landscape): stay fully visible, even if that covers the field.
            top = Math.max(view.top + VIEWPORT_EDGE, view.bottom - VIEWPORT_EDGE - height);
        }

        popover.style.top = `${top}px`;
        popover.style.left = `${alignedLeft(box, width, tools.rtl)}px`;
    }

    const KEY_MOVES = {
        ArrowLeft: (d) => addDays(d, horizontalStep('ArrowLeft', tools.rtl)),
        ArrowRight: (d) => addDays(d, horizontalStep('ArrowRight', tools.rtl)),
        ArrowUp: (d) => addDays(d, -7),
        ArrowDown: (d) => addDays(d, 7),
        Home: (d) => addDays(d, -weekdayOffset(d)),
        End: (d) => addDays(d, 6 - weekdayOffset(d)),
        PageUp: (d, event) => addMonths(d, event.shiftKey ? -12 : -1),
        PageDown: (d, event) => addMonths(d, event.shiftKey ? 12 : 1),
    };

    grid.addEventListener('keydown', (event) => {
        const move = KEY_MOVES[event.key];
        if (!move) {
            return;
        }
        event.preventDefault();
        moveFocus(move(focused, event));
    });

    grid.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-date]');
        if (button && !button.disabled) {
            select(parseIso(button.dataset.date));
        }
    });

    prevButton.addEventListener('click', () => changeMonth(addMonths(focused, -1), prevButton));
    nextButton.addEventListener('click', () => changeMonth(addMonths(focused, 1), nextButton));

    const onViewSelect = () => {
        const year = Number(yearSelect.value);
        const month = Number(monthSelect.value);
        changeMonth(new Date(year, month, Math.min(focused.getDate(), daysInMonth(year, month))));
    };
    monthSelect.addEventListener('change', onViewSelect);
    yearSelect.addEventListener('change', onViewSelect);

    popover.addEventListener('beforetoggle', (event) => {
        if (event.newState !== 'open') {
            return;
        }
        refreshLimits();
        // The value as it is now: a Livewire render or another script may have changed it since.
        selected = parseIso(input.value);
        // Open on the selected day, or today clamped into range, so a birthday
        // picker doesn't open on a month where every day is disabled.
        focused = clamp(selected ?? today);
        render();
        // Hidden until positioned, otherwise it flashes at the popover default (screen centre).
        popover.style.visibility = 'hidden';
    });

    popover.addEventListener('toggle', (event) => {
        const open = event.newState === 'open';
        trigger.setAttribute('aria-expanded', String(open));

        if (!open) {
            window.removeEventListener('scroll', position, true);
            window.removeEventListener('resize', position);
            window.visualViewport?.removeEventListener('resize', position);

            return;
        }

        position();
        popover.style.visibility = '';
        focusDay();
        // Capture phase so scrolling any ancestor container also repositions it.
        window.addEventListener('scroll', position, true);
        window.addEventListener('resize', position);
        window.visualViewport?.addEventListener('resize', position);
    });
}

export function initDatepickers(scope = document) {
    scope.querySelectorAll('[data-datepicker]').forEach(initDatepicker);
}

initDatepickers();

// Date pickers added to the page later (a Livewire render or wire:navigate, fetched HTML) set themselves up.
new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            (node.matches('[data-datepicker]') ? [node] : node.querySelectorAll('[data-datepicker]')).forEach(initDatepicker);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });
