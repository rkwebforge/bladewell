// Drives <x-widget.calendar>, which the server draws in full. What it can't know:
// - The viewer's today and time. A page can come from a cache days later, or stay open past midnight, so the mark
//   moves to today's date in the calendar's own timezone (data-timezone), the Today link to today's period, and in a
//   week or a day, the line at the current time to today's column. Checked each minute, when the tab comes back into
//   view, and after a Livewire render, which puts the server's mark back.
// - Where the hours should open. A week or a day scrolls its hours to data-calendar-scroll (the earlier of 7:00 and
//   its first event) once, when first seen; after that the scrolling is the viewer's.
// - What a script or Livewire wants from the links. Each fires calendar:navigate on the calendar (and
//   calendar-navigate, for wire:calendar-navigate) with detail { date, view }: YYYY-MM for a month, YYYY-MM-DD for a
//   week or a day, and the view it's for (a view button's own, otherwise the one showing). A listener calling preventDefault() keeps the page where it is; so does a wire:calendar-navigate on the
//   calendar, whose component then changes the date itself.
//
// The links are real links, and without this script each loads its page. With it, a link fetches that page and takes
// only this calendar from it, so the rest of the page (and its scrolling) stays as it is; the address changes as it
// would have, and Back goes back. Anything unexpected (no answer, no such calendar in it) follows the link instead.
// Left to the browser, as plain links: a calendar with the reload prop (data-reload), for pages where something else
// changes with the date; and one inside a Livewire component, which draws its own markup.

const CALENDAR = '[data-calendar]';
const MINUTES_IN_A_DAY = 24 * 60;

// The date (YYYY-MM-DD) and minutes since midnight in a timezone.
const nowIn = (timezone) => {
    try {
        const parts = Object.fromEntries(
            new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
                .formatToParts(new Date())
                .map(({ type, value }) => [type, value]),
        );

        return { date: `${parts.year}-${parts.month}-${parts.day}`, minutes: Number(parts.hour) * 60 + Number(parts.minute) };
    } catch {
        return null;
    }
};

// The first day of the week holding a date, as the server counts weeks: from the week's first column.
const weekOf = (calendar, date) => {
    const first = calendar.querySelector('[data-calendar-column]')?.dataset.calendarColumn;
    if (!first) {
        return date;
    }
    const day = (iso) => Date.UTC(Number(iso.slice(0, 4)), Number(iso.slice(5, 7)) - 1, Number(iso.slice(8, 10)));
    const offset = ((((day(date) - day(first)) / 86400000) % 7) + 7) % 7;

    return new Date(day(date) - offset * 86400000).toISOString().slice(0, 10);
};

function mark(calendar) {
    const now = nowIn(calendar.dataset.timezone);
    if (!now) {
        return;
    }

    for (const day of calendar.querySelectorAll('[data-calendar-day]')) {
        const isToday = day.dataset.calendarDay === now.date;
        day.toggleAttribute('data-today', isToday);
        if (isToday) {
            day.setAttribute('aria-current', 'date');
        } else {
            day.removeAttribute('aria-current');
        }
    }

    // The current time, in today's column if it's shown. Placed by the script, so a style property rather than a class.
    const line = calendar.querySelector('[data-calendar-now]');
    const column = calendar.querySelector(`[data-calendar-column="${now.date}"]`);
    if (line) {
        if (column && line.parentElement !== column) {
            column.append(line);
        }
        line.hidden = !column;
        line.style.top = `${(now.minutes / MINUTES_IN_A_DAY) * 100}%`;
    }

    // The Today link, kept within the links' min and max as the server keeps it.
    const link = calendar.querySelector('[data-calendar-nav="today"]');
    const { url, min, max, view } = calendar.dataset;
    let date = view === 'month' ? now.date.slice(0, 7) : now.date;
    date = min && date < min ? min : max && date > max ? max : date;
    if (view === 'week') {
        date = weekOf(calendar, date);
    }
    if (link && url && link.dataset.date !== date) {
        link.dataset.date = date;
        link.href = url.replaceAll('{date}', date);
    }
}

// Opened once per box, when it's shown: a week's hours are hidden on a phone, where its list shows instead.
const opened = new WeakSet();
function open(box) {
    const hours = box.querySelector('[data-calendar-hours]');
    if (opened.has(box) || !hours || hours.offsetHeight === 0) {
        return;
    }
    opened.add(box);
    const head = box.querySelector('[data-calendar-head]')?.offsetHeight ?? 0;
    // A little above the hour, so its label shows below the day headings.
    const hour = hours.offsetHeight / 24;
    box.scrollTop = hours.offsetTop - head + hour * Number(box.dataset.calendarScroll || 0) - hour / 4;
}

// An event focused from the keyboard is scrolled into view below the day headings, which stay at the top of the box:
// as far below as they are tall, which changes with the all-day events and the width.
function clearHead(box) {
    const head = box.querySelector('[data-calendar-head]')?.offsetHeight ?? 0;
    if (head > 0) {
        box.style.scrollPaddingTop = `${head + 4}px`;
    }
}

