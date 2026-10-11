// Drives <x-widget.clock> and its variants. The server paints the first frame; this keeps them ticking.
// Every clock reads the device time and formats it for its own timezone (data-timezone, set by the Blade views).
// Events: clock:finished on a countdown or timer when it reaches zero (bubbles).
// From JS: clock.start(target), clock.pause(target), clock.reset(target) on a stopwatch or timer, by its id or element;
// also on window.clock.

const LOCALE = 'en-US';
const formatters = new Map();

// Intl formatters are costly to build, so one per timezone/setting.
function formatter(timeZone, options) {
    const key = `${timeZone ?? ''}|${JSON.stringify(options)}`;
    if (!formatters.has(key)) {
        formatters.set(key, new Intl.DateTimeFormat(LOCALE, { ...options, ...(timeZone ? { timeZone } : {}) }));
    }

    return formatters.get(key);
}

// The wall-clock parts of `date` in a timezone: { year, month, day, hour, minute, second, weekday, monthName }.
function partsIn(date, timeZone) {
    const parts = {};
    for (const { type, value } of formatter(timeZone, {
        year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric',
        weekday: 'long', hourCycle: 'h23',
    }).formatToParts(date)) {
        parts[type] = value;
    }
    parts.monthName = formatter(timeZone, { month: 'long' }).format(date);

    return {
        year: Number(parts.year), month: Number(parts.month), day: Number(parts.day),
        hour: Number(parts.hour) % 24, minute: Number(parts.minute), second: Number(parts.second),
        weekday: parts.weekday, monthName: parts.monthName,
    };
}

const pad = (n) => String(n).padStart(2, '0');

// "09:41" / "9:41" + "AM", matching what the Blade views print.
function timeText({ hour, minute, second }, { hour12 = false, seconds = false } = {}) {
    const h = hour12 ? String(hour % 12 || 12) : pad(hour);

    return { time: `${h}:${pad(minute)}${seconds ? `:${pad(second)}` : ''}`, period: hour < 12 ? 'AM' : 'PM' };
}

// Minutes the timezone is ahead of UTC at `date`.
function offsetMinutes(date, timeZone) {
    const p = partsIn(date, timeZone);

    return Math.round((Date.UTC(p.year, p.month - 1, p.day, p.hour, p.minute, p.second) - date.getTime()) / 60000 / 15) * 15;
}

const setText = (el, text) => {
    if (el && el.textContent !== text) {
        el.textContent = text;
    }
};

// --- Clocks: digital, analog, world -------------------------------------------------------------------

function tickDigital(el, now) {
    const p = partsIn(now, el.dataset.timezone);
    const { time, period } = timeText(p, { hour12: 'hour12' in el.dataset, seconds: 'seconds' in el.dataset });
    setText(el.querySelector('[data-clock-time]'), time);
    setText(el.querySelector('[data-clock-period]'), period);
    setText(el.querySelector('[data-clock-date]'), `${p.weekday}, ${p.day} ${p.monthName}`);
    el.querySelector('time')?.setAttribute('datetime', now.toISOString());
}

function tickAnalog(el, now) {
    const p = partsIn(now, el.dataset.timezone);
    const angles = { hour: (p.hour % 12) * 30 + p.minute * 0.5, minute: p.minute * 6 + p.second * 0.1, second: p.second * 6 };
    for (const [name, angle] of Object.entries(angles)) {
        const hand = el.querySelector(`[data-clock-hand="${name}"]`);
        if (hand) {
            // The server's first frame uses the SVG attribute rotate(a 50 50); CSS rotate() has no centre argument,
            // so give it the clock's centre as its origin. Set from here, which a Content Security Policy allows.
            hand.style.transformOrigin = '50px 50px';
            hand.style.transform = `rotate(${angle}deg)`;
        }
    }
    const { time, period } = timeText(p, { hour12: true });
    setText(el.querySelector('[data-clock-spoken]'), `${time} ${period}`);
}

