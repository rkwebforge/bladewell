// Drives <x-widget.price-roll>. Setting data-value re-formats the price and rolls each digit column
// to its new --d. Columns are matched from the right (units stay units), so going from 999 to 1,000
// rolls the existing three and adds a new column on the left that rolls up from 0.

const TREND_MS = 1200;

function digitColumn() {
    const frame = document.createElement('span');
    frame.dataset.priceRollDigit = '';
    frame.className = 'inline-block h-[1lh] overflow-hidden [mask-image:linear-gradient(transparent,#000_12%,#000_88%,transparent)]';
    const column = document.createElement('span');
    column.className = 'block transition-[translate] duration-700 ease-[cubic-bezier(0.2,0.8,0.2,1)] [transition-delay:calc(var(--i)*40ms)] motion-reduce:transition-none';
    column.style.setProperty('--d', '0');
    column.style.translate = '0 calc(var(--d) * -1lh)';
    for (let n = 0; n <= 9; n++) {
        column.append(Object.assign(document.createElement('span'), { className: 'block text-center', textContent: String(n) }));
    }
    frame.append(column);

    return frame;
}

function staticChar(char) {
    return Object.assign(document.createElement('span'), { className: 'whitespace-pre', textContent: char });
}

// Kept here rather than in a data- attribute, which a Livewire render would take away: the same one set up twice would
// roll every change twice.
const ready = new WeakSet();

function initPriceRoll(root) {
    if (ready.has(root)) {
        return;
    }
    ready.add(root);

    const visual = root.querySelector('[data-price-roll-visual]');
    const text = root.querySelector('[data-price-roll-text]');
    const label = root.dataset.label ? `${root.dataset.label}: ` : '';
    const decimals = root.dataset.decimals === undefined ? {} : { minimumFractionDigits: Number(root.dataset.decimals), maximumFractionDigits: Number(root.dataset.decimals) };
    // A locale or currency code the browser doesn't know throws, which would stop every other price roll on the page:
    // fall back to a plain number in the browser's own locale instead.
    let format;
    try {
        format = new Intl.NumberFormat(root.dataset.locale || undefined, root.dataset.currency
            ? { style: 'currency', currency: root.dataset.currency, ...decimals }
            : decimals).format;
    } catch (error) {
        console.warn('Bladewell price-roll: falling back to a plain number:', error.message);
        format = new Intl.NumberFormat(undefined, decimals).format;
    }
    let current = Number(root.dataset.value) || 0;
    let trendTimer = null;

    // previous="…": the change badge, formatted exactly as the Blade view does (sign, then the absolute value).
    const badge = root.querySelector('[data-price-roll-change]');
    const previous = root.dataset.previous === undefined ? null : Number(root.dataset.previous);
    const percent = new Intl.NumberFormat(root.dataset.locale || undefined, { style: 'percent', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format;
    const colors = root.dataset.trendColors ?? 'default';
    // Good or bad rather than up or down, as in the view: inverse flips it, none has no colour.
    const tone = (direction) => (colors === 'none' || direction === 'flat' ? 'neutral' : (direction === 'up') !== (colors === 'inverse') ? 'good' : 'bad');

    function changeSince(value) {
        const delta = Math.round((value - previous) * 1e10) / 1e10;
        const direction = delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat';
        const sign = delta > 0 ? '+' : delta < 0 ? '-' : '';
        const amount = sign + format(Math.abs(delta));
        const pct = previous !== 0 ? sign + percent(Math.abs(delta / previous)) : null;
        const mode = root.dataset.change;
        const text = mode === 'amount' || pct === null ? amount : mode === 'both' ? `${amount} (${pct})` : pct;
        const spoken = direction === 'flat' ? 'unchanged' : `${direction} ${text.replace(/^[+-]/, '')}`;

        return { direction, text, spoken };
    }

    function update() {
        const next = Number(root.dataset.value);
        if (!Number.isFinite(next) || next === current) {
            return;
        }
        const chars = [...format(next)];
        const old = [...visual.children];

        // Walk both strings from the right, reusing a digit column wherever a digit meets a digit.
        const built = chars.map((char, index) => {
            const match = old[old.length - (chars.length - index)];
            const isDigit = char >= '0' && char <= '9';
            if (isDigit && match?.hasAttribute('data-price-roll-digit')) {
                return match;
            }
            if (!isDigit) {
                return match && !match.hasAttribute('data-price-roll-digit') && match.textContent === char ? match : staticChar(char);
            }
            return digitColumn();
        });
        visual.replaceChildren(...built);

        // Flush layout before setting the new digits: re-inserted columns (replaceChildren moves them) and
        // new ones (at 0) would otherwise take the new --d without a transition, i.e. jump instead of roll.
        visual.getBoundingClientRect();
        let place = built.filter((el) => el.hasAttribute('data-price-roll-digit')).length;
        built.forEach((el, index) => {
            if (el.hasAttribute('data-price-roll-digit')) {
                const column = el.firstElementChild;
                column.style.setProperty('--i', String(--place));
                column.style.setProperty('--d', chars[index]);
            }
        });

        let spoken = '';
        if (badge && previous !== null) {
            const change = changeSince(next);
            badge.dataset.direction = change.direction;
            badge.dataset.tone = tone(change.direction);
            badge.querySelector('[data-price-roll-arrow]').textContent = { up: '▲', down: '▼' }[change.direction] ?? '';
            badge.querySelector('[data-price-roll-change-text]').textContent = change.text;
            spoken = `, ${change.spoken}`;
        }
        text.textContent = label + format(next) + spoken;
        // A moment of colour on the digits for this move, good or bad per trend-colors (none: no flash).
        const flash = tone(next > current ? 'up' : 'down');
        if (flash === 'neutral') {
            delete root.dataset.flash;
        } else {
            root.dataset.flash = flash;
        }
        clearTimeout(trendTimer);
        trendTimer = setTimeout(() => delete root.dataset.flash, TREND_MS);
        current = next;
    }

    new MutationObserver(update).observe(root, { attributes: true, attributeFilter: ['data-value'] });
}

export function initPriceRolls(scope = document) {
    scope.querySelectorAll('[data-price-roll]').forEach(initPriceRoll);
}

initPriceRolls();

// Price rolls added to the page later (a Livewire render or wire:navigate, fetched HTML) set themselves up, like the
// select and date pickers. One already set up rolls from its new data-value instead.
new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            (node.matches('[data-price-roll]') ? [node] : node.querySelectorAll('[data-price-roll]')).forEach(initPriceRoll);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });
