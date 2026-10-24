// Drives <x-widget.sidebar-layout>. On large screens: collapses the sidebar to a rail of icons and back, remembers that
// in a cookie if asked, names each icon beside the rail on hover or focus, and shows a group's links in a popover beside
// it. On smaller ones: slides the sidebar in over the page from the menu button, with the page inert behind it, and out
// again on Esc, the close button, a click on the page, or a link chosen. And the panel slot, from its own button: beside
// the page on a large screen, over it on a smaller one. Delegated from `document`, so a layout added later or
// re-rendered by Livewire works without setting up.

const ROOT = '[data-sidebar-layout]';
// Tailwind's lg, where the sidebar stops being a panel over the page and sits beside it.
const wide = window.matchMedia('(min-width: 64rem)');
const GAP = 10;
const FOCUSABLE = 'a[href], button:not([disabled]), summary, input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])';
const YEAR = 60 * 60 * 24 * 365;

const sidebarOf = (root) => root.querySelector(':scope > [data-sidebar]');
const contentOf = (root) => root.querySelector(':scope > [data-sidebar-content]');
const tipOf = (root) => root.querySelector(':scope > [data-sidebar-tip]');
const isOpen = (root) => root.hasAttribute('data-sidebar-open');
const isRail = (root) => wide.matches && root.hasAttribute('data-sidebar-collapsed');

function setCollapsed(root, collapsed) {
    root.toggleAttribute('data-sidebar-collapsed', collapsed);
    root.querySelectorAll('[data-sidebar-collapse]').forEach((button) => button.setAttribute('aria-expanded', String(!collapsed)));
    hideTip(root);
    hideFlyouts(root);
    const cookie = root.dataset.sidebarRemember;
    if (cookie) {
        document.cookie = `${encodeURIComponent(cookie)}=${collapsed ? 'collapsed' : 'expanded'}; path=/; max-age=${YEAR}; samesite=lax`;
    }
}

function open(root) {
    if (wide.matches || isOpen(root)) {
        return;
    }
    closePanel(root, { restoreFocus: false });
    root.setAttribute('data-sidebar-open', '');
    contentOf(root).inert = true;
    root.querySelectorAll('[data-sidebar-menu]').forEach((button) => button.setAttribute('aria-expanded', 'true'));
    // The current page's link if there is one, so a keyboard user starts where they are; otherwise the first thing.
    const sidebar = sidebarOf(root);
    const start = sidebar.querySelector('[aria-current="page"]') ?? sidebar.querySelector(`[data-accordion-menu] :is(${FOCUSABLE})`) ?? sidebar.querySelector(FOCUSABLE);
    start?.focus({ preventScroll: true });
}

function close(root, { restoreFocus = true } = {}) {
    if (!isOpen(root)) {
        return;
    }
    root.removeAttribute('data-sidebar-open');
    contentOf(root).inert = false;
    const button = root.querySelector('[data-sidebar-menu]');
    root.querySelectorAll('[data-sidebar-menu]').forEach((each) => each.setAttribute('aria-expanded', 'false'));
    if (restoreFocus) {
        button?.focus();
    }
}

// The panel slot (a chat, say), from its button at the end of the top bar. On a wide screen it sits beside the page,
// which can still be used; on a narrow one it slides over it, and the page is inert meanwhile, as with the sidebar.
// Only one of the two is over the page at a time.
const sidePanelOf = (root) => root.querySelector(':scope > [data-sidebar-panel]');
const isPanelOpen = (root) => root.hasAttribute('data-sidebar-panel-open');

function openPanel(root) {
    const panel = sidePanelOf(root);
    if (!panel || isPanelOpen(root)) {
        return;
    }
    close(root, { restoreFocus: false });
    hideTip(root);
    hideFlyouts(root);
    root.setAttribute('data-sidebar-panel-open', '');
    contentOf(root).inert = !wide.matches;
    root.querySelectorAll('[data-sidebar-panel-toggle]').forEach((button) => button.setAttribute('aria-expanded', 'true'));
    // Straight to where to type, if there's somewhere (a chat's box), so the person can start writing.
    (panel.querySelector('[autofocus], [data-chat-input], textarea, input:not([type="hidden"])') ?? panel.querySelector(FOCUSABLE))?.focus({ preventScroll: true });
}

function closePanel(root, { restoreFocus = true } = {}) {
    if (!isPanelOpen(root)) {
        return;
    }
    // Back to its button only from inside it: on a wide screen the person may have moved on to the page.
    const fromInside = sidePanelOf(root).contains(document.activeElement);
    root.removeAttribute('data-sidebar-panel-open');
    contentOf(root).inert = false;
    root.querySelectorAll('[data-sidebar-panel-toggle]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
    if (restoreFocus && fromInside) {
        root.querySelector('[data-sidebar-panel-toggle]')?.focus();
    }
}

// The name beside the rail. Positioned here, as the menu scrolls under it; aria-hidden, as the link is already named.
function showTip(root, target) {
    const tip = tipOf(root);
    const name = (target.querySelector('[data-menu-label]') ?? target).textContent.trim().replace(/\s+/g, ' ') || target.getAttribute('aria-label');
    if (!tip || !name) {
        return;
    }
    tip.textContent = name;
    if (!tip.matches(':popover-open')) {
        tip.showPopover();
    }
    const box = target.getBoundingClientRect();
    const rtl = getComputedStyle(target).direction === 'rtl';
    tip.style.top = `${Math.round(box.top + box.height / 2 - tip.offsetHeight / 2)}px`;
    tip.style.left = `${Math.round(rtl ? box.left - GAP - tip.offsetWidth : box.right + GAP)}px`;
}

function hideTip(root) {
    const tip = tipOf(root);
    if (tip?.matches(':popover-open')) {
        tip.hidePopover();
    }
}

// What in the rail has a tip: the menu's links and group headings, but not the links in a group's popover, which show
// their names, nor a heading whose popover is showing.
const tipTarget = (element) => {
    const target = element instanceof Element ? element.closest('[data-sidebar] [data-accordion-menu] :is(a[href], summary)') : null;
    const root = target?.closest(ROOT);
    if (!root || !isRail(root) || target.closest('[data-accordion-content]') || target.parentElement.querySelector(':scope > [data-sidebar-flyout]:popover-open')) {
        return [];
    }

    return [root, target];
};

// groups="popover": a group's links in a popover beside the rail. It's the group's own list (the accordion's
// [data-accordion-content]), made a popover only while it shows, so the links stay where they are in the Tab order,
// straight after their heading, and nothing is copied. popover="auto" closes it on Esc or a click elsewhere.
const panelOf = (details) => details.querySelector(':scope > [data-accordion-content]');

function hideFlyouts(root) {
    root.querySelectorAll('[data-sidebar-flyout]:popover-open').forEach((panel) => panel.hidePopover());
}

function showFlyout(root, details) {
    const panel = panelOf(details);
    const summary = details.querySelector(':scope > summary');
    hideTip(root);
    hideFlyouts(root);
    // Its links only show while the group is open: open a closed one for now, and put it back as it was after.
    if (!details.open) {
        details.open = true;
        panel.setAttribute('data-sidebar-flyout-reclose', '');
    }
    panel.popover = 'auto';
    panel.setAttribute('data-sidebar-flyout', '');
    panel.showPopover();

    // Beside the rail, clear of it by the tail, its title level with the heading, and moved up as far as it takes to fit
    // the window. The tail stays on the icon however far it moved, short of the rounded corners.
    const rail = sidebarOf(root).getBoundingClientRect();
    const box = summary.getBoundingClientRect();
    const rtl = getComputedStyle(summary).direction === 'rtl';
    const top = Math.round(Math.max(GAP, Math.min(box.top - 6, window.innerHeight - panel.offsetHeight - GAP)));
    panel.style.top = `${top}px`;
    panel.style.left = `${Math.round(rtl ? rail.left - GAP - panel.offsetWidth : rail.right + GAP)}px`;
    panel.style.setProperty('--tail-y', `${Math.round(Math.min(Math.max(box.top + box.height / 2 - top, 16), panel.offsetHeight - 16))}px`);
    // Room before the list's scrollbar, only where it has one that takes up room: a long list, where scrollbars always
    // show. Overlay scrollbars and a list that fits need none.
    const list = panel.querySelector(':scope > ul');
    list?.toggleAttribute('data-scrollbar', list.offsetWidth > list.clientWidth);

    (panel.querySelector('[aria-current="page"]') ?? panel.querySelector('a[href]'))?.focus({ preventScroll: true });
}

// Back to the group's plain list once it closes, however that happened. Focus goes back to the heading when it was in the
// list (Esc), not when the person moved it somewhere else.
document.addEventListener('toggle', (event) => {
    const panel = event.target;
    if (event.newState !== 'closed' || !(panel instanceof Element) || !panel.hasAttribute('data-sidebar-flyout')) {
        return;
    }
    const details = panel.parentElement;
    const hadFocus = panel.contains(document.activeElement) || document.activeElement === document.body;
    panel.removeAttribute('popover');
    panel.removeAttribute('data-sidebar-flyout');
    panel.style.top = '';
    panel.style.left = '';
    panel.style.removeProperty('--tail-y');
    panel.querySelector(':scope > ul')?.removeAttribute('data-scrollbar');
    if (panel.hasAttribute('data-sidebar-flyout-reclose')) {
        panel.removeAttribute('data-sidebar-flyout-reclose');
        details.open = false;
    }
    if (hadFocus) {
        details.querySelector(':scope > summary')?.focus({ preventScroll: true });
    }
}, true);

// A press on the heading of a showing popover closes it (light dismiss) before the click arrives, which would only open
// it again: note it here, so that click leaves it closed.
document.addEventListener('pointerdown', (event) => {
    const summary = event.target instanceof Element ? event.target.closest('[data-sidebar] details[data-accordion] > summary') : null;
    if (summary) {
        summary.toggleAttribute('data-sidebar-flyout-was-open', !!summary.parentElement.querySelector(':scope > [data-sidebar-flyout]:popover-open'));
    }
}, true);

// A group's icon in the rail: its popover, or with groups="expand" the sidebar again, with that group open. Ahead of the
// accordion's own click (capture), so it doesn't also open or close the group.
document.addEventListener('click', (event) => {
    const summary = event.target instanceof Element ? event.target.closest('[data-sidebar] details[data-accordion] > summary') : null;
    const root = summary?.closest(ROOT);
    if (!root || !isRail(root)) {
        return;
    }
    const details = summary.parentElement;
    if (root.dataset.sidebarGroups === 'expand') {
        setCollapsed(root, false);
        if (details.open && !details.hasAttribute('data-closing')) {
            event.preventDefault();
            event.stopPropagation();
        }

        return;
    }
    event.preventDefault();
    event.stopPropagation();
    const wasOpen = summary.hasAttribute('data-sidebar-flyout-was-open');
    summary.removeAttribute('data-sidebar-flyout-was-open');
    const panel = panelOf(details);
    if (panel.matches(':popover-open')) {
        panel.hidePopover();
    } else if (!wasOpen) {
        showFlyout(root, details);
    }
}, true);

// Tab past the last link (or anywhere outside it and its heading) closes the popover.
document.addEventListener('focusin', (event) => {
    document.querySelectorAll('[data-sidebar-flyout]:popover-open').forEach((panel) => {
        if (!panel.contains(event.target) && event.target !== panel.parentElement.querySelector(':scope > summary')) {
            panel.hidePopover();
        }
    });
});

document.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }
    const root = event.target.closest(ROOT);
    if (!root) {
        return;
    }
    if (event.target.closest('[data-sidebar-menu]')) {
        open(root);
    } else if (event.target.closest('[data-sidebar-panel-toggle]')) {
        if (isPanelOpen(root)) {
            closePanel(root);
        } else {
            openPanel(root);
        }
    } else if (event.target.closest('[data-sidebar-panel-close]')) {
        closePanel(root);
    } else if (event.target.closest('[data-sidebar-close]')) {
        // The menu's close button, or the dimmed page behind whichever of the two is open.
        close(root);
        closePanel(root);
    } else if (event.target.closest('[data-sidebar-collapse]')) {
        setCollapsed(root, !root.hasAttribute('data-sidebar-collapsed'));
    } else if (event.target.closest('[data-sidebar] a[href]')) {
        // A link chosen: the panel or a group's popover gets out of the way of the page (or the #section) it leads to.
        close(root, { restoreFocus: false });
        hideFlyouts(root);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !(event.target instanceof Element)) {
        return;
    }
    document.querySelectorAll(ROOT).forEach((root) => {
        hideTip(root);
        if (isOpen(root)) {
            event.preventDefault();
            close(root);
        } else if (isPanelOpen(root)) {
            event.preventDefault();
            closePanel(root);
        }
    });
});

// Mouse and pen only: a tap follows the link, and a name left showing after it would only be in the way.
document.addEventListener('pointerover', (event) => {
    if (event.pointerType === 'touch') {
        return;
    }
    const [root, target] = tipTarget(event.target);
    if (root) {
        showTip(root, target);
    }
});
document.addEventListener('pointerout', (event) => {
    const [root, target] = tipTarget(event.target);
    if (root && !target.contains(event.relatedTarget)) {
        hideTip(root);
    }
});
document.addEventListener('focusin', (event) => {
    const [root, target] = tipTarget(event.target);
    if (root && target.matches(':focus-visible')) {
        // Once the browser is done: focus can arrive while a popover is still closing (Esc in a group's popover hands
        // it back to the group's icon), and no other may open until then. Still there by then, it gets its name.
        setTimeout(() => {
            if (document.activeElement === target && tipTarget(target).length > 0) {
                showTip(root, target);
            }
        });
    }
});
document.addEventListener('focusout', (event) => {
    const [root] = tipTarget(event.target);
    if (root) {
        hideTip(root);
    }
});

// The top bar gets its line once the page scrolls under it. A tip or a group's popover would be left behind by anything
// scrolling: the menu, or the window where the layout sits in a page rather than filling it. Not by the list inside a popover:
// scrolling it is how someone reaches its last links.
document.addEventListener('scroll', (event) => {
    if (event.target instanceof Element && event.target.matches('[data-sidebar-scroll]')) {
        event.target.querySelector(':scope > [data-sidebar-topbar]')?.toggleAttribute('data-scrolled', event.target.scrollTop > 0);
    }
    if (event.target instanceof Element && event.target.closest('[data-sidebar-flyout]')) {
        return;
    }
    const root = event.target instanceof Element ? event.target.closest(ROOT) : null;
    (root ? [root] : document.querySelectorAll(ROOT)).forEach((each) => {
        hideTip(each);
        hideFlyouts(each);
    });
}, true);

// Turned to a wide screen with the panel open: it's a column now, so the page mustn't stay inert. And the rail's
// names and popovers don't belong on a narrow one.
wide.addEventListener('change', () => document.querySelectorAll(ROOT).forEach((root) => {
    close(root, { restoreFocus: false });
    // An open panel stays open: beside the page now, which can be used, or over it, which can't.
    if (isPanelOpen(root)) {
        contentOf(root).inert = !wide.matches;
    }
    hideTip(root);
    hideFlyouts(root);
}));
