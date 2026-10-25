// Drives <x-widget.table.row>: rows with `href` navigate, rows with a `details` slot expand.
// Pagination links are ordinary URLs (they work without JS); this script also swaps pages in place.

// A swapped-in table may bring selects in its filters toolbar, which bind per element.
import { initSelects, refreshOptions } from '../select';

// Clicks on these inside a row do their own thing instead of activating the row.
const INTERACTIVE = 'a, button, input, select, textarea, label, summary, [role="button"], [data-no-row-click]';
const ROW = 'tr[data-href], tr[data-expandable]';
// Matches the details row's duration-300 transition.
const COLLAPSE_MS = 300;
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// The row's Details button carries the state for screen readers; the row's data-expanded styles it.
function toggle(row) {
    const button = row.querySelector('[data-row-toggle]');
    const open = button.getAttribute('aria-expanded') !== 'true';
    const details = document.getElementById(button.getAttribute('aria-controls'));

    button.setAttribute('aria-expanded', String(open));
    row.toggleAttribute('data-expanded', open);
    // Collapsed content stays out of the tab order and away from screen readers.
    details.inert = !open;
    clearTimeout(details.hideTimer);

    if (open) {
        // Un-hide first and force a layout, so the height transition runs from 0 instead of snapping open.
        details.hidden = false;
        void details.offsetHeight;
        details.setAttribute('data-open', '');
    } else {
        details.removeAttribute('data-open');
        // Hide once the collapse transition (duration-300) is over, so it takes no space at all.
        details.hideTimer = setTimeout(() => {
            details.hidden = true;
        }, reducedMotion() ? 0 : COLLAPSE_MS);
    }
}

function activate(row, newTab) {
    if (row.hasAttribute('data-expandable')) {
        toggle(row);
    } else if (newTab) {
        window.open(row.dataset.href, '_blank', 'noopener');
    } else {
        window.location.assign(row.dataset.href);
    }
}

function rowFromEvent(event) {
    const row = event.target.closest?.(ROW);
    const control = event.target.closest?.(INTERACTIVE);
    if (!row || (control && row.contains(control))) {
        return null;
    }

    return row;
}

document.addEventListener('click', (event) => {
    // The Details button is a button, so the row's own click (below) leaves it alone; it toggles here instead.
    const toggleButton = event.target.closest?.('[data-row-toggle]');
    if (toggleButton) {
        toggle(toggleButton.closest('tr'));

        return;
    }
    const row = rowFromEvent(event);
    // Selecting text in a row shouldn't navigate away.
    if (row && !window.getSelection()?.toString()) {
        activate(row, event.metaKey || event.ctrlKey);
    }
});

// Middle-click opens a linked row in a new tab, like a normal link.
document.addEventListener('auxclick', (event) => {
    const row = rowFromEvent(event);
    if (row && event.button === 1 && row.dataset.href) {
        activate(row, true);
    }
});

// --- Keyboard access to wide tables ---------------------------------------------------

// A table that overflows sideways can only be scrolled with a mouse or touch unless its scroll area
// can take focus. Make it focusable (and give it a name for screen readers) only while it overflows,
// so tables that fit don't get a pointless extra tab stop.
function syncScrollable(scroller) {
    // How wide the visible part is, so an expanded row's text wraps to fit the screen rather than the whole table.
    scroller.style.setProperty('--table-visible', `${scroller.clientWidth}px`);
    // Sideways (wide table) or up/down (windowed infinite table): either way arrow keys need focus to scroll.
    const overflows = scroller.scrollWidth > scroller.clientWidth + 1 || scroller.scrollHeight > scroller.clientHeight + 1;

    if (overflows) {
        scroller.tabIndex = 0;
        scroller.setAttribute('role', 'region');
        scroller.setAttribute('aria-label', scroller.dataset.label);
    } else {
        scroller.removeAttribute('tabindex');
        scroller.removeAttribute('role');
        scroller.removeAttribute('aria-label');
    }
}

const resizes = new ResizeObserver((entries) => entries.forEach((entry) => syncScrollable(entry.target)));

// Run by setUp (end of this file) for every table.
function watchScrollables(scope) {
    scope.querySelectorAll('[data-table-scroll]').forEach((scroller) => {
        // max-height="24rem" can be any length, so it can't be a prebuilt class, and style="" is blocked by a
        // strict Content Security Policy; setting it from here isn't. Without JS the table just isn't capped.
        if (scroller.dataset.maxHeight) {
            scroller.style.maxHeight = scroller.dataset.maxHeight;
        }
        syncScrollable(scroller);
        resizes.observe(scroller);
    });
}

// --- Page changes without a full reload -------------------------------------------------

// A pagination link is a real link, but following it reloads the whole page: the browser paints the
// top first and only then jumps to #table-…, which flickers on long pages. Instead, fetch the target
// page, swap in just this table, and update the URL.
//
// On a slow or dead connection: the spinner (CSS, after 300ms) shows it's working, a newer click
// cancels the older request, and after TIMEOUT_MS or with no connection the current page stays put
// with an error toast. An HTTP error (500, expired session…) still does a normal page load, so the
// user sees the real error or login page.
const TIMEOUT_MS = 20_000;
let inFlight = null;
// Tables the current request is for; a cancelled request must only un-fade tables no longer loading.
let loading = new Set();

function settleSuperseded(roots) {
    roots.forEach((root) => {
        if (!loading.has(root)) {
            setBusy(root, false);
        }
    });
}

// aria-busy fades the rows and shows the spinner (CSS, see table/index). With while-loading="skeleton", placeholder
// rows stand in for them instead, once the load has taken SKELETON_AFTER_MS, so fast responses never flash them.
const SKELETON_AFTER_MS = 150;
const skeletonTimers = new WeakMap();

function setBusy(root, busy) {
    clearTimeout(skeletonTimers.get(root));
    if (!busy) {
        root.removeAttribute('aria-busy');
        showSkeleton(root, false);

        return;
    }
    root.setAttribute('aria-busy', 'true');
    if (root.dataset.whileLoading === 'skeleton') {
        skeletonTimers.set(root, setTimeout(() => showSkeleton(root, true), SKELETON_AFTER_MS));
    }
}

function showSkeleton(root, show) {
    const skeleton = root.querySelector('tbody[data-table-skeleton]');
    const rows = skeleton?.parentElement.querySelector(':scope > tbody:not([data-table-skeleton])');
    if (skeleton && rows) {
        skeleton.hidden = !show;
        rows.hidden = show;
    }
}

// A fetched page, ready to take tables from. Its inline <style> blocks go first: they carry that response's CSP
// nonce, not this page's, so under a strict policy the browser reports each one as a violation just for being parsed
// (Laravel's @fonts adds one to every page). Only the tables are taken from the page, and they never hold one.
function parsePage(html) {
    return new DOMParser().parseFromString(html.replace(/<style\b[^>]*>[\s\S]*?<\/style>/gi, ''), 'text/html');
}

// --- cache-for: pages already fetched, in memory ------------------------------------------------------

// Only the tables of each page are kept, not the whole page, and at most CACHE_ENTRIES of them, oldest dropped
// first. Never localStorage or the like: tables often hold private data, which mustn't outlive the page.
const CACHE_ENTRIES = 20;
const pageCache = new Map();
const cacheKey = (url) => url.split('#')[0];

function cachedPage(root, url) {
    const seconds = Number(root.dataset.cacheFor);
    const hit = seconds ? pageCache.get(cacheKey(url)) : null;
    if (!hit || Date.now() - hit.at > seconds * 1000) {
        return null;
    }

    return parsePage(hit.html);
}

function remember(root, url, page) {
    if (!Number(root.dataset.cacheFor)) {
        return;
    }
    const key = cacheKey(url);
    const html = [...page.querySelectorAll('[data-table-root][id]')].map((table) => table.outerHTML).join('');
    // Re-inserted, so the most recently used page is the last to be dropped.
    pageCache.delete(key);
    pageCache.set(key, { html, at: Date.now() });
    while (pageCache.size > CACHE_ENTRIES) {
        pageCache.delete(pageCache.keys().next().value);
    }
}

/** @returns {Promise<{ page?: Document, failure?: 'network' | 'http' | 'superseded' }>} */
async function fetchPage(url) {
    inFlight?.abort();
    const controller = new AbortController();
    inFlight = controller;
    const timer = setTimeout(() => controller.abort('timeout'), TIMEOUT_MS);

    try {
        const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin', signal: controller.signal });
        if (!response.ok) {
            return { failure: 'http' };
        }

        return { page: parsePage(await response.text()) };
    } catch {
        // Aborted by a newer click stays silent; a timeout or no connection is a network failure.
        return { failure: controller.signal.aborted && controller.signal.reason !== 'timeout' ? 'superseded' : 'network' };
    } finally {
        clearTimeout(timer);
        if (inFlight === controller) {
            inFlight = null;
        }
    }
}

// One polite live region for the page, created once: a region inside the table would be replaced
// along with it, and screen readers don't announce text in an element that has only just appeared.
function announce(message) {
    let region = document.getElementById('table-announcer');
    if (!region) {
        region = Object.assign(document.createElement('p'), { id: 'table-announcer', className: 'sr-only' });
        region.setAttribute('role', 'status');
        document.body.append(region);
    }
    region.textContent = '';
    // Set in a separate task so an identical repeat message is still read out.
    setTimeout(() => {
        region.textContent = message;
    }, 50);
}

function failedToLoad(roots) {
    roots.forEach((root) => setBusy(root, false));
    const message = 'Couldn’t load that page. Check your connection and try again.';
    window.toast ? window.toast.error(message, { title: 'Connection problem' }) : window.alert(message);
    announce(message);
}

// Replaces a table with its counterpart (same id) from a fetched page; null if the page lacks it.
function replaceTable(root, page) {
    const fresh = page?.getElementById(root.id);
    if (!fresh?.hasAttribute('data-table-root')) {
        return null;
    }
    // Close the page sheet if it was open; the old one goes away with the old table.
    root.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
    const adopted = document.adoptNode(fresh);
    root.replaceWith(adopted);
    clearTimeout(skeletonTimers.get(root));
    initSelects(adopted);
    watchScrollables(adopted);
    enhance(adopted);
    // A sorted infinite table arrives as a fresh window and starts loading again.
    if (adopted.hasAttribute('data-infinite')) {
        initWindow(adopted);
    }

    return adopted;
}

// Filtering swaps everything in the table but its toolbar (keep), which stays where it is: moving the field being
// typed in, even for a moment, takes its focus and caret away. The root stays too, with the fresh one's attributes;
// null as replaceTable, or its result if the fresh table has no toolbar to stand in for. What the server works out
// for the toolbar (option counts) is carried over from the fresh copy.
function refreshToolbar(keep, fresh) {
    keep.querySelectorAll('[data-select]').forEach((select) => {
        const id = select.querySelector('[data-select-trigger]')?.id;
        const counterpart = id ? fresh.querySelector(`[data-select]:has(#${CSS.escape(id)})`) : null;
        if (counterpart) {
            refreshOptions(select, counterpart);
        }
    });
}

function replaceAround(root, page, keep) {
    const fresh = page?.getElementById(root.id);
    if (!fresh?.hasAttribute('data-table-root')) {
        return null;
    }
    const children = [...fresh.children];
    const at = children.findIndex((child) => child.matches('[data-table-toolbar]'));
    if (at === -1) {
        return replaceTable(root, page);
    }
    refreshToolbar(keep, children[at]);
    root.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
    clearTimeout(skeletonTimers.get(root));
    [...root.attributes].forEach((attribute) => root.removeAttribute(attribute.name));
    [...fresh.attributes].forEach((attribute) => root.setAttribute(attribute.name, attribute.value));
    [...root.children].forEach((child) => child !== keep && child.remove());
    keep.before(...children.slice(0, at).map((child) => document.adoptNode(child)));
    keep.after(...children.slice(at + 1).map((child) => document.adoptNode(child)));
    // Still the same element, so what was set up once per table is set up again for its new parts.
    thumbed.delete(root);
    windowed.delete(root);
    watchScrollables(root);
    enhance(root);
    if (root.hasAttribute('data-infinite')) {
        initWindow(root);
    }

    return root;
}

// Other tables keep their rows, but their page links were built for the old URL; without this, using
// them would drop the page this swap just set. The fetched page already has fresh links for every table.
function refreshOtherPagination(page, except) {
    document.querySelectorAll('[data-table-root][id]').forEach((root) => {
        // A Livewire component's links are its own to render.
        if (root === except || inLivewire(root)) {
            return;
        }
        const current = root.querySelector('[data-table-pagination]');
        const fresh = page.getElementById(root.id)?.querySelector('[data-table-pagination]');
        if (current && fresh) {
            current.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
            current.replaceWith(document.adoptNode(fresh));
        }
    });
}

// The query parameters a table answers to: its page, its sort and its filter fields.
function paramsOf(root) {
    const form = root.querySelector(':scope > form[data-table-filters]');

    return new Set([root.dataset.pageName, root.dataset.sortName, root.dataset.directionName, ...(form ? new FormData(form).keys() : [])].filter(Boolean));
}

// A table's links were built when its page was drawn, with every other table's page and sort as they were then. Since,
// another table may have been paged, sorted or filtered in place: its parameters are taken from the address bar
// instead, so following the link keeps it where it is. Those a Livewire component's table owns are its own to keep.
function withOtherTables(href, root) {
    const url = new URL(href, window.location.href);
    const here = new URLSearchParams(window.location.search);
    const own = paramsOf(root);
    document.querySelectorAll('[data-table-root][id]').forEach((other) => {
        if (other === root || inLivewire(other)) {
            return;
        }
        paramsOf(other).forEach((name) => {
            if (own.has(name)) {
                return;
            }
            url.searchParams.delete(name);
            here.getAll(name).forEach((value) => url.searchParams.append(name, value));
        });
    });

    return url.href;
}

// push: a new history entry (a page or sort link, a filter picked); otherwise a filter replaces the entry, as while
// typing. filter: focus stays where it is, in the toolbar (keep, see replaceAround), and the result count is read out.
async function swapTable(root, url, { push = false, sort = false, filter = false, keep = null }) {
    loading = new Set([root]);
    let page = cachedPage(root, url);
    if (page) {
        // A cached answer is newer than anything still on its way.
        inFlight?.abort();
    } else {
        setBusy(root, true);
        const result = await fetchPage(url);
        if (result.failure === 'superseded') {
            settleSuperseded([root]);

            return;
        }
        if (result.failure === 'network') {
            failedToLoad([root]);
            // Nothing changed, so the same filter asked for again (Enter, or picking it again) is tried again.
            if (filter) {
                lastFilter.delete(root);
            }

            return;
        }
        page = result.page;
        if (page) {
            remember(root, url, page);
        }
    }
    const fresh = page && (keep ? replaceAround(root, page, keep) : replaceTable(root, page));
    if (!fresh) {
        window.location.assign(url);

        return;
    }
    setBusy(fresh, false);
    refreshOtherPagination(page, fresh);

    if (push) {
        window.history.pushState({ tableId: fresh.id }, '', url);
    } else if (filter) {
        window.history.replaceState({ ...window.history.state, tableId: fresh.id }, '', url);
    }
    if (filter) {
        announceResults(fresh);
    } else {
        settle(fresh, { sort });
    }
}

function announceResults(root) {
    const total = root.dataset.total !== undefined ? Number(root.dataset.total) : root.querySelectorAll('tbody tr[data-table-row]').length;
    announce(total === 0 ? 'No results' : `${total.toLocaleString()} ${total === 1 ? 'result' : 'results'}`);
}

// Keep the table in view (its top may have been scrolled away), and move focus to it so keyboard
// and screen-reader users land on the new page rather than on a link that no longer exists.
function settle(fresh, { sort }) {
    if (fresh.getBoundingClientRect().top < 0) {
        fresh.scrollIntoView({ block: 'start' });
    }
    fresh.tabIndex = -1;
    fresh.focus({ preventScroll: true });
    // After a sort, say the new order; after a page change, the page.
    const sorted = sort ? fresh.querySelector('th[aria-sort]') : null;
    if (sorted) {
        announce(`Sorted by ${sorted.dataset.label}, ${sorted.getAttribute('aria-sort')}`);

        return;
    }
    // Numbers and jump styles mark the current page with aria-current; the drawer shows it on its sheet button.
    const current = fresh.querySelector('[data-table-pagination] [aria-current="page"], [data-table-pagination] [data-modal-open]')?.textContent.trim();
    announce(current ? `Page ${current.replace(/\s+/g, ' ')} loaded` : 'Page loaded');
}

// Page links and sort links (in the header) both swap just their table.
const tableFor = (element) => element.closest('[data-table-pagination], [data-table-sort]')?.closest('[data-table-root][id]');

// --- In a Livewire component ------------------------------------------------------------------------

// There the component owns the rows. Swapping the table would leave its state (page, sort) behind, and its next
// render would put the old rows back. So a page or sort link becomes a call on the component instead.
const inLivewire = (root) => root.closest('[wire\\:id]') !== null;
const wireFor = (root) => window.Livewire?.find(root.closest('[wire\\:id]')?.getAttribute('wire:id'));
// Tables whose page or sort is changing: their ticked boxes and open rows start fresh, as they do without Livewire.
const resetting = new WeakSet();

async function changePage(root, href, { sort = false }) {
    typingEntry.delete(root.id);
    if (!inLivewire(root)) {
        swapTable(root, withOtherTables(href, root), { push: true, sort });

        return;
    }
    const wire = wireFor(root);
    if (!wire || !(await viaLivewire(root, wire, href, { sort }))) {
        window.location.assign(href);
    }
}

// The link's query says what to change: its page goes to gotoPage() (WithPagination), and the table's own sort
// and direction parameters to the component properties of those names. Only those: sort links carry the page's
// whole query, so setting any property a link names would let a crafted URL (?isAdmin=1) set it on the next
// click. Anything else that differs from the address bar can't be done in place; false, and the caller loads
// the page instead.
async function viaLivewire(root, wire, href, { sort }) {
    const target = new URL(href, window.location.href);
    const here = new URL(window.location.href);
    const { pageName, sortName, directionName } = root.dataset;
    const settable = new Set([sortName, directionName].filter(Boolean));
    const updates = [];
    for (const [name, value] of target.searchParams) {
        if (name === pageName) {
            continue;
        }
        if (settable.has(name) && wire.$get(name) !== undefined) {
            updates.push([name, value]);
        } else if (here.searchParams.get(name) !== value) {
            return false;
        }
    }
    // A sort link without the sort parameters is the third click, which removes the sort: empty the properties too,
    // or the component would keep sorting.
    if (sort) {
        settable.forEach((name) => {
            const current = wire.$get(name);
            if (!target.searchParams.has(name) && current !== undefined && current !== null && current !== '') {
                updates.push([name, typeof current === 'string' ? '' : null]);
            }
        });
    }
    // A sort link leaves the page out: back to the first one.
    const page = pageName ? (target.searchParams.get(pageName) ?? (root.hasAttribute('data-page-cursor') ? '' : '1')) : null;
    const paginated = wire.$get('paginators') !== undefined;
    if (page !== null && !paginated) {
        return false;
    }

    root.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
    resetting.add(root);
    setBusy(root, true);
    // Not live: they travel with the gotoPage()/$refresh() request, so it's one round trip.
    updates.forEach(([name, value]) => wire.$set(name, value, false));
    try {
        await (page !== null ? wire.$call('gotoPage', page, pageName) : wire.$refresh());
    } finally {
        resetting.delete(root);
        setBusy(root, false);
    }
    const fresh = root.isConnected ? root : document.getElementById(root.id);
    if (fresh) {
        settle(fresh, { sort });
    }

    return true;
}

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]');
    const root = link && tableFor(link);
    // With multi-sort, Shift adds the column to the sort (Shift+Enter too: it clicks with the key held).
    const adding = event.shiftKey && link?.dataset.sortAdd;
    // Let the browser handle new-tab/window clicks and anything that isn't a same-origin page link.
    if (!root || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || (event.shiftKey && !adding) || event.altKey) {
        return;
    }
    const url = new URL(adding || link.href, window.location.href);
    if (url.origin !== window.location.origin) {
        return;
    }
    event.preventDefault();
    changePage(root, url.href, { sort: link.hasAttribute('data-table-sort') });
});

