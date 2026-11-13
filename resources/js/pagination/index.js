// Drawer: builds the page list the first time its sheet opens. The server only sends
// the URL of page 1 and the page count, so the HTML stays a few KB however many pages there are.

const LINK = 'text-foreground focus-visible:ring-primary block w-full rounded-xl p-3 text-center tabular-nums outline-none focus-visible:ring-2 focus-visible:ring-inset';

// Up to this many pages, list them all. Beyond it, building every link froze the sheet (100,000 pages:
// ~1.9 s), so list a window around the current page plus both ends; the first/last buttons and the
// previous/next arrows (or the numbers/jump styles, better suited to huge tables) reach the rest.
const LIST_ALL_UP_TO = 2000;
const WINDOW = 100;
const ENDS = 3;

function pagesToList(current, last) {
    if (last <= LIST_ALL_UP_TO) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }
    const pages = new Set();
    const add = (from, to) => {
        for (let page = Math.max(1, from); page <= Math.min(last, to); page++) {
            pages.add(page);
        }
    };
    add(1, ENDS);
    add(current - WINDOW, current + WINDOW);
    add(last - ENDS + 1, last);

    return [...pages].sort((a, b) => a - b);
}

function build(list) {
    const { pageName, url } = list.dataset;
    const current = Number(list.dataset.current);
    const last = Number(list.dataset.last);
    // Page 1's URL already carries the other query params and the #fragment; only the page changes.
    const base = new URL(url, window.location.href);
    const items = document.createDocumentFragment();
    let previous = 0;

    for (const page of pagesToList(current, last)) {
        if (page > previous + 1) {
            // A gap in the windowed list; purely visual, the numbers around it say which pages are skipped.
            const gap = Object.assign(document.createElement('li'), { textContent: '…', className: 'text-muted p-1 text-center select-none' });
            gap.setAttribute('aria-hidden', 'true');
            items.append(gap);
        }
        previous = page;
        const href = new URL(base);
        href.searchParams.set(pageName, String(page));

        const link = document.createElement('a');
        link.href = href.href;
        link.textContent = String(page);
        link.className = page === current ? `${LINK} bg-field font-semibold` : `${LINK} hover:bg-field/60`;
        if (page === current) {
            link.setAttribute('aria-current', 'page');
            // Picked up by the modal script: focuses it and scrolls it into view.
            link.dataset.autofocus = '';
        }

        const item = document.createElement('li');
        item.append(link);
        items.append(item);
    }

    list.replaceChildren(items);
    list.dataset.built = '';
}

// `modal:before-open` doesn't bubble, so listen in the capture phase.
document.addEventListener('modal:before-open', (event) => {
    const list = event.target.querySelector?.('[data-page-list]:not([data-built])');
    if (list) {
        build(list);
    }
}, true);

// A Livewire render empties the list (the server sends it empty): refill it in a sheet that is open.
const refill = (Livewire) => Livewire.hook('morphed', () => {
    document.querySelectorAll('dialog[open] [data-page-list]:not([data-built])').forEach(build);
});
if (window.Livewire) {
    refill(window.Livewire);
} else {
    document.addEventListener('livewire:init', () => refill(window.Livewire));
}

// --- Table footer: rows per page ------------------------------------------------------------------

// Submits as soon as a size is picked; the Apply button is only there for when JavaScript isn't (see footer.blade.php).
document.addEventListener('change', (event) => {
    const form = event.target.closest?.('form[data-per-page]:not([data-per-page-model])');
    if (form) {
        form.requestSubmit();
    }
});

// --- In a Livewire component ---------------------------------------------------------------------------

// There the component owns the page (WithPagination). A page link would reload everything, and the component would
// start over; instead the links, the page sheet and the jump form call gotoPage(). Not in a table, which does the
// same for its own pagination (resources/js/widget/table).
const wireFor = (element) => {
    const host = element.closest('[wire\\:id]');

    return host ? window.Livewire?.find(host.getAttribute('wire:id')) : null;
};
const paginated = (wire) => wire?.$get('paginators') !== undefined;

// One polite live region, as the table has: a region inside the pagination would be replaced along with it.
function announce(message) {
    let region = document.getElementById('pagination-announcer');
    if (!region) {
        region = Object.assign(document.createElement('p'), { id: 'pagination-announcer', className: 'sr-only' });
        region.setAttribute('role', 'status');
        document.body.append(region);
    }
    region.textContent = '';
    setTimeout(() => {
        region.textContent = message;
    }, 50);
}