function refresh(calendar) {
    mark(calendar);
    for (const box of calendar.querySelectorAll('[data-calendar-scroll]')) {
        clearHead(box);
        open(box);
    }
}

function say(calendar, message) {
    const status = calendar.querySelector('[data-calendar-status]');
    if (!status) {
        return;
    }
    // Emptied first, and filled a frame later, so it's read out even when it's the same words, or newly on the page.
    status.textContent = '';
    requestAnimationFrame(() => {
        status.textContent = message;
    });
}

// Per calendar, the request for the period it's moving to: a newer click drops the older one, so a slow answer for a
// month already left never replaces the one being shown.
const moving = new WeakMap();
// Once the address has been changed without a page load, Back needs one to show what that address shows.
let moved = false;

const swappable = (calendar, url) => !calendar.hasAttribute('data-reload')
    && !calendar.closest('[wire\\:id]')
    && calendar.id !== ''
    && url.origin === location.origin;

async function go(calendar, link) {
    const { param, viewParam } = calendar.dataset;
    // The link was written for the address the page had when it was drawn. Another calendar may have changed it since:
    // take only this one's parameters (its date, and its view) from the link, onto the address as it is now, so the
    // others stay where they are.
    const linked = new URL(link.href, location.href);
    const url = param ? new URL(location.href) : linked;
    for (const name of param ? [param, viewParam].filter(Boolean) : []) {
        if (linked.searchParams.has(name)) {
            url.searchParams.set(name, linked.searchParams.get(name));
        } else {
            url.searchParams.delete(name);
        }
    }
    if (!swappable(calendar, url)) {
        location.assign(url);

        return;
    }

    moving.get(calendar)?.abort();
    const request = new AbortController();
    moving.set(calendar, request);
    calendar.setAttribute('aria-busy', 'true');
    const which = link.dataset.calendarNav;

    try {
        const response = await fetch(url, { signal: request.signal, headers: { Accept: 'text/html' } });
        if (!response.ok) {
            throw new Error(`The calendar's page answered ${response.status}.`);
        }
        const fresh = new DOMParser().parseFromString(await response.text(), 'text/html').getElementById(calendar.id);
        if (!fresh?.matches(CALENDAR)) {
            throw new Error("The calendar isn't on the page its link leads to.");
        }

        // The same element, so anything holding on to it (a listener, a script's reference) still has the calendar.
        for (const { name } of [...calendar.attributes]) {
            if (name.startsWith('data-') && !fresh.hasAttribute(name)) {
                calendar.removeAttribute(name);
            }
        }
        for (const { name, value } of fresh.attributes) {
            if (name.startsWith('data-') || name === 'aria-label') {
                calendar.setAttribute(name, value);
            }
        }
        calendar.replaceChildren(...document.importNode(fresh, true).childNodes);
        history.pushState(history.state, '', url);
        moved = true;
        refresh(calendar);

        // Focus was on the link pressed, which is gone: on the same link of the new period, or, where that one has
        // reached min or max, on Today.
        const again = which === 'view' ? `[data-calendar-nav="view"][data-view="${link.dataset.view}"]` : `[data-calendar-nav="${which}"][href]`;
        (calendar.querySelector(again) ?? calendar.querySelector('[data-calendar-nav="today"]'))?.focus({ preventScroll: true });
        say(calendar, calendar.querySelector('[id$="-title"]')?.textContent.trim() ?? '');
    } catch (error) {
        if (error.name !== 'AbortError') {
            location.assign(url);
        }
    } finally {
        if (moving.get(calendar) === request) {
            moving.delete(calendar);
            calendar.removeAttribute('aria-busy');
        }
    }
}

const markAll = () => {
    document.querySelectorAll(CALENDAR).forEach(mark);
    for (const box of document.querySelectorAll('[data-calendar-scroll]')) {
        clearHead(box);
        open(box);
    }
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-calendar-nav][href]');
    const calendar = link?.closest(CALENDAR);
    // A click that opens a new tab or window is left to the browser.
    if (!calendar || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const detail = { date: link.dataset.date, view: link.dataset.view ?? calendar.dataset.view };
    let kept = false;
    for (const name of ['calendar:navigate', 'calendar-navigate']) {
        const fired = new CustomEvent(name, { bubbles: true, cancelable: true, detail });
        kept = !calendar.dispatchEvent(fired) || kept;
    }
    const wired = [...calendar.attributes].some((attribute) => attribute.name.startsWith('wire:calendar-navigate'));
    event.preventDefault();
    if (!kept && !wired) {
        go(calendar, link);
    }
});

window.addEventListener('popstate', () => {
    if (moved) {
        location.reload();
    }
});

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        markAll();
    }
});
// Livewire's SPA navigation brings new calendars without a page load.
document.addEventListener('livewire:navigated', markAll);
// A week's hours appear when the page widens past a phone's.
window.addEventListener('resize', () => {
    for (const box of document.querySelectorAll('[data-calendar-scroll]')) {
        clearHead(box);
        open(box);
    }
});
markAll();
setInterval(markAll, 60000);

const hook = (Livewire) => Livewire.hook('morphed', markAll);
if (window.Livewire) {
    hook(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => hook(window.Livewire));
}