// The "go to page" form: same thing, built from its GET fields.
document.addEventListener('submit', (event) => {
    const form = event.target;
    const root = tableFor(form);
    if (!root || form.method.toLowerCase() !== 'get') {
        return;
    }
    event.preventDefault();
    const url = new URL(form.action, window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    changePage(root, url.href, {});
});

// --- Filters (the filters slot) ------------------------------------------------------------------------

// A GET form in the table's toolbar. Text fields filter once typing pauses for FILTER_AFTER_MS, anything else
// (selects, checkboxes) as soon as it changes; Enter does it at once. Each goes back to the first page, keeps the
// sort (in the form's hidden fields) and every other table's page, and swaps the table in place. In a Livewire
// component the fields are yours to bind (wire:model), and Livewire does the rest.
const FILTER_AFTER_MS = 300;
const TEXT_FIELD = 'input:not([type]), input[type="text"], input[type="search"], input[type="email"], input[type="number"], input[type="tel"], input[type="url"], textarea';
const typingTimers = new WeakMap();
// The query each table was last asked to show, so a change event after the same text was typed doesn't fetch again.
const lastFilter = new WeakMap();
// History: picking a filter is a step Back undoes, so it gets an entry of its own. Typing gets one entry for the
// whole burst: the first pause adds it, later ones update it. By table id; cleared by any other step.
const typingEntry = new Set();

// Empty fields are left out, so a cleared filter leaves no ?q= behind.
function filterUrl(form, root, fields = new FormData(form)) {
    const url = new URL(form.action, window.location.href);
    const query = new URLSearchParams(window.location.search);
    new Set([...new FormData(form).keys()]).forEach((name) => query.delete(name));
    if (root.dataset.pageName) {
        query.delete(root.dataset.pageName);
    }
    for (const [name, value] of fields) {
        if (typeof value === 'string' && value !== '') {
            query.append(name, value);
        }
    }
    url.search = query.toString();

    return url;
}

function filter(form, { now = false } = {}) {
    const toolbar = form.closest('[data-table-toolbar]');
    const root = toolbar?.parentElement?.closest('[data-table-root][id]');
    clearTimeout(typingTimers.get(form));
    if (!root || inLivewire(root)) {
        return;
    }
    const run = () => {
        const url = filterUrl(form, root);
        if (url.search === (lastFilter.get(root) ?? window.location.search)) {
            return;
        }
        lastFilter.set(root, url.search);
        const push = now || !typingEntry.has(root.id);
        if (now) {
            typingEntry.delete(root.id);
        } else {
            typingEntry.add(root.id);
        }
        swapTable(root, url.href, { filter: true, keep: toolbar, push });
    };
    if (now) {
        run();
    } else {
        typingTimers.set(form, setTimeout(run, FILTER_AFTER_MS));
    }
}

const filtersOf = (event) => event.target.closest?.('form[data-table-filters]');

document.addEventListener('input', (event) => {
    const form = filtersOf(event);
    if (form && event.target.matches(TEXT_FIELD)) {
        filter(form);
    }
});

document.addEventListener('change', (event) => {
    const form = filtersOf(event);
    // The Columns menu sits in the same toolbar; its ticks aren't filters.
    if (form && !event.target.matches(TEXT_FIELD) && !event.target.matches('[data-table-column]')) {
        filter(form, { now: true });
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target.closest?.('form[data-table-filters]');
    if (form) {
        event.preventDefault();
        filter(form, { now: true });
    }
});

// "Clear filters" (in the empty state, or any element of yours marked data-table-filters-clear inside the table):
// drops this table's filter fields from the URL, keeping the sort, and swaps in the table with an empty toolbar.
document.addEventListener('click', async (event) => {
    const clear = event.target.closest?.('[data-table-filters-clear]');
    const root = clear?.closest('[data-table-root][id]');
    const form = root?.querySelector(':scope > form[data-table-filters]');
    if (!form || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    event.preventDefault();
    if (inLivewire(root)) {
        clearInLivewire(root, form);

        return;
    }
    const sort = [root.dataset.sortName, root.dataset.directionName];
    const url = filterUrl(form, root, [...new FormData(form)].filter(([name]) => sort.includes(name)));
    lastFilter.set(root, url.search);
    typingEntry.delete(root.id);
    await swapTable(root, url.href, { filter: true, push: true });
    document.getElementById(root.id)?.querySelector('[data-table-filters] :is(input:not([type="hidden"]), button, select, textarea)')?.focus();
});

// Every property the toolbar's fields are bound to goes back to empty, then one request: the first page, or a render.
function clearInLivewire(root, form) {
    const wire = wireFor(root);
    if (!wire) {
        return;
    }
    const names = new Set([...form.querySelectorAll('*')].flatMap((el) => [...el.attributes].filter((attribute) => attribute.name.startsWith('wire:model')).map((attribute) => attribute.value)));
    names.forEach((name) => {
        const value = wire.$get(name);
        wire.$set(name, Array.isArray(value) ? [] : typeof value === 'string' ? '' : null, false);
    });
    const { pageName } = root.dataset;
    wire.$get('paginators') !== undefined && pageName ? wire.$call('gotoPage', 1, pageName) : wire.$refresh();
}

// Back/forward: bring every table back to what the URL says (several may have changed since that entry).
// Not those in a Livewire component: Livewire restores its own state from the URL.
window.addEventListener('popstate', async (event) => {
    const roots = [...document.querySelectorAll('[data-table-root][id]')].filter((root) => !inLivewire(root));
    if (!event.state?.tableId || roots.length === 0) {
        return;
    }
    loading = new Set(roots);
    roots.forEach((root) => setBusy(root, true));
    const url = window.location.href;
    const { page, failure } = await fetchPage(url);

    if (failure === 'superseded') {
        settleSuperseded(roots);

        return;
    }
    if (failure === 'network') {
        failedToLoad(roots);

        return;
    }
    if (!page || roots.some((root) => !replaceTable(root, page))) {
        window.location.assign(url);
    }
});

// Tag the entry the page was loaded on, so going back to it also swaps instead of doing nothing. Merged into the
// entry's state rather than replacing it, which would wipe what Livewire keeps there.
if (!window.history.state?.tableId) {
    const first = [...document.querySelectorAll('[data-table-root][id]')].find((root) => !inLivewire(root));
    if (first) {
        window.history.replaceState({ ...window.history.state, tableId: first.id }, '');
    }
}

// --- Windowed infinite scroll (pagination="infinite") ---------------------------------

// The table scrolls inside itself. Rows arrive in batches (one server page each) at whichever end you
// approach, and once more than MAX_BATCHES are in the DOM the batch farthest away is dropped, so even a
// 10-lakh-row list holds only a few pages of rows. One batch alone would fit the view exactly and leave
// nothing to scroll, hence one above and one below the visible one. When the window is tall next to a page
// (visible-rows well above per-page), it keeps a batch or two more, just enough to scroll without flipping.
const MAX_BATCHES = 3;
const EDGE_PX = 256;
const RETRY_AFTER_MS = 5000;
const ID_REFERENCES = ['aria-controls', 'aria-describedby', 'aria-labelledby', 'for', 'popovertarget', 'data-modal-open'];
let batchNumber = 0;

// Every fetched page numbers its ids from scratch (row-details, row-details-2 …), so a new batch can
// clash with rows already on the page. Rename those ids and the references to them inside the batch.
function uniquifyIds(rows) {
    batchNumber++;
    const all = rows.flatMap((row) => [row, ...row.querySelectorAll('*')]);
    const renamed = new Map();
    all.forEach((el) => {
        if (el.id && document.getElementById(el.id)) {
            renamed.set(el.id, `${el.id}-b${batchNumber}`);
        }
    });
    if (renamed.size === 0) {
        return;
    }
    all.forEach((el) => {
        if (renamed.has(el.id)) {
            el.id = renamed.get(el.id);
        }
        ID_REFERENCES.forEach((attribute) => {
            const value = el.getAttribute(attribute);
            if (value) {
                el.setAttribute(attribute, value.split(' ').map((ref) => renamed.get(ref) ?? ref).join(' '));
            }
        });
    });
}

async function fetchTablePage(url) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
    try {
        const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin', signal: controller.signal });

        return response.ok ? parsePage(await response.text()) : null;
    } catch {
        return null;
    } finally {
        clearTimeout(timer);
    }
}

const windows = new WeakMap();
const dataRows = (container) => [...container.children].filter((row) => row.matches('tr:not([aria-hidden="true"])'));

function batchFrom(page, root, url) {
    const fresh = page?.getElementById(root.id);
    if (!fresh?.hasAttribute('data-infinite')) {
        return null;
    }
    const rows = dataRows(fresh.querySelector('tbody'));
    rows.forEach((row) => document.adoptNode(row));
    uniquifyIds(rows);

    return { url, rows, prev: withPageQuery(root, fresh.dataset.prev), next: withPageQuery(root, fresh.dataset.next) };
}

// Livewire keeps its filters (#[Url] properties) in the address bar but not in its paginators' links, so a batch
// fetched from those links would come back unfiltered. Carry over whatever the link doesn't set itself.
function withPageQuery(root, url) {
    if (!url || !inLivewire(root)) {
        return url ?? null;
    }
    const merged = new URL(url, window.location.href);
    const own = new Set(merged.searchParams.keys());
    new URLSearchParams(window.location.search).forEach((value, name) => {
        if (!own.has(name)) {
            merged.searchParams.append(name, value);
        }
    });

    return merged.href;
}

function setStatus(root) {
    const state = windows.get(root);
    const status = root.querySelector('[data-table-window-status]');
    if (!status) {
        return;
    }
    const atEnd = !state.batches.at(-1).next;
    const atStart = !state.batches[0].prev;
    const scroller = root.querySelector('[data-table-scroll]');
    const scrollable = scroller.scrollHeight - scroller.clientHeight > 1;
    status.textContent = state.loading
        ? 'Loading more rows…'
        : atEnd && atStart ? 'Showing every row.' : atEnd ? 'That’s the end of the list.' : scrollable ? 'Scroll inside the table for more rows.' : '';
}

// Keep the scroll inside the table while there is more to load in that direction; once the first row of
// the first page (or the last row of the last page) is reached, let the scroll carry on to the page.
// overscroll-behavior only matters at an edge, so it's decided by the edge the table is sitting at.
function syncOverscroll(root) {
    const state = windows.get(root);
    const scroller = root.querySelector('[data-table-scroll]');
    const atTop = scroller.scrollTop <= 1;
    const atBottom = scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - 1;
    const release = (atTop && !state.batches[0].prev) || (atBottom && !state.batches.at(-1).next);
    scroller.style.overscrollBehaviorY = release ? 'auto' : 'contain';
}

// Point the URL at the batch at the top of the view, so a reload or a shared link reopens there.
function syncUrl(root) {
    const state = windows.get(root);
    const scroller = root.querySelector('[data-table-scroll]');
    const headerHeight = root.querySelector('thead')?.offsetHeight ?? 0;
    const visible = [...state.batches].reverse().find((batch) => batch.rows[0].offsetTop - headerHeight <= scroller.scrollTop + 1) ?? state.batches[0];
    const url = visible.url && withOtherTables(visible.url, root);
    if (url && url !== window.location.href) {
        window.history.replaceState(window.history.state, '', url);
    }
}

async function loadBatch(root, direction) {
    const state = windows.get(root);
    const edge = direction === 'next' ? state.batches.at(-1) : state.batches[0];
    const url = edge[direction];
    if (!url || state.loading || Date.now() < state.retryAt[direction]) {
        return;
    }

    const scroller = root.querySelector('[data-table-scroll]');
    const tbody = root.querySelector('tbody');
    state.loading = direction;
    tbody.setAttribute('aria-busy', 'true');
    setStatus(root);
    const batch = batchFrom(await fetchTablePage(url), root, url);
    state.loading = null;
    // Swapped out while this was loading (a sort, Back): a detached table measures 0 everywhere, so it would
    // count as being at the bottom edge forever and fetch every remaining page. Or re-rendered by Livewire,
    // which starts the window again: this batch belongs to rows that are gone.
    if (!root.isConnected || windows.get(root) !== state) {
        return;
    }
    tbody.removeAttribute('aria-busy');

    if (!batch) {
        // Keep the rows, and don't hammer a dead connection on every scroll event.
        state.retryAt[direction] = Date.now() + RETRY_AFTER_MS;
        // At the very edge no further scroll event fires, so retry on our own if the user is still there.
        setTimeout(() => checkEdges(root), RETRY_AFTER_MS + 50);
        const message = 'Couldn’t load more rows. Check your connection; it will retry automatically.';
        window.toast ? window.toast.error(message, { title: 'Connection problem' }) : window.alert(message);
        announce(message);
        setStatus(root);

        return;
    }

    // A batch is only dropped if the view stays clear of the far edge afterwards. Otherwise, when the window is
    // tall next to the batches (many visible rows, few per page), dropping at the top lands the view in the top
    // edge, which loads a batch there and drops one at the bottom, and so on for ever. The window keeps the extra
    // batch instead, and a later load trims it once there's room.
    const batchHeight = (b) => b.rows.reduce((sum, row) => sum + row.getBoundingClientRect().height, 0);
    restoreSelection(root, batch.rows);
    if (direction === 'next') {
        tbody.append(...batch.rows);
        state.batches.push(batch);
        while (state.batches.length > MAX_BATCHES && scroller.scrollTop - batchHeight(state.batches[0]) > EDGE_PX) {
            // Removing rows above the view shifts everything up by their height; scroll up by the same amount.
            const dropped = state.batches.shift();
            const height = batchHeight(dropped);
            releaseFocus(dropped.rows, scroller);
            keepSelection(root, dropped.rows);
            dropped.rows.forEach((row) => row.remove());
            scroller.scrollTop -= height;
        }
    } else {
        // Rows added above the view push it down by their height; scroll down by the same amount.
        const before = scroller.scrollHeight;
        tbody.prepend(...batch.rows);
        state.batches.unshift(batch);
        scroller.scrollTop += scroller.scrollHeight - before;
        const belowView = () => scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight;
        while (state.batches.length > MAX_BATCHES && belowView() - batchHeight(state.batches.at(-1)) > EDGE_PX) {
            const dropped = state.batches.pop();
            releaseFocus(dropped.rows, scroller);
            keepSelection(root, dropped.rows);
            dropped.rows.forEach((row) => row.remove());
        }
    }

    linkRows(root);
    syncSelection(root);
    setStatus(root);
    syncUrl(root);
    // Its size never changes (so ResizeObserver stays quiet), but it may only now overflow: re-check focusability.
    syncScrollable(scroller);
    syncThumb(root);
    syncOverscroll(root);
    // A fast scroll can still be near an edge after one batch; keep going.
    checkEdges(root);
}

// If a removed row had keyboard focus, hand focus to the scroll area rather than losing it to <body>.
function releaseFocus(rows, scroller) {
    if (rows.some((row) => row.contains(document.activeElement))) {
        scroller.focus({ preventScroll: true });
    }
}

function checkEdges(root) {
    // A retry timer or a finished load can outlive the table it was for; see loadBatch.
    if (!root.isConnected) {
        return;
    }
    const scroller = root.querySelector('[data-table-scroll]');
    if (scroller.scrollTop + scroller.clientHeight >= scroller.scrollHeight - EDGE_PX) {
        loadBatch(root, 'next');
    } else if (scroller.scrollTop <= EDGE_PX) {
        loadBatch(root, 'prev');
    }
}

// Overlay scrollbars for every table that scrolls inside itself (infinite, or max-height): the native bars are
// hidden, since the vertical one would run beside the sticky header too. The vertical thumb spans the scroll area
// below the header and mirrors scrollTop; the horizontal one sits along the bottom edge and mirrors scrollLeft.
const MIN_THUMB_PX = 24;
const THUMB_INSET_PX = 2;

function syncThumb(root) {
    const scroller = root.querySelector('[data-table-scroll]');
    const thumb = root.querySelector('[data-table-thumb]');
    if (!thumb || !scroller) {
        return;
    }
    const header = root.querySelector('thead')?.offsetHeight ?? 0;
    const range = scroller.scrollHeight - scroller.clientHeight;
    if (range <= 1) {
        thumb.hidden = true;
    } else {
        const track = scroller.clientHeight - header;
        const size = Math.max(MIN_THUMB_PX, track * (track / (scroller.scrollHeight - header)));
        const offset = (scroller.scrollTop / range) * (track - size);
        thumb.hidden = false;
        thumb.style.top = `${scroller.offsetTop + header + offset}px`;
        thumb.style.height = `${size}px`;
        thumb.dataset.ratio = String(range / Math.max(1, track - size));
    }

    const across = root.querySelector('[data-table-thumb-x]');
    if (!across) {
        return;
    }
    const rangeX = scroller.scrollWidth - scroller.clientWidth;
    if (rangeX <= 1) {
        across.hidden = true;

        return;
    }
    // scrollLeft runs from 0 to -range in right-to-left pages; the thumb still moves with the content.
    const rtl = getComputedStyle(scroller).direction === 'rtl';
    const trackX = scroller.clientWidth;
    const sizeX = Math.max(MIN_THUMB_PX, trackX * (trackX / scroller.scrollWidth));
    const progress = Math.abs(scroller.scrollLeft) / rangeX;
    const offsetX = (rtl ? 1 - progress : progress) * (trackX - sizeX);
    across.hidden = false;
    across.style.top = `${scroller.offsetTop + scroller.clientHeight - across.offsetHeight - THUMB_INSET_PX}px`;
    across.style.left = `${scroller.offsetLeft + offsetX}px`;
    across.style.width = `${sizeX}px`;
    across.dataset.ratio = String((rtl ? -1 : 1) * rangeX / Math.max(1, trackX - sizeX));
}

function dragThumb(root, selector = '[data-table-thumb]', axis = 'y') {
    const thumb = root.querySelector(selector);
    const scroller = root.querySelector('[data-table-scroll]');
    if (!thumb) {
        return;
    }
    thumb.addEventListener('pointerdown', (event) => {
        event.preventDefault();
        thumb.setPointerCapture(event.pointerId);
        const start = axis === 'y' ? event.clientY : event.clientX;
        const startScroll = axis === 'y' ? scroller.scrollTop : scroller.scrollLeft;
        const ratio = Number(thumb.dataset.ratio || 1);
        const move = (e) => {
            const scrolled = startScroll + ((axis === 'y' ? e.clientY : e.clientX) - start) * ratio;
            axis === 'y' ? (scroller.scrollTop = scrolled) : (scroller.scrollLeft = scrolled);
        };
        const stop = () => {
            thumb.removeEventListener('pointermove', move);
            thumb.removeEventListener('pointerup', stop);
            thumb.removeEventListener('pointercancel', stop);
        };
        thumb.addEventListener('pointermove', move);
        thumb.addEventListener('pointerup', stop);
        thumb.addEventListener('pointercancel', stop);
    });
}

// Once per table root (a swapped-in table is a new root): keep the thumbs on the scroll position and the size.
const thumbed = new WeakSet();

function initThumb(root) {
    const scroller = root.querySelector('[data-table-scroll]');
    if (thumbed.has(root) || !scroller || !root.querySelector('[data-table-thumb]')) {
        return;
    }
    thumbed.add(root);
    let queued = false;
    scroller.addEventListener('scroll', () => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(() => {
                queued = false;
                syncThumb(root);
            });
        }
    }, { passive: true });
    dragThumb(root);
    dragThumb(root, '[data-table-thumb-x]', 'x');
    const resized = new ResizeObserver(() => syncThumb(root));
    resized.observe(scroller);
    resized.observe(scroller.querySelector('table'));
    syncThumb(root);
}

