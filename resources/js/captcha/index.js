// Behaviour for <x-widget.captcha>: reloading the image, the audio and a broken image. Delegated from `document`, so fields added later work without
// re-initialising. Client-side filtering is only for convenience; the Form Request must validate the same rules.
import { on } from '../field';

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
