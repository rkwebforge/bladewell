// Animates <x-widget.accordion> open and close. Plain <details> snaps; the CSS-only
// alternative (::details-content) isn't in every browser and can't animate an item that
// the browser closes for a `name` group. So the height is animated here, and the group
// is handled here too.

const DURATION = 300;
const EASING = 'ease-out';
const running = new WeakMap();

const content = (details) => details.querySelector(':scope > [data-accordion-content]');
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function animateHeight(details, from, to, onDone) {
    const panel = content(details);
    running.get(details)?.cancel();

    panel.style.overflow = 'hidden';
    const animation = panel.animate(
        [{ height: `${from}px`, opacity: from === 0 ? 0 : 1 }, { height: `${to}px`, opacity: to === 0 ? 0 : 1 }],
        { duration: reducedMotion() ? 0 : DURATION, easing: EASING },
    );
    running.set(details, animation);

    animation.onfinish = () => {
        panel.style.overflow = '';
        running.delete(details);
        onDone?.();
    };
    // A reversed click cancels mid-way; the next animation starts from the current height. That one has already
    // set overflow:hidden for itself by the time this runs, so only reset it if nothing replaced this animation.
    animation.oncancel = () => {
        if (running.get(details) === animation) {
            panel.style.overflow = '';
            running.delete(details);
        }
    };
}

// Current rendered height, including mid-animation, so reversing a toggle doesn't jump.
const currentHeight = (details) => (details.open ? content(details).getBoundingClientRect().height : 0);

// In a menu scrolled to its end, a closing group makes the list shorter than where it's scrolled to, so the browser
// would pull it back and the whole list would slide down. Keep room for that at the end instead: the heading stays put.
// The room only ever shrinks: as the menu is scrolled back up, and when a group opens. In a menu not near its end it's 0.
const menuList = (menu) => menu.querySelector(':scope > ul');

function keepRoom(menu, shrinkBy = 0) {
    const list = menuList(menu);
    const room = parseFloat(list.style.paddingBottom) || 0;
    const content = menu.scrollHeight - room - shrinkBy;
    const needed = Math.max(0, Math.ceil(menu.scrollTop + menu.clientHeight - content));
    // Grows only for a group about to close; anything else can only take room away.
    const next = shrinkBy > 0 ? Math.max(room, needed) : Math.min(room, needed);
    list.style.paddingBottom = next > 0 ? `${next}px` : '';
}

document.addEventListener('scroll', (event) => {
    if (event.target instanceof Element && event.target.matches('[data-accordion-menu]') && menuList(event.target)?.style.paddingBottom) {
        keepRoom(event.target);
    }
}, true);

// Once the group has closed, a menu whose links all fit again has nothing left to scroll to, so the room would only leave
// an empty scrollbar: glide back to the top instead, and keepRoom gives the room up as it goes. A longer menu keeps it.
// At the top, what's left of it no longer shows; it goes too.
function releaseRoom(menu) {
    const list = menuList(menu);
    const room = parseFloat(list.style.paddingBottom) || 0;
    if (room === 0 || menu.scrollHeight - room > menu.clientHeight) {
        return;
    }
    if (menu.scrollTop === 0) {
        list.style.paddingBottom = '';

        return;
    }
    menu.addEventListener('scrollend', () => menu.scrollTop === 0 && (list.style.paddingBottom = ''), { once: true });
    menu.scrollTo({ top: 0, behavior: reducedMotion() ? 'instant' : 'smooth' });
}

function collapse(details) {
    if (!details.open || details.hasAttribute('data-closing')) {
        return;
    }
    const menu = details.closest('[data-accordion-menu]');
    if (menu) {
        keepRoom(menu, currentHeight(details));
    }
    // data-closing lets the tint and chevron animate alongside the height, while `open` stays set.
    details.setAttribute('data-closing', '');
    animateHeight(details, currentHeight(details), 0, () => {
        details.open = false;
        details.removeAttribute('data-closing');
        if (menu) {
            releaseRoom(menu);
        }
    });
}

function expand(details) {
    const from = details.hasAttribute('data-closing') ? currentHeight(details) : 0;
    details.removeAttribute('data-closing');
    details.open = true;

    const group = details.dataset.accordionGroup;
    if (group) {
        document.querySelectorAll(`details[data-accordion-group="${CSS.escape(group)}"][open]`).forEach((other) => {
            if (other !== details) {
                collapse(other);
            }
        });
    }

    const menu = details.closest('[data-accordion-menu]');
    animateHeight(details, from, content(details).scrollHeight, menu ? () => keepRoom(menu) : undefined);
}

// The native `name` group would close siblings instantly, before they could animate, so move it to a
// data attribute and enforce "one open at a time" in expand() instead. Runs at load and again before every
// toggle, so accordions added later (fetched HTML, a table page swap) are covered too; the native name stays
// in the markup until then, so without JS the group still works.
function adoptGroups() {
    document.querySelectorAll('details[data-accordion][name]').forEach((details) => {
        details.dataset.accordionGroup = details.getAttribute('name');
        details.removeAttribute('name');
    });
}

document.addEventListener('click', (event) => {
    const summary = event.target.closest?.('details[data-accordion] > summary');
    if (!summary) {
        return;
    }
    event.preventDefault();
    adoptGroups();
    const details = summary.parentElement;
    details.open && !details.hasAttribute('data-closing') ? collapse(details) : expand(details);
});

adoptGroups();

// <x-widget.accordion.menu> paints scrolled to its current item with CSS alone (scroll-initial-target). Browsers without
// it yet open the menu at the top, so bring the current item into view here, centred; that runs after the first paint.
// Only when it's out of view: a menu with `remember` may already be back where it was left, and that should stay.
if (!CSS.supports('scroll-initial-target', 'nearest')) {
    document.querySelectorAll('[data-accordion-menu]').forEach((menu) => {
        const current = menu.querySelector('[aria-current="page"]');
        if (current && (current.offsetTop < menu.scrollTop || current.offsetTop + current.offsetHeight > menu.scrollTop + menu.clientHeight)) {
            menu.scrollTop = current.offsetTop - (menu.clientHeight - current.offsetHeight) / 2;
        }
    });
}

// scroll-initial-target has set where the menu is first painted, but Chrome keeps the menu pinned to it until something
// scrolls the menu, re-centring the current item whenever the list changes height: opening or closing a group would
// slide the whole list instead of only what's below the heading. Its work is done once the page has loaded, so drop it.
document.querySelectorAll('[data-accordion-menu] [aria-current="page"]').forEach((current) => current.classList.remove('[scroll-initial-target:nearest]'));

// remember: keep each such menu's scroll position for the next page (a link, reload, back). The inline script the
// menu renders puts it back before that page's first paint.
window.addEventListener('pagehide', () => {
    document.querySelectorAll('[data-accordion-menu-remember]').forEach((menu) => {
        try {
            sessionStorage.setItem(menu.dataset.accordionMenuRemember, String(menu.scrollTop));
        } catch {
            // Storage turned off: the menu simply opens at the current item next time.
        }
    });
});

// A menu of #section links (a table of contents) never reloads the page, so the server's current item would stay put
// while the URL moves on. Move aria-current, which the highlight is styled from, to the link for the URL's #hash: on
// load, on a click, and on back / forward. Links to other pages are left to the server.
const sectionLink = (link) => link.hash !== '' && link.origin === location.origin && link.pathname === location.pathname && link.search === location.search;

function markCurrent(menu, link) {
    menu.querySelectorAll('[aria-current="page"]').forEach((other) => other !== link && other.removeAttribute('aria-current'));
    link.setAttribute('aria-current', 'page');
}

function followHash() {
    if (location.hash === '') {
        return;
    }
    document.querySelectorAll('[data-accordion-menu]').forEach((menu) => {
        const link = [...menu.querySelectorAll('a[href]')].find((a) => sectionLink(a) && a.hash === location.hash);
        if (link) {
            markCurrent(menu, link);
        }
    });
}

document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('[data-accordion-menu] a[href]') : null;
    if (link && sectionLink(link)) {
        markCurrent(link.closest('[data-accordion-menu]'), link);
    }
});
window.addEventListener('hashchange', followHash);
followHash();