// Listeners go on once per table; the state is set afresh each time, since a Livewire render replaces the rows.
const windowed = new WeakSet();

function initWindow(root) {
    windows.set(root, {
        batches: [{ url: root.dataset.url, rows: dataRows(root.querySelector('tbody')), prev: withPageQuery(root, root.dataset.prev), next: withPageQuery(root, root.dataset.next) }],
        loading: null,
        retryAt: { next: 0, prev: 0 },
    });
    root.querySelector('[data-table-window-fallback]')?.setAttribute('hidden', '');
    root.querySelector('[data-table-window-status]')?.classList.remove('hidden');
    if (!windowed.has(root)) {
        windowed.add(root);
        listenToWindow(root);
    }
    syncOverscroll(root);
    setStatus(root);

    // Fill both sides straight away: below so there is something to scroll to, above so scrolling up
    // from a deep link works (at scrollTop 0 there is no scroll event to trigger it).
    loadBatch(root, 'next').then(() => loadBatch(root, 'prev'));
}

function listenToWindow(root) {
    const scroller = root.querySelector('[data-table-scroll]');
    let queued = false;
    let urlTimer = null;
    scroller.addEventListener('scroll', () => {
        if (!queued) {
            queued = true;
            requestAnimationFrame(() => {
                queued = false;
                syncOverscroll(root);
                checkEdges(root);
            });
        }
        clearTimeout(urlTimer);
        urlTimer = setTimeout(() => syncUrl(root), 200);
    }, { passive: true });

    // Pushing past the top or bottom fires no scroll event (the position can't change), but it is
    // exactly when a load is wanted: treat those attempts as edge checks too.
    const nudge = () => requestAnimationFrame(() => checkEdges(root));
    scroller.addEventListener('wheel', nudge, { passive: true });
    scroller.addEventListener('touchmove', nudge, { passive: true });
    scroller.addEventListener('keydown', nudge);

    // The thumbs are initThumb's (run from enhance); this keeps the window's own state in step.
    const sync = () => {
        syncOverscroll(root);
        setStatus(root);
    };
    // Also watch the table: rows expanding or collapsing change whether the body needs to scroll at all.
    const resized = new ResizeObserver(sync);
    resized.observe(scroller);
    resized.observe(scroller.querySelector('table'));
}

