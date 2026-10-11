// Drives <x-widget.tabs>: switching panels, the arrow keys, keeping the open tab in the URL (remember) and in a hidden
// input (a form, wire:model). The server already rendered the open tab and hid the rest, so this only switches them.
// Delegated from `document`, so tabs added later (a Livewire render, fetched HTML) work without setting up.
// Event: tabs:change on the tabs, detail { tab } (its key), when the open tab changes.

const ROOT = '[data-tabs]';
const TAB = '[role="tab"]';

const tabsOf = (root) => [...root.querySelector('[data-tabs-list]')?.querySelectorAll(TAB) ?? []];
const usable = (tabs) => tabs.filter((tab) => tab.getAttribute('aria-disabled') !== 'true');

// --- Remember: #settings=security, several sets joined with & ------------------------------------------------

function hashPairs() {
    const pairs = new Map();
    for (const part of location.hash.slice(1).split('&')) {
        const at = part.indexOf('=');
        if (at > 0) {
            pairs.set(decodeURIComponent(part.slice(0, at)), decodeURIComponent(part.slice(at + 1)));
        }
    }

    return pairs;
}

function rememberTab(root, key) {
    const pairs = hashPairs();
    pairs.set(root.dataset.tabs, key);
    const hash = [...pairs].map(([id, tab]) => `${encodeURIComponent(id)}=${encodeURIComponent(tab)}`).join('&');
    // replaceState, not location.hash: no extra Back step per click, and no jump to an element with that id.
    history.replaceState(history.state, '', `${location.pathname}${location.search}#${hash}`);
}

// A long row of tabs scrolls sideways: keep the open one in view, without scrolling the page. Instantly on load, so a
// tab the server opened (value, remember) isn't left out of sight.
function reveal(tab, behavior = 'instant') {
    const strip = tab.closest('[data-tabs-list]')?.parentElement;
    if (!strip || strip.scrollWidth <= strip.clientWidth) {
        return;
    }
    const left = tab.getBoundingClientRect().left - strip.getBoundingClientRect().left + strip.scrollLeft;
    if (left < strip.scrollLeft || left + tab.offsetWidth > strip.scrollLeft + strip.clientWidth) {
        strip.scrollTo({ left: left - (strip.clientWidth - tab.offsetWidth) / 2, behavior });
    }
}

const revealOpen = (scope) => scope.querySelectorAll(`${ROOT} [data-tabs-list] ${TAB}[aria-selected="true"]`).forEach((tab) => reveal(tab));

// --- Switching ------------------------------------------------------------------------------------------

function activate(root, tab, { focus = false, remember = true } = {}) {
    if (!tab || tab.getAttribute('aria-disabled') === 'true') {
        return;
    }
    const key = tab.dataset.tab;
    const changed = tab.getAttribute('aria-selected') !== 'true';
    for (const other of tabsOf(root)) {
        const open = other === tab;
        other.setAttribute('aria-selected', String(open));
        other.tabIndex = open ? 0 : -1;
    }
    root.querySelectorAll(':scope > div > [role="tabpanel"]').forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== key;
    });
    if (focus) {
        tab.focus();
    }
    reveal(tab, 'smooth');
    if (!changed) {
        return;
    }
    const input = root.querySelector(':scope > [data-tabs-input]');
    if (input && input.value !== key) {
        input.value = key;
        // Both: Alpine x-model and Livewire wire:model listen for `input`, plain forms for `change`.
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    if (remember && 'tabsRemember' in root.dataset) {
        rememberTab(root, key);
    }
    root.dispatchEvent(new CustomEvent('tabs:change', { bubbles: true, detail: { tab: key } }));
}

document.addEventListener('click', (event) => {
    const tab = event.target.closest?.(`${ROOT} ${TAB}`);
    if (tab) {
        activate(tab.closest(ROOT), tab);
    }
});

// Automatic activation: moving to a tab opens it, which the pattern recommends when the panels are already on the page.
document.addEventListener('keydown', (event) => {
    const tab = event.target.closest?.(`${ROOT} ${TAB}`);
    if (!tab) {
        return;
    }
    const root = tab.closest(ROOT);
    const list = tab.closest('[role="tablist"]');
    const vertical = list.getAttribute('aria-orientation') === 'vertical';
    const rtl = getComputedStyle(list).direction === 'rtl';
    const tabs = usable(tabsOf(root));
    const index = tabs.indexOf(tab);
    const next = vertical ? 'ArrowDown' : rtl ? 'ArrowLeft' : 'ArrowRight';
    const previous = vertical ? 'ArrowUp' : rtl ? 'ArrowRight' : 'ArrowLeft';
    const target = {
        [next]: tabs[(index + 1) % tabs.length],
        [previous]: tabs[(index - 1 + tabs.length) % tabs.length],
        Home: tabs[0],
        End: tabs.at(-1),
    }[event.key];
    if (target) {
        event.preventDefault();
        activate(root, target, { focus: true });
    }
});

// --- Opening the remembered tab ---------------------------------------------------------------------------

function openFromHash(scope = document) {
    const pairs = hashPairs();
    scope.querySelectorAll(`${ROOT}[data-tabs-remember]`).forEach((root) => {
        const key = pairs.get(root.dataset.tabs);
        const tab = key === undefined ? null : tabsOf(root).find((candidate) => candidate.dataset.tab === key);
        if (tab) {
            activate(root, tab, { remember: false });
        }
    });
}

openFromHash();
revealOpen(document);
window.addEventListener('hashchange', () => openFromHash());

// Tabs added to the page later (a Livewire render or wire:navigate, fetched HTML) open their remembered tab and show
// the open one too.
new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element && (node.matches(ROOT) || node.querySelector(ROOT))) {
            openFromHash(node.parentElement ?? document);
            revealOpen(node.parentElement ?? document);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

export const tabs = {
    // tabs.open('settings', 'security'): switch from your own script, by the tabs' id or any element of them.
    open(target, key) {
        const root = typeof target === 'string' ? document.querySelector(`${ROOT}[data-tabs="${CSS.escape(target)}"]`) : target?.closest?.(ROOT);
        const tab = root && tabsOf(root).find((candidate) => candidate.dataset.tab === key);
        if (!tab) {
            console.warn(root ? `Bladewell tabs: no tab "${key}" in` : 'Bladewell tabs: no tabs on this page for', root ?? target);

            return;
        }
        activate(root, tab);
    },
};

// Global, as modal and toast are, so a script on the page can call it without an import.
window.tabs = tabs;