function tickWorld(el, now) {
    const p = partsIn(now, el.dataset.timezone);
    const { time, period } = timeText(p, { hour12: 'hour12' in el.dataset });
    setText(el.querySelector('[data-clock-time]'), time);
    setText(el.querySelector('[data-clock-period]'), period);

    // Relative to the visitor: "+8h", "−5h 30m", "Same time"; and whether it's already tomorrow there.
    const here = partsIn(now);
    let diff = offsetMinutes(now, el.dataset.timezone) - offsetMinutes(now);
    const sign = diff > 0 ? '+' : '−';
    diff = Math.abs(diff);
    setText(el.querySelector('[data-clock-offset]'), diff === 0 ? 'Same time' : `${sign}${Math.floor(diff / 60)}h${diff % 60 ? ` ${diff % 60}m` : ''}`);
    const dayDiff = Math.sign(Date.UTC(p.year, p.month - 1, p.day) - Date.UTC(here.year, here.month - 1, here.day));
    setText(el.querySelector('[data-clock-day]'), dayDiff > 0 ? ', Tomorrow' : dayDiff < 0 ? ', Yesterday' : '');

    const night = p.hour < 6 || p.hour >= 18;
    el.querySelector('[data-clock-sun]')?.classList.toggle('hidden', night);
    el.querySelector('[data-clock-moon]')?.classList.toggle('hidden', !night);
}

// --- Countdown ------------------------------------------------------------------------------------------

// Server time minus device time, per countdown, measured when the page loaded. Only a big difference counts: the
// render-to-script delay (a slow network, a page served from cache) also shows up here, and treating that as
// skew would make the countdown run late by it. A device clock that's really off is usually off by minutes.
const skew = new WeakMap();
const SKEW_THRESHOLD_MS = 60_000;

function tickCountdown(el) {
    if (!skew.has(el)) {
        const measured = Number(el.dataset.now) - Date.now();
        skew.set(el, Math.abs(measured) > SKEW_THRESHOLD_MS ? measured : 0);
    }
    // Rounded up, as the server does, so the first tick doesn't jump a second up and 0 means the time is up.
    const left = Math.max(0, Math.ceil((Number(el.dataset.to) - (Date.now() + skew.get(el))) / 1000));
    const units = { days: Math.floor(left / 86400), hours: Math.floor((left % 86400) / 3600), minutes: Math.floor((left % 3600) / 60), seconds: left % 60 };
    const shown = [];
    for (const [unit, n] of Object.entries(units)) {
        const cell = el.querySelector(`[data-clock-unit="${unit}"]`);
        if (cell) {
            setText(cell, unit === 'days' ? String(n) : pad(n));
            if (n > 0) {
                shown.push(`${n} ${n === 1 ? unit.slice(0, -1) : unit}`);
            }
        }
    }
    // Hours absorb the days when the countdown started with less than a day to go (no days cell).
    if (!el.querySelector('[data-clock-unit="days"]')) {
        setText(el.querySelector('[data-clock-unit="hours"]'), pad(units.hours + units.days * 24));
    }
    // Spoken without the zero units, like the server.
    setText(el.querySelector('[data-clock-spoken]'), `${shown.join(', ') || '0 seconds'} left`);

    if (left === 0 && !('finished' in el.dataset)) {
        finish(el);
    }
}

function finish(el) {
    el.dataset.finished = '';
    setText(el.querySelector('[data-clock-done]'), el.dataset.done ?? '');
    el.dispatchEvent(new CustomEvent('clock:finished', { bubbles: true }));
}

// --- Stopwatch and timer --------------------------------------------------------------------------------

// Per element: { elapsed (ms before the current run), startedAt (performance.now() of the current run, or null) }.
const runs = new WeakMap();
// Per running timer: the timeout that ends it even in a background tab (see start()).
const finishTimers = new WeakMap();

function elapsedOf(el) {
    const run = runs.get(el) ?? { elapsed: 0, startedAt: null };

    return run.elapsed + (run.startedAt === null ? 0 : performance.now() - run.startedAt);
}