// Back online: retry wherever a windowed table is waiting at an edge.
window.addEventListener('online', () => {
    document.querySelectorAll('[data-table-root][data-infinite]').forEach((root) => {
        const state = windows.get(root);
        if (state) {
            state.retryAt = { next: 0, prev: 0 };
            checkEdges(root);
        }
    });
});

// --- Row selection ------------------------------------------------------------------------------------

// An infinite table drops rows that scroll far away. A ticked one leaves a hidden input with the same name and
// value in its place, so the bulk form still sends it and the count still counts it; the box is ticked again
// if its row comes back.
function keepSelection(root, rows) {
    for (const box of rows.flatMap((row) => [...row.querySelectorAll('[data-table-select]:checked')])) {
        const kept = Object.assign(document.createElement('input'), { type: 'hidden', name: box.name, value: box.value });
        kept.setAttribute('form', box.getAttribute('form') ?? '');
        kept.dataset.tableKept = '';
        root.append(kept);
    }
}

function restoreSelection(root, rows) {
    const kept = [...root.querySelectorAll('[data-table-kept]')];
    for (const box of rows.flatMap((row) => [...row.querySelectorAll('[data-table-select]')])) {
        const match = kept.find((input) => input.isConnected && input.value === box.value && input.name === box.name);
        if (match) {
            box.checked = true;
            match.remove();
        }
    }
}

const lastCount = new WeakMap();

// Checkboxes, the select-all box (checked, half-checked or clear) and the bulk bar, all from what's ticked.
// announceCount: after the user ticked or cleared something, say the new count; the label sits in a bar that
// has only just appeared, and screen readers don't read a live region they didn't see arrive.
function syncSelection(root, { announceCount = false } = {}) {
    const boxes = [...root.querySelectorAll('[data-table-select]')];
    // One in the header, and one above the cards when a stacked table is on a phone.
    const alls = root.querySelectorAll('[data-table-select-all]');
    const bar = root.querySelector('[data-table-bulk]');
    if (!bar) {
        return;
    }
    const ticked = boxes.filter((box) => box.checked).length;
    const model = modelSelection(root);
    const onPage = new Set(boxes.map((box) => box.value));
    const elsewhere = model ? new Set(model.filter((value) => !onPage.has(value))).size : 0;
    const count = ticked + (model ? elsewhere : root.querySelectorAll('[data-table-kept]').length);
    alls.forEach((all) => {
        all.checked = boxes.length > 0 && ticked === boxes.length;
        all.indeterminate = count > 0 && !all.checked;
    });
    bar.hidden = count === 0;
    const text = elsewhere ? `${count} selected (${elsewhere} on other pages)` : `${count} selected`;
    const label = root.querySelector('[data-table-selected-count]');
    if (label) {
        label.textContent = text;
    }
    if (announceCount && lastCount.get(root) !== text) {
        announce(count === 0 ? 'Selection cleared' : text);
    }
    lastCount.set(root, text);
}

// With select-model the selection is the Livewire property, which keeps ticks from other pages too. The count
// comes from it, so it always says what a bulk action will get; null without one.
function modelSelection(root) {
    const name = root.dataset.selectModel;
    const value = name && inLivewire(root) ? wireFor(root)?.$get(name) : undefined;

    return Array.isArray(value) ? [...value].map(String) : null;
}

// Clear, and unticking select-all, clear the rows on other pages too, as they do out-of-view rows without Livewire.
function clearModel(root) {
    const name = root.dataset.selectModel;
    if (name && modelSelection(root)) {
        wireFor(root).$set(name, [], false);
    }
}

// Returns the boxes that changed.
function setBoxes(root, checked) {
    return [...root.querySelectorAll('[data-table-select]')].filter((box) => {
        const changed = box.checked !== checked;
        box.checked = checked;

        return changed;
    });
}

// Setting .checked fires no event, so wire:model (select-model) and any script of yours would never hear of it.
// Fired once every box is set. wire:model takes them in one at a time, so the count waits for the last (notifying).
let notifying = false;

function notifyChanged(boxes) {
    notifying = true;
    try {
        boxes.forEach((box) => box.dispatchEvent(new Event('change', { bubbles: true })));
    } finally {
        notifying = false;
    }
}

document.addEventListener('change', (event) => {
    const root = event.target.closest?.('[data-table-root]');
    if (!root || notifying) {
        return;
    }
    if (event.target.matches('[data-table-select-all]')) {
        const changed = setBoxes(root, event.target.checked);
        notifyChanged(changed);
        // Clearing all clears the rows out of view too, not just the ones on screen.
        if (!event.target.checked) {
            root.querySelectorAll('[data-table-kept]').forEach((input) => input.remove());
            clearModel(root);
        }
    }
    if (event.target.matches('[data-table-select-all], [data-table-select]')) {
        syncSelection(root, { announceCount: true });
    }
});

document.addEventListener('click', (event) => {
    const clear = event.target.closest?.('[data-table-clear]');
    const root = clear?.closest('[data-table-root]');
    if (root) {
        const changed = setBoxes(root, false);
        root.querySelectorAll('[data-table-kept]').forEach((input) => input.remove());
        notifyChanged(changed);
        clearModel(root);
        syncSelection(root, { announceCount: true });
        [...root.querySelectorAll('[data-table-select-all]')].find((all) => all.offsetParent !== null)?.focus();
    }
});

// --- Stacked cards on phones --------------------------------------------------------------------------

// Each cell shows its column name before the value (CSS reads data-label). Cells you labelled yourself keep theirs.
function labelCells(root) {
    const headers = [...root.querySelectorAll('thead th')].map((th) => th.dataset.label ?? '');
    // The totals rows too, which are yours to write.
    root.querySelectorAll('tbody tr[data-table-row], tfoot tr').forEach((row) => {
        [...row.children].forEach((cell, i) => {
            if (!cell.hasAttribute('data-label')) {
                cell.dataset.label = headers[i] ?? '';
            }
        });
    });
}

const stackedRows = new MutationObserver((records) => {
    new Set(records.map((record) => record.target.closest('[data-table-root][data-stack]')).filter(Boolean)).forEach(labelCells);
});

// A row with href is clicked as a whole, but a <tr> can't be a link: screen readers would call it a row and not
// say where it goes. So each gets a real <a> in its first cell (not the checkbox cell), visually hidden, as its
// Tab stop; Enter, Ctrl+Enter and the context menu then work as on any link. The row draws the focus ring.
function linkRows(root) {
    root.querySelectorAll('tr[data-href]:not([data-row-linked])').forEach((row) => {
        const cell = [...row.children].find((td) => !td.hasAttribute('data-no-row-click'));
        if (!cell) {
            return;
        }
        const link = Object.assign(document.createElement('a'), {
            href: row.dataset.href,
            className: 'sr-only',
            textContent: row.dataset.linkLabel || cell.textContent.trim().replace(/\s+/g, ' ') || 'Open',
        });
        link.dataset.rowLink = '';
        cell.prepend(link);
        row.dataset.rowLinked = '';
    });
}

// Runs on load, on every table swapped in by sorting or paging, and after every Livewire render.
function enhance(root) {
    initThumb(root);
    linkRows(root);
    syncSelection(root);
    applyColumns(root);
    if (root.hasAttribute('data-stack')) {
        labelCells(root);
        const tbody = root.querySelector('tbody');
        if (tbody) {
            stackedRows.observe(tbody, { childList: true });
        }
    }
}

// --- Columns menu (hideable columns) ------------------------------------------------------------------

// Which columns someone hid, by table id, for this visit only: nothing is stored, so a reload shows the defaults.
// Positions (the checkbox column counts), which stay the same across pages, sorts and filters.
const hiddenColumns = new Map();

// The menu's ticks set data-hide on the table (CSS in table/index hides those cells). Run on load and after every
// swap or Livewire render, which bring the server's defaults back.
function applyColumns(root) {
    const menu = root.id ? document.getElementById(`${root.id}-columns`) : null;
    const table = root.querySelector('table');
    if (!menu || !table) {
        return;
    }
    const boxes = [...menu.querySelectorAll('input[data-table-column]')];
    const chosen = hiddenColumns.get(root.id);
    if (chosen) {
        boxes.forEach((box) => {
            box.checked = !chosen.has(box.dataset.tableColumn);
        });
    }
    const hidden = boxes.filter((box) => !box.checked).map((box) => `c${box.dataset.tableColumn}`);
    if (hidden.length) {
        table.dataset.hide = hidden.join(' ');
    } else {
        table.removeAttribute('data-hide');
    }
    // At least one column stays: with no fixed column, the last ticked box can't be unticked.
    const shown = boxes.filter((box) => box.checked);
    boxes.forEach((box) => {
        box.disabled = Number(menu.dataset.fixed) === 0 && shown.length === 1 && box.checked;
    });
    // The table's width changed: sideways scrolling and the scrollbars may have too.
    root.querySelectorAll('[data-table-scroll]').forEach(syncScrollable);
    syncThumb(root);
}

document.addEventListener('change', (event) => {
    const box = event.target.closest?.('input[data-table-column]');
    const menu = box?.closest('[data-table-columns]');
    const root = menu?.closest('[data-table-root][id]');
    if (!root) {
        return;
    }
    hiddenColumns.set(root.id, new Set([...menu.querySelectorAll('input[data-table-column]')].filter((input) => !input.checked).map((input) => input.dataset.tableColumn)));
    applyColumns(root);
});

// The menu is a popover (top layer, fixed), placed under its button, its right edge on the button's, and kept there
// while the page scrolls or resizes; beforetoggle doesn't bubble, so it's caught on the way down.
function placeColumnsMenu(menu) {
    const button = document.querySelector(`[popovertarget="${CSS.escape(menu.id)}"]`);
    if (!button) {
        return;
    }
    const anchor = button.getBoundingClientRect();
    const left = Math.max(8, Math.min(anchor.right - menu.offsetWidth, window.innerWidth - menu.offsetWidth - 8));
    const below = anchor.bottom + 8;
    const top = below + menu.offsetHeight > window.innerHeight - 8 ? Math.max(8, anchor.top - menu.offsetHeight - 8) : below;
    menu.style.inset = 'auto';
    menu.style.left = `${left}px`;
    menu.style.top = `${top}px`;
    // Invisible until here (see table/index), so it never flashes where the browser first puts it.
    menu.dataset.placed = '';
}

const placing = new WeakMap();

document.addEventListener('toggle', (event) => {
    const menu = event.target;
    if (!(menu instanceof HTMLElement) || !menu.matches('[data-table-columns]')) {
        return;
    }
    const previous = placing.get(menu);
    if (previous) {
        window.removeEventListener('scroll', previous, true);
        window.removeEventListener('resize', previous);
        placing.delete(menu);
        delete menu.dataset.placed;
    }
    if (event.newState === 'open') {
        const place = () => placeColumnsMenu(menu);
        placing.set(menu, place);
        place();
        window.addEventListener('scroll', place, true);
        window.addEventListener('resize', place);
        menu.querySelector('input:not(:disabled)')?.focus();
    }
}, true);

const ready = new WeakSet();

// rerender: Livewire has just morphed the table, which undid this script's changes to it; do them again.
function setUp(root, { rerender = false } = {}) {
    if (ready.has(root) && !rerender) {
        return;
    }
    ready.add(root);
    if (rerender) {
        restoreRowState(root);
    }
    watchScrollables(root);
    enhance(root);
    if (root.hasAttribute('data-infinite')) {
        initWindow(root);
    }
}

document.querySelectorAll('[data-table-root]').forEach((root) => setUp(root));

// --- Livewire renders ---------------------------------------------------------------------------------

// A morph brings each table back to the server's markup: rows keep their checkbox elements but maybe not their
// values (without wire:key, rows are matched by position), open rows close, and the rows an infinite table
// loaded itself go. So the ticked values and open rows are noted before it and put back after.
const saved = new WeakMap();
const modelled = (box) => [...box.attributes].some((attribute) => attribute.name.startsWith('wire:model'));
// wire:key when the row has one, since it names the record; otherwise what the row links to or submits.
const rowKey = (row) => row.getAttribute('wire:key') ?? row.dataset.href ?? row.querySelector('[data-table-select]')?.value ?? null;

function saveRowState(root) {
    // A new page or order: nothing carries over, and reused checkboxes mustn't stay ticked on other rows.
    if (resetting.has(root)) {
        saved.set(root, { ticked: [], open: new Set() });

        return;
    }
    const boxes = [...root.querySelectorAll('[data-table-select]')].filter((box) => !modelled(box));
    const ticked = [
        ...boxes.filter((box) => box.checked).map((box) => ({ value: box.value, name: box.name, form: box.getAttribute('form') })),
        ...[...root.querySelectorAll('[data-table-kept]')].map((input) => ({ value: input.value, name: input.name, form: input.getAttribute('form') })),
    ];
    const open = new Set([...root.querySelectorAll('tr[data-expandable][data-expanded]')].map(rowKey).filter(Boolean));
    saved.set(root, { ticked, open });
}

function restoreRowState(root) {
    const state = saved.get(root);
    saved.delete(root);
    if (!state) {
        return;
    }
    const values = new Set(state.ticked.map((box) => box.value));
    const shown = new Set();
    root.querySelectorAll('[data-table-select]').forEach((box) => {
        if (!modelled(box)) {
            box.checked = values.has(box.value);
            shown.add(box.value);
        }
    });
    // An infinite table is about to load its neighbouring rows again; ticked ones among them are ticked again
    // from these (restoreSelection), as when they scroll back into view.
    if (root.hasAttribute('data-infinite')) {
        state.ticked.filter((box) => !shown.has(box.value)).forEach((box) => {
            const kept = Object.assign(document.createElement('input'), { type: 'hidden', name: box.name, value: box.value });
            kept.setAttribute('form', box.form ?? '');
            kept.dataset.tableKept = '';
            root.append(kept);
        });
    }
    // Open straight away, without the expand transition: to the user the row never closed.
    root.querySelectorAll('tr[data-expandable]').forEach((row) => {
        const button = row.querySelector('[data-row-toggle]');
        const details = document.getElementById(button?.getAttribute('aria-controls'));
        if (details && state.open.has(rowKey(row))) {
            button.setAttribute('aria-expanded', 'true');
            row.toggleAttribute('data-expanded', true);
            details.hidden = false;
            details.inert = false;
            details.setAttribute('data-open', '');
        }
    });
}

// Only the component's own tables: a child component's are left alone by its parent's morph.
const tablesOf = (component) => [...component.querySelectorAll('[data-table-root]')].filter((root) => root.closest('[wire\\:id]') === component);
// Livewire 4 islands morph just the part of the component between two markers.
const tablesBetween = (start, end, component) => tablesOf(component).filter((root) => (start.compareDocumentPosition(root) & Node.DOCUMENT_POSITION_FOLLOWING) && (end.compareDocumentPosition(root) & Node.DOCUMENT_POSITION_PRECEDING));

function hookIntoLivewire(Livewire) {
    Livewire.hook('morph', ({ el }) => tablesOf(el).forEach(saveRowState));
    Livewire.hook('morphed', ({ el }) => tablesOf(el).forEach((root) => setUp(root, { rerender: true })));
    Livewire.hook('island.morph', ({ startNode, endNode, component }) => tablesBetween(startNode, endNode, component.el).forEach(saveRowState));
    Livewire.hook('island.morphed', ({ startNode, endNode, component }) => tablesBetween(startNode, endNode, component.el).forEach((root) => setUp(root, { rerender: true })));
}

// None of this runs on a page without Livewire.
if (window.Livewire) {
    hookIntoLivewire(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => hookIntoLivewire(window.Livewire));
}
// wire:navigate swaps the page without reloading, so this file doesn't run again for the new page's tables.
document.addEventListener('livewire:navigated', () => document.querySelectorAll('[data-table-root]').forEach((root) => setUp(root)));