async function gotoPage(wire, root, page, pageName) {
    root.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
    document.querySelectorAll(`dialog[open] [data-page-list][data-page-name="${CSS.escape(pageName)}"]`).forEach((list) => list.closest('dialog').close());
    await wire.$call('gotoPage', page, pageName);
    // The link that was clicked may be gone (the new page is plain text); land on the pagination instead.
    const fresh = root.isConnected ? root : null;
    if (fresh) {
        fresh.tabIndex = -1;
        fresh.focus({ preventScroll: true });
    }
    announce(page ? `Page ${page} loaded` : 'Page loaded');
}

const inTable = (element) => element.closest('[data-table-root]') !== null;

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    // The page sheet lives in a <dialog> beside the pagination; its list names the page parameter itself.
    const list = link.closest('[data-page-list]');
    const root = link.closest('[data-pagination]') ?? list?.closest('dialog')?.previousElementSibling?.closest('[data-pagination]');
    const pageName = root?.dataset.pageName ?? list?.dataset.pageName;
    if (!root || !pageName || inTable(link) || link.matches('[data-load-more-link]')) {
        return;
    }
    const wire = wireFor(link);
    const target = new URL(link.href, window.location.href);
    if (!paginated(wire) || target.origin !== window.location.origin) {
        return;
    }
    event.preventDefault();
    gotoPage(wire, root, target.searchParams.get(pageName) ?? '1', pageName);
});

// The jump form ("go to page"). The rows-per-page form is left alone: its select binds with per-page-model.
document.addEventListener('submit', (event) => {
    const form = event.target;
    const root = form.closest?.('[data-pagination]');
    if (!root || form.matches('[data-per-page]') || inTable(form)) {
        return;
    }
    const wire = wireFor(form);
    const page = new FormData(form).get(root.dataset.pageName);
    if (!paginated(wire) || !page) {
        return;
    }
    event.preventDefault();
    gotoPage(wire, root, String(page), root.dataset.pageName);
});

// --- Load more ------------------------------------------------------------------------------------

// Each load adds the page size the list started with: the paginator's own grows with every load.
const loadSteps = new Map();

async function moreFromLivewire(wire, block, list, url) {
    const { perPageModel, perPage, pageName } = block.dataset;
    const before = list ? list.children.length : 0;
    if (perPageModel && wire.$get(perPageModel) !== undefined) {
        const key = `${wire.$id}:${perPageModel}`;
        if (!loadSteps.has(key)) {
            loadSteps.set(key, Number(perPage));
        }
        await wire.$set(perPageModel, Number(wire.$get(perPageModel)) + loadSteps.get(key));
    } else if (paginated(wire)) {
        await wire.$call('gotoPage', url.searchParams.get(pageName) ?? '2', pageName);
    } else {
        window.location.assign(url.href);

        return;
    }
    // As without Livewire: focus goes to the first new item, where the list grew.
    const first = list?.isConnected && perPageModel ? list.children[before] : null;
    if (first) {
        first.tabIndex = -1;
        first.focus({ preventScroll: true });
    }
}

// Fetches the next page, appends the items found in the block's target to the list on this page and
// swaps in the next page's block (new link, new count). Any failure leaves the link working as a link.
document.addEventListener('click', async (event) => {
    const link = event.target.closest?.('[data-load-more] a[data-load-more-link]');
    if (!link || link.hasAttribute('aria-busy') || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }
    const block = link.closest('[data-load-more]');
    const list = document.querySelector(block.dataset.target);
    const url = new URL(link.href, window.location.href);
    // In Livewire, items appended here would be gone at the component's next render: it must render them itself. With
    // per-page-model it gets one page more; without, it moves to the next page.
    const wire = wireFor(block);
    if (wire) {
        event.preventDefault();
        await moreFromLivewire(wire, block, list, url);

        return;
    }
    // Only same-origin HTML is parsed and moved into the page.
    if (!list || url.origin !== window.location.origin) {
        return;
    }
    event.preventDefault();

    const status = block.querySelector('[data-load-more-status]');
    link.setAttribute('aria-busy', 'true');
    status.textContent = '';

    try {
        const response = await fetch(url, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const next = new DOMParser().parseFromString(await response.text(), 'text/html');
        const items = [...(next.querySelector(block.dataset.target)?.children ?? [])];
        const nextBlock = next.getElementById(block.id);

        list.append(...items.map((item) => document.importNode(item, true)));
        const added = items.length ? [...list.children].slice(-items.length) : [];
        if (nextBlock) {
            block.replaceWith(document.importNode(nextBlock, true));
        } else {
            block.remove();
        }
        // Move focus to the first new item, so keyboard and screen reader users carry on where the list grew.
        if (added[0]) {
            added[0].tabIndex = -1;
            added[0].focus({ preventScroll: true });
        }
    } catch {
        link.removeAttribute('aria-busy');
        status.textContent = "Couldn't load more. Try again.";
    }
});