function durationText(ms, tenths) {
    const total = tenths ? Math.floor(ms / 100) / 10 : Math.ceil(ms / 1000);
    const whole = Math.floor(total);
    const h = Math.floor(whole / 3600);
    const m = Math.floor((whole % 3600) / 60);
    const s = whole % 60;
    const clock = h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${m}:${pad(s)}`;

    return tenths ? `${clock}.${Math.floor((total * 10) % 10)}` : clock;
}

function renderStopwatch(el) {
    const timer = el.dataset.clock === 'timer';
    const elapsed = elapsedOf(el);
    const display = el.querySelector('[data-clock-display]');
    if (!timer) {
        setText(display, durationText(elapsed, true));

        return;
    }
    const left = Math.max(0, Number(el.dataset.duration) * 1000 - elapsed);
    setText(display, durationText(left, false));
    if (left === 0 && !('finished' in el.dataset)) {
        pause(el);
        finish(el);
        setText(el.querySelector('[data-clock-toggle-label]'), 'Start');
    }
}

function start(el) {
    if ('finished' in el.dataset) {
        reset(el);
    }
    const run = runs.get(el) ?? { elapsed: 0, startedAt: null };
    run.startedAt = performance.now();
    runs.set(el, run);
    el.dataset.running = '';
    setText(el.querySelector('[data-clock-toggle-label]'), 'Pause');
    loop();
    // Browsers pause animation frames in a background tab, so a timer's end is also scheduled with a timeout:
    // clock:finished and "Time's up" then happen on time, not when the tab is looked at again.
    if (el.dataset.clock === 'timer') {
        clearTimeout(finishTimers.get(el));
        const left = Math.max(0, Number(el.dataset.duration) * 1000 - elapsedOf(el));
        finishTimers.set(el, setTimeout(() => renderStopwatch(el), left + 20));
    }
}

function pause(el) {
    clearTimeout(finishTimers.get(el));
    const run = runs.get(el);
    if (run?.startedAt != null) {
        run.elapsed += performance.now() - run.startedAt;
        run.startedAt = null;
    }
    delete el.dataset.running;
    setText(el.querySelector('[data-clock-toggle-label]'), elapsedOf(el) > 0 ? 'Resume' : 'Start');
}

function reset(el) {
    clearTimeout(finishTimers.get(el));
    runs.set(el, { elapsed: 0, startedAt: null });
    delete el.dataset.running;
    delete el.dataset.finished;
    setText(el.querySelector('[data-clock-done]'), '');
    setText(el.querySelector('[data-clock-toggle-label]'), 'Start');
    renderStopwatch(el);
}

// Runs every frame only while something is running; the display changes every tenth of a second.
let looping = false;
function loop() {
    if (looping) {
        return;
    }
    looping = true;
    const frame = () => {
        const running = document.querySelectorAll('[data-clock="stopwatch"][data-running], [data-clock="timer"][data-running]');
        running.forEach(renderStopwatch);
        if (running.length) {
            requestAnimationFrame(frame);
        } else {
            looping = false;
        }
    };
    requestAnimationFrame(frame);
}

document.addEventListener('click', (event) => {
    const toggle = event.target.closest?.('[data-clock-toggle]');
    const resetButton = event.target.closest?.('[data-clock-reset]');
    const el = (toggle ?? resetButton)?.closest('[data-clock="stopwatch"], [data-clock="timer"]');
    if (!el) {
        return;
    }
    if (resetButton) {
        reset(el);
    } else if ('running' in el.dataset) {
        pause(el);
    } else {
        start(el);
    }
});

// --- One tick for all the clocks, on the second ---------------------------------------------------------

function tick() {
    const now = new Date();
    document.querySelectorAll('[data-clock="digital"]').forEach((el) => tickDigital(el, now));
    document.querySelectorAll('[data-clock="analog"]').forEach((el) => tickAnalog(el, now));
    document.querySelectorAll('[data-clock="world"]').forEach((el) => tickWorld(el, now));
    document.querySelectorAll('[data-clock="countdown"]:not([data-finished])').forEach(tickCountdown);
}

tick();
// Line the ticks up with the device's second, so seconds change together and on time.
setTimeout(() => {
    tick();
    setInterval(tick, 1000);
}, 1000 - (Date.now() % 1000));

// A stopwatch or timer by its id, or the element itself or anything in it (its Start button, say).
const stopwatchOf = (target) => {
    const element = typeof target === 'string' ? document.getElementById(target) : target;
    const el = element?.closest?.('[data-clock="stopwatch"], [data-clock="timer"]');
    if (!el) {
        console.warn('Bladewell clock: no stopwatch or timer on this page for', target);
    }

    return el ?? null;
};
const call = (action) => (target) => {
    const el = stopwatchOf(target);
    if (el) {
        action(el);
    }
};

export const clock = { start: call(start), pause: call(pause), reset: call(reset) };

// Global, as modal and toast are, so a script on the page can call it without an import.
window.clock = clock;

// A Livewire render puts back the server's first frame: its time, and world clocks' offsets from the app's timezone
// rather than the visitor's. Tick straight away, so that frame never shows. (Stopwatches and timers are wire:ignore'd.)
const hook = (Livewire) => Livewire.hook('morphed', () => tick());
if (window.Livewire) {
    hook(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => hook(window.Livewire));
}
