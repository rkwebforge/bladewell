// Drives <x-widget.date-range-picker>: the first click sets the start, the second the end (a click
// before the start restarts from there). On a touch screen, a slide from one day to another picks the
// range in one go, the band following the finger. Nothing is submitted until both ends are chosen;
// closing half-way keeps the previous range.
import { closeIfOutOfView, visibleArea } from '../field';
import { addDays, addMonths, alignedLeft, DAY_BASE, daysInMonth, EARLIEST, fillYears, horizontalStep, LATEST, localeTools, MIN_WIDTH, parseIso, POPOVER_GAP, TODAY_MARK, toIso, VIEWPORT_EDGE } from '../datepicker';

// Day shape (DAY_BASE) and the today ring (TODAY_MARK) come from the datepicker, so restyling its days restyles these too.
const DAY_TONE = {
    endpoint: 'bg-primary-fill font-semibold text-on-primary',
    disabled: 'cursor-not-allowed text-muted/50',
    inRange: 'text-foreground hover:bg-primary/20',
    today: 'font-semibold text-primary hover:bg-field',
    default: 'text-foreground hover:bg-field',
};
// The band behind the range sits on the cells, so it runs unbroken between the round day buttons;
// on the two ends it covers only the inner half. bg-clip-content + py leaves a gap between weeks.
const CELL_BASE = 'bg-clip-content py-0.5';
const BAND = {
    middle: 'bg-primary/10',
    // In RTL the range runs right to left, so each end's half-band flips.
    start: 'bg-linear-to-r rtl:bg-linear-to-l from-transparent from-50% to-primary/10 to-50%',
    end: 'bg-linear-to-l rtl:bg-linear-to-r from-transparent from-50% to-primary/10 to-50%',
};
// Two months need two calendars' width; on a narrower screen one is shown.
const TWO_MONTH_WIDTH = MIN_WIDTH * 2;

const firstOfMonth = (d) => new Date(d.getFullYear(), d.getMonth(), 1);
const monthsBetween = (from, to) => (to.getFullYear() - from.getFullYear()) * 12 + to.getMonth() - from.getMonth();
const daysBetween = (from, to) => Math.round((to - from) / 86_400_000);

// Set up once per element. Not a data attribute: Livewire's morph removes attributes the server didn't render.
const ready = new WeakSet();

function initDateRange(root) {
    if (ready.has(root)) {
        return;
    }
    ready.add(root);

    const trigger = root.querySelector('[data-date-range-trigger]');
    const display = root.querySelector('[data-date-range-display]');
    const startInput = root.querySelector('[data-date-range-start]');
    const endInput = root.querySelector('[data-date-range-end]');
    const popover = root.querySelector('[data-date-range-popover]');
    const monthSelect = popover.querySelector('[data-date-range-month]');
    const yearSelect = popover.querySelector('[data-date-range-year]');
    const prevButton = popover.querySelector('[data-date-range-prev]');
    const nextButton = popover.querySelector('[data-date-range-next]');
    const monthsEl = popover.querySelector('[data-date-range-months]');
    const status = popover.querySelector('[data-date-range-status]');
    const clearButton = popover.querySelector('[data-date-range-clear]');

    const tools = localeTools(root);
    // Screen-reader labels and the status line. Change the wording here.
    const text = { start: 'start date', end: 'end date', inRange: 'in range', chooseStart: 'Choose the start date', chooseEnd: 'From :date, choose the end date' };
    // "8 days" in the locale's own words and plural rules (Arabic has six forms), with nothing to translate.
    const dayCount = new Intl.NumberFormat(tools.locale, { style: 'unit', unit: 'day', unitDisplay: 'long' });
    const weekStart = Number(root.dataset.weekStart || 0);
    // Worked out again each time the calendar opens: a page left open past midnight moves "today" on, and limits a
    // Livewire render has changed since (data-min, data-max) apply.
    let today;
    let min;
    let max;
    const parseLimit = (value) => (value === 'today' ? today : parseIso(value));

    function refreshLimits() {
        const now = new Date();
        today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        min = parseLimit(root.dataset.min) ?? EARLIEST;
        max = parseLimit(root.dataset.max) ?? LATEST;
    }
    refreshLimits();

    const isOutOfRange = (d) => d < min || d > max;
    const clamp = (d) => (d > max ? max : d < min ? min : d);
    const weekdayOffset = (d) => (d.getDay() - weekStart + 7) % 7;

    let committed = { start: parseIso(startInput.value), end: parseIso(endInput.value) };
    let draft = { ...committed };
    let hover = null;
    let focused = today;
    let view = firstOfMonth(today);
    let count = 1;

    for (let month = 0; month < 12; month++) {
        monthSelect.add(new Option(tools.monthName.format(new Date(2000, month, 1)), String(month)));
    }

    const monthsToShow = () => (root.dataset.months === '2' && window.innerWidth - VIEWPORT_EDGE * 2 >= TWO_MONTH_WIDTH ? 2 : 1);
    const pickingEnd = () => draft.start && !draft.end;

    // Keep the focused day on screen, and don't open onto a month that lies wholly past the limit:
    // with max=today the current month is shown on the right, not on the left beside a blank one.
    function placeView() {
        if (focused < view) {
            view = firstOfMonth(focused);
        } else if (monthsBetween(view, focused) >= count) {
            view = addMonths(firstOfMonth(focused), 1 - count);
        }
        if (count === 2 && new Date(view.getFullYear(), view.getMonth() + 1, 1) > max && new Date(view.getFullYear(), view.getMonth(), 0) >= min) {
            view = addMonths(view, -1);
        }
    }

    // Structure: one table per month shown. Colours and state are applied by paint().
    function build() {
        count = monthsToShow();
        placeView();
        const year = view.getFullYear();
        const month = view.getMonth();

        fillYears(yearSelect, { min, max, today, shown: year, back: 100, tools });
        yearSelect.value = String(year);
        monthSelect.value = String(month);
        for (const option of monthSelect.options) {
            const m = Number(option.value);
            option.disabled = new Date(year, m + 1, 0) < min || new Date(year, m, 1) > max;
        }
        prevButton.disabled = new Date(year, month, 0) < min;
        nextButton.disabled = new Date(year, month + count, 1) > max;

        monthsEl.classList.toggle('grid-cols-2', count === 2);
        monthsEl.replaceChildren(...Array.from({ length: count }, (_, i) => monthTable(addMonths(view, i))));
        paint();
    }

    function monthTable(first) {
        const table = document.createElement('table');
        table.className = 'w-full table-fixed border-collapse text-center';
        // With one month the selects above already name it; with two, each grid is captioned.
        const caption = table.createCaption();
        caption.textContent = tools.monthCaption.format(first);
        caption.className = count === 2 ? 'mb-1 text-sm font-medium' : 'sr-only';

        const headRow = table.createTHead().insertRow();
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

        const body = table.createTBody();
        let day = addDays(first, -weekdayOffset(first));
        // Six weeks always, so the height never jumps. Days of the neighbouring months stay blank:
        // with two months side by side they would otherwise appear twice.
        for (let week = 0; week < 6; week++) {
            const row = body.insertRow();
            for (let i = 0; i < 7; i++, day = addDays(day, 1)) {
                const cell = row.insertCell();
                cell.className = CELL_BASE;
                if (day.getMonth() !== first.getMonth()) {
                    continue;
                }
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = tools.number(day.getDate());
                button.dataset.date = toIso(day);
                button.disabled = isOutOfRange(day);
                cell.append(button);
            }
        }

        return table;
    }

    // State: endpoints, the band between them (following the pointer or focus while the end is being
    // chosen), today, the roving tabindex and the labels. Runs on every hover, so no rebuilding here.
    function paint() {
        const start = draft.start ? toIso(draft.start) : null;
        const preview = pickingEnd() && hover && hover >= draft.start ? hover : null;
        const end = draft.end ? toIso(draft.end) : preview ? toIso(preview) : null;
        const committedEnd = draft.end ? toIso(draft.end) : null;
        const focusedIso = toIso(focused);
        const todayIso = toIso(today);

        for (const button of monthsEl.querySelectorAll('button[data-date]')) {
            const iso = button.dataset.date;
            const isEndpoint = iso === start || iso === end;
            const inRange = start && end && iso > start && iso < end;
            const tone = isEndpoint ? 'endpoint' : button.disabled ? 'disabled' : inRange ? 'inRange' : iso === todayIso ? 'today' : 'default';
            const band = !start || !end || start === end ? '' : inRange ? BAND.middle : iso === start ? BAND.start : iso === end ? BAND.end : '';

            button.className = `${DAY_BASE} ${DAY_TONE[tone]}${iso === todayIso ? ` ${TODAY_MARK}` : ''}`;
            button.parentElement.className = `${CELL_BASE} ${band}`;
            button.tabIndex = iso === focusedIso ? 0 : -1;
            // The labels describe the chosen range only, not the hover preview.
            const role = iso === start ? text.start : iso === committedEnd ? text.end : committedEnd && iso > start && iso < committedEnd ? text.inRange : null;
            button.setAttribute('aria-label', tools.dayLabel.format(parseIso(iso)) + (role ? `, ${role}` : ''));
            button.setAttribute('aria-pressed', String(iso === start || iso === committedEnd));
            if (iso === todayIso) {
                button.setAttribute('aria-current', 'date');
            }
        }

        status.textContent = draft.start && draft.end
            ? `${tools.formatRange(draft.start, draft.end)} · ${dayCount.format(daysBetween(draft.start, draft.end) + 1)}`
            : draft.start ? text.chooseEnd.replace(':date', tools.format(draft.start)) : text.chooseStart;
        clearButton.disabled = !draft.start;
    }

    const focusDay = () => monthsEl.querySelector('button[tabindex="0"]')?.focus();

    function moveFocus(date) {
        focused = clamp(date);
        if (pickingEnd()) {
            hover = focused;
        }
        const visible = focused >= view && monthsBetween(view, focused) < count;
        visible ? paint() : build();
        focusDay();
    }

    function changeView(target, sourceButton) {
        view = firstOfMonth(target);
        focused = clamp(new Date(view.getFullYear(), view.getMonth(), Math.min(focused.getDate(), daysInMonth(view.getFullYear(), view.getMonth()))));
        build();
        // A nav button that just became disabled drops focus to <body>; keep keyboard users in the grid.
        if (sourceButton?.disabled) {
            focusDay();
        }
    }

    // formatRange already writes the shortest natural form for the locale ("Sep 26 – Oct 3, 2026"). If even
    // that is cut off in a narrow field, the tooltip keeps the whole range.
    function fitDisplay() {
        const { start, end } = committed;
        display.toggleAttribute('data-empty', !start);
        display.textContent = start ? tools.formatRange(start, end) : display.dataset.placeholder;
        syncTitle();
    }

    // Only reads the layout (a title doesn't change it), so a page of pickers observed at load measures once,
    // instead of each one rewriting its text and forcing a layout before the next can measure.
    function syncTitle() {
        committed.start && display.scrollWidth > display.clientWidth ? display.setAttribute('title', display.textContent) : display.removeAttribute('title');
    }

    function commit() {
        committed = { ...draft };
        startInput.value = committed.start ? toIso(committed.start) : '';
        endInput.value = committed.end ? toIso(committed.end) : '';
        fitDisplay();
        // Both events on both inputs: x-model / wire:model listen for `input`, plain forms for `change`.
        for (const input of [startInput, endInput]) {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function pick(date) {
        focused = date;
        if (pickingEnd() && date >= draft.start) {
            draft.end = date;
            commit();
            popover.hidePopover();
            trigger.focus();

            return;
        }
        draft = { start: date, end: null };
        hover = date;
        paint();
        focusDay();
    }

    function position() {
        if (closeIfOutOfView(trigger, popover)) {
            return;
        }
        const box = trigger.getBoundingClientRect();
        const wanted = count === 2 ? TWO_MONTH_WIDTH : MIN_WIDTH;
        const fit = Math.min(Math.max(box.width, wanted), window.innerWidth - VIEWPORT_EDGE * 2);
        popover.style.width = `${fit}px`;
        const view = visibleArea();
        popover.style.maxHeight = `${view.bottom - view.top - VIEWPORT_EDGE * 2}px`;

        const { offsetWidth: width, offsetHeight: height } = popover;
        const below = box.bottom + POPOVER_GAP;
        const above = box.top - POPOVER_GAP - height;
        const top = below + height <= view.bottom - VIEWPORT_EDGE ? below
            : above >= view.top + VIEWPORT_EDGE ? above
            : Math.max(view.top + VIEWPORT_EDGE, view.bottom - VIEWPORT_EDGE - height);

        popover.style.top = `${top}px`;
        popover.style.left = `${alignedLeft(box, width, tools.rtl)}px`;
    }

    // Turning a phone, or resizing the window, can change how many months fit.
    function onResize() {
        if (monthsToShow() !== count) {
            // build() replaces every day button; put focus back on the day it was on, not on <body>.
            const hadFocus = popover.contains(document.activeElement);
            build();
            if (hadFocus) {
                focusDay();
            }
        }
        position();
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

    monthsEl.addEventListener('keydown', (event) => {
        const move = KEY_MOVES[event.key];
        if (move && event.target.matches('button[data-date]')) {
            event.preventDefault();
            moveFocus(move(focused, event));
        }
    });

    monthsEl.addEventListener('click', (event) => {
        // The click a slide leaves behind on the day it started from: the slide has already picked. Enter on a day is
        // a click with no pointer, and always picks.
        if (slid && event.pointerType !== '') {
            slid = false;

            return;
        }
        // Anywhere in a day's cell, not only on its round button: a finger that lands just beside it still means it.
        const button = event.target.closest('td')?.querySelector('button[data-date]');
        if (button && !button.disabled) {
            pick(parseIso(button.dataset.date));
        }
    });

    // Touch and pen have no hover to preview the range with, so a slide does it: press the start day, slide, and the
    // band follows the finger; lifting it on a day picks that as the end. A tap without a slide picks as a click does.
    // The finger's day is found by where it is, as a touch keeps reporting to the day it started on.
    let slide = null;
    let slid = false;
    // A finger wobbles as it taps: it only slides once it has gone further than this from where it pressed.
    const SLIDE_AFTER_PX = 12;
    // The day a point is on: its whole cell, not just the round button in it, so the gaps between days count too.
    // Undefined off the calendar; null on it but not on a day that can be picked.
    const dayAt = (event) => {
        const element = document.elementFromPoint(event.clientX, event.clientY);
        if (!element || !monthsEl.contains(element)) {
            return undefined;
        }
        const button = element.closest('td')?.querySelector('button[data-date]:enabled');

        return button ? parseIso(button.dataset.date) : null;
    };
    monthsEl.addEventListener('pointerdown', (event) => {
        slid = false;
        const button = event.target.closest('td')?.querySelector('button[data-date]:enabled');
        slide = event.pointerType !== 'mouse' && button
            ? { id: event.pointerId, from: parseIso(button.dataset.date), x: event.clientX, y: event.clientY, moved: false, last: null }
            : null;
    });
    monthsEl.addEventListener('pointermove', (event) => {
        if (slide?.id !== event.pointerId) {
            return;
        }
        if (!slide.moved && Math.hypot(event.clientX - slide.x, event.clientY - slide.y) < SLIDE_AFTER_PX) {
            return;
        }
        const date = dayAt(event);
        if (!date || (!slide.moved && toIso(date) === toIso(slide.from))) {
            return;
        }
        slide.last = date;
        if (!slide.moved) {
            // The day it started on is the start, whatever was picked before.
            slide.moved = true;
            draft = { start: slide.from, end: null };
        }
        const next = date >= draft.start ? date : null;
        if ((next && toIso(next)) !== (hover && toIso(hover))) {
            hover = next;
            paint();
        }
    });
    monthsEl.addEventListener('pointerup', (event) => {
        if (slide?.id !== event.pointerId) {
            return;
        }
        const { moved, last } = slide;
        slide = null;
        if (!moved) {
            return;
        }
        slid = true;
        // Not every browser sends that click after a slide; don't let the flag eat a later one.
        setTimeout(() => { slid = false; }, 400);
        // Lifted between days, or on one that can't be picked: the last day the finger was on is the end.
        const at = dayAt(event);
        const date = at === undefined ? null : (at ?? last);
        if (date && date >= draft.start) {
            pick(date);
        } else {
            // Lifted off the calendar, or before the start: the start stays, and the end is still to choose.
            hover = null;
            focused = draft.start;
            paint();
        }
    });
    monthsEl.addEventListener('pointercancel', () => {
        if (slide?.moved) {
            hover = null;
            paint();
        }
        slide = null;
    });

    // Preview the range under the pointer while the end is being chosen.
    monthsEl.addEventListener('mouseover', (event) => {
        const button = event.target.closest('button[data-date]:enabled');
        const date = button ? parseIso(button.dataset.date) : null;
        if (pickingEnd() && date && toIso(date) !== (hover && toIso(hover))) {
            hover = date;
            paint();
        }
    });
    monthsEl.addEventListener('mouseleave', () => {
        if (pickingEnd() && hover) {
            hover = null;
            paint();
        }
    });

    prevButton.addEventListener('click', () => changeView(addMonths(view, -1), prevButton));
    nextButton.addEventListener('click', () => changeView(addMonths(view, 1), nextButton));
    const onViewSelect = () => changeView(new Date(Number(yearSelect.value), Number(monthSelect.value), 1));
    monthSelect.addEventListener('change', onViewSelect);
    yearSelect.addEventListener('change', onViewSelect);

    clearButton.addEventListener('click', () => {
        draft = { start: null, end: null };
        hover = null;
        commit();
        paint();
        focusDay();
    });

    // The field's width changes with the layout (grid columns, rotation), so re-fit whenever it does.
    new ResizeObserver(syncTitle).observe(trigger);

    popover.addEventListener('beforetoggle', (event) => {
        if (event.newState !== 'open') {
            return;
        }
        refreshLimits();
        // The range as it is now (a Livewire render or another script may have changed it), and from there: an
        // unfinished pick from last time is dropped.
        committed = { start: parseIso(startInput.value), end: parseIso(endInput.value) };
        draft = { ...committed };
        hover = null;
        focused = clamp(committed.start ?? today);
        view = firstOfMonth(focused);
        build();
        // Hidden until positioned, otherwise it flashes at the popover default (screen centre).
        popover.style.visibility = 'hidden';
    });

    popover.addEventListener('toggle', (event) => {
        const open = event.newState === 'open';
        trigger.setAttribute('aria-expanded', String(open));

        if (!open) {
            window.removeEventListener('scroll', position, true);
            window.removeEventListener('resize', onResize);
            window.visualViewport?.removeEventListener('resize', position);

            return;
        }

        position();
        popover.style.visibility = '';
        focusDay();
        // Capture phase so scrolling any ancestor container also repositions it.
        window.addEventListener('scroll', position, true);
        window.addEventListener('resize', onResize);
        // The on-screen keyboard changes only the visible height, so placing it again is enough.
        window.visualViewport?.addEventListener('resize', position);
    });
}

export function initDateRangePickers(scope = document) {
    scope.querySelectorAll('[data-date-range]').forEach(initDateRange);
}

initDateRangePickers();

// Range pickers added to the page later (a Livewire render or wire:navigate, fetched HTML) set themselves up.
new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            (node.matches('[data-date-range]') ? [node] : node.querySelectorAll('[data-date-range]')).forEach(initDateRange);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });
