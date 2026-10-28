// Behaviour for <x-widget.captcha>: reloading the image, the audio and a broken image, and a provider's token for wire:model. Delegated from `document`, so fields added later work without
// re-initialising. Client-side filtering is only for convenience; the Form Request must validate the same rules.
import { on, onLivewireMorph } from '../field';

// --- Captcha: reload the image --------------------------------------------------

on('click', '[data-captcha-refresh]', (event, button) => {
    const root = button.closest('[data-captcha]');
    const image = root.querySelector('[data-captcha-image]');
    const input = root.querySelector('[data-captcha-input]');

    // Cache-buster so the browser fetches a fresh image from the same URL.
    const url = new URL(image.dataset.src, window.location.href);
    url.searchParams.set('_', String(Date.now()));

    const done = () => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
    };
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    image.addEventListener('load', done, { once: true });
    image.addEventListener('error', done, { once: true });
    image.src = url.href;

    // The spoken version belongs to the same code, so it is reloaded alongside the image.
    const audio = root.querySelector('[data-captcha-audio]');
    if (audio) {
        audio.pause();
        const audioUrl = new URL(audio.dataset.src, window.location.href);
        audioUrl.searchParams.set('_', url.searchParams.get('_'));
        audio.src = audioUrl.href;
    }

    // A new code: the old answer goes, and wire:model and other listeners hear of it, as when it's deleted by hand.
    input.value = '';
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.focus();
});

// A broken image shows a message instead of the browser's broken-image icon, until a refresh loads one.
// load and error don't bubble, so they're caught on the way down; images that failed before this script
// ran are checked once at start.
function showCaptchaImage(image, loaded) {
    image.hidden = !loaded;
    image.parentElement.querySelector('[data-captcha-error]').hidden = loaded;
}
['load', 'error'].forEach((type) => document.addEventListener(type, (event) => {
    if (event.target instanceof HTMLImageElement && event.target.matches('[data-captcha-image]')) {
        showCaptchaImage(event.target, type === 'load');
    }
}, true));
document.querySelectorAll('img[data-captcha-image]').forEach((image) => {
    if (image.complete) {
        showCaptchaImage(image, image.naturalWidth > 0);
    }
});

// A captcha that first appears in a Livewire update comes without src (see captcha.blade.php): load it now it's here.
function loadCaptchas(scope) {
    scope.querySelectorAll('img[data-captcha-image]:not([src]), audio[data-captcha-audio]:not([src])').forEach((media) => {
        media.src = media.dataset.src;
    });
}
new MutationObserver((records) => {
    for (const node of records.flatMap((record) => [...record.addedNodes])) {
        if (node instanceof Element) {
            loadCaptchas(node.parentElement ?? node);
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

// Audio: play the code aloud, or stop it; the button shows which.
on('click', '[data-captcha-play]', (event, button) => {
    const audio = button.closest('[data-captcha]').querySelector('[data-captcha-audio]');
    if (audio.paused) {
        audio.currentTime = 0;
        audio.play().catch(() => window.toast?.error('The audio could not be played.'));
    } else {
        audio.pause();
    }
});
['play', 'pause', 'ended'].forEach((type) => document.addEventListener(type, (event) => {
    if (event.target instanceof HTMLAudioElement && event.target.matches('[data-captcha-audio]')) {
        event.target.closest('[data-captcha]').querySelector('[data-captcha-play]')?.setAttribute('aria-pressed', String(type === 'play'));
    }
}, true));

// --- Turnstile, reCAPTCHA and hCaptcha with wire:model ------------------------------------------------------

// A provider keeps its token in a field of its own, which wire:model can't reach. With wire:model the widget comes
// without the provider's class, so the provider's script leaves it alone, and is drawn here with the provider's
// render(): the token arrives in the callbacks and goes to the hidden input beside the widget (data-captcha-token),
// with an input event, emptied again when it expires or fails. render() also returns the id that reset() needs.
const providers = {
    turnstile: () => window.turnstile,
    recaptcha: () => window.grecaptcha,
    hcaptcha: () => window.hcaptcha,
};
const drawn = new WeakMap();

// A <script> that arrives in a Livewire render is never run, so a widget that first appears in one would wait for a
// provider that never loads: its script is added here instead. Not when the page brought it (this module runs once the
// page is parsed, so those are all there by now), which would load the provider twice.
const pageScripts = new Set([...document.scripts].map((script) => script.src));
const added = new Set();
function loadScript(url) {
    const src = url && new URL(url, window.location.href).href;
    if (src && !pageScripts.has(src) && !added.has(src)) {
        added.add(src);
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        document.head.append(script);
    }
}

// The last token passed on, kept here: a render may rewrite the hidden input's value.
const tokens = new WeakMap();

function setToken(widget, token) {
    const input = widget.nextElementSibling;
    if (input?.matches('[data-captcha-token]') && (tokens.get(widget) ?? '') !== token) {
        tokens.set(widget, token);
        input.value = token;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

function draw(widget) {
    const { captchaProvider: provider, captchaScript, sitekey, theme, size, language } = widget.dataset;
    const api = providers[provider]?.();
    if (drawn.has(widget) || !sitekey) {
        return;
    }
    if (typeof api?.render !== 'function') {
        loadScript(captchaScript);

        return;
    }
    const clear = () => setToken(widget, '');
    const options = {
        sitekey,
        size,
        callback: (token) => setToken(widget, token),
        'expired-callback': clear,
        'error-callback': clear,
    };
    // Only Turnstile follows the page's colour scheme; the others are light unless told dark.
    if (theme !== 'auto' || provider === 'turnstile') {
        options.theme = theme;
    }
    if (provider === 'turnstile') {
        options.language = language;
    } else if (provider === 'hcaptcha') {
        options.hl = language;
        options['chalexpired-callback'] = clear;
    }
    drawn.set(widget, api.render(widget, options));
}

// The provider's script loads on its own (async), before or after this one, so widgets wait until its render() is
// there, for up to a minute (a blocked script never comes). Also run for widgets that first appear in a Livewire
// render: the provider would never draw those.
let waiting = null;
function drawAll(tries = 600) {
    const pending = [...document.querySelectorAll('[data-captcha-provider]')].filter((widget) => !drawn.has(widget) && widget.dataset.sitekey);
    pending.forEach(draw);
    if (pending.some((widget) => !drawn.has(widget)) && !waiting && tries > 0) {
        waiting = setTimeout(() => {
            waiting = null;
            drawAll(tries - 1);
        }, 100);
    }
}
drawAll();
new MutationObserver((records) => {
    if (records.some((record) => [...record.addedNodes].some((node) => node instanceof Element && (node.matches('[data-captcha-provider]') || node.querySelector('[data-captcha-provider]'))))) {
        drawAll();
    }
}).observe(document.documentElement, { childList: true, subtree: true });

// A token is good for one check. Once a submit has sent it (capture runs before Livewire's wire:submit), the widget
// gets a new one when that request's render comes back, so a form that failed validation can be sent again. Kept here,
// not in an attribute: wire:ignore keeps the widget's contents through a render, but not its attributes.
const sent = new WeakSet();
document.addEventListener('submit', (event) => {
    event.target.querySelectorAll('[data-captcha-provider]').forEach((widget) => sent.add(widget));
}, true);

onLivewireMorph((el) => {
    el.querySelectorAll('[data-captcha-provider]').forEach((widget) => {
        if (!sent.delete(widget)) {
            return;
        }
        if (drawn.has(widget)) {
            providers[widget.dataset.captchaProvider]().reset(drawn.get(widget));
        }
        setToken(widget, '');
    });
});
