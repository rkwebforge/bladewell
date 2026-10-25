// Drives <x-widget.file-upload>, .button and .image. Without JS each is a plain file input that still submits.
// This adds drag and drop, a list of the chosen files with remove, the size and type checks from max-size and
// accept (convenience only: the Form Request must validate the same), and with upload-url, uploading each file
// as soon as it's picked, with progress, retry and the stored ids submitted instead of the files.
// Everything is delegated from `document`, so file inputs added to the page later work too.
import { onLivewireMorph } from '../field';

const ROOT = '[data-file-upload]';
const state = new WeakMap();

const messagesOf = (root) => {
    try {
        return JSON.parse(root.dataset.messages ?? '{}');
    } catch {
        return {};
    }
};
const say = (template, values) => Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`:${key}`, value), template ?? '');

// Same wording as the server's UploadLimits::size(): 1024-based, one decimal at most.
function size(bytes) {
    for (const [unit, factor] of [['GB', 1024 ** 3], ['MB', 1024 ** 2], ['KB', 1024]]) {
        if (bytes >= factor) {
            return `${Number((bytes / factor).toFixed(1))} ${unit}`;
        }
    }

    return `${bytes} bytes`;
}

// accept=".pdf,image/*": an extension matches the name's end, type/* the MIME type's group, anything else exactly.
function accepted(file, accept) {
    const entries = (accept ?? '').split(',').map((entry) => entry.trim().toLowerCase()).filter(Boolean);
    if (entries.length === 0) {
        return true;
    }
    const name = file.name.toLowerCase();
    const type = (file.type || '').toLowerCase();

    return entries.some((entry) => (entry.startsWith('.') ? name.endsWith(entry) : entry.endsWith('/*') ? type.startsWith(entry.slice(0, -1)) : type === entry));
}

// Why a file can't be taken, in the widget's own words; null when it can.
function problem(root, file) {
    const messages = messagesOf(root);
    const maxBytes = Number(root.dataset.maxBytes || 0);
    if (!accepted(file, root.dataset.accept)) {
        return say(messages.wrongType, { name: file.name });
    }
    if (maxBytes && file.size > maxBytes) {
        return say(messages.tooBig, { name: file.name, size: size(maxBytes) });
    }

    return null;
}

const inputOf = (root) => root.querySelector('input[type="file"]');

function announce(root, text) {
    const status = root.querySelector('[data-file-status]');
    if (!status) {
        return;
    }
    // Emptied first and filled a moment later, so the same message twice in a row is read twice.
    status.textContent = '';
    setTimeout(() => { status.textContent = text; }, 50);
}

// --- Dropzone ---------------------------------------------------------------------------------------------

function dropzone(root) {
    if (!state.has(root)) {
        state.set(root, { entries: [] });
    }

    return state.get(root);
}

const direct = (root) => Boolean(root.dataset.uploadUrl);

// The files the form will send, rebuilt from the list: the browser's own FileList can't be edited in place.
function syncInput(root) {
    const input = inputOf(root);
    const entries = dropzone(root).entries;
    if (!direct(root)) {
        const transfer = new DataTransfer();
        entries.filter((entry) => entry.state === 'ready').forEach((entry) => transfer.items.add(entry.file));
        input.files = transfer.files;
    } else {
        // Direct upload: the input only picks. Required means "at least one uploaded", which its own required
        // would get wrong (it's always empty), so it's only required while nothing has been uploaded.
        if (input.dataset.required === undefined) {
            input.dataset.required = input.required ? 'true' : 'false';
        }
        const done = root.querySelectorAll('[data-file-item][data-state="done"]').length;
        input.required = input.dataset.required === 'true' && done === 0;
        input.value = '';
    }
}

function row(root, file, error) {
    const item = root.querySelector('[data-file-template]').content.firstElementChild.cloneNode(true);
    item.querySelector('[data-file-name]').textContent = file.name;
    item.querySelector('[data-file-size]').textContent = size(file.size);
    const remove = item.querySelector('[data-file-remove]');
    remove.setAttribute('aria-label', `${remove.getAttribute('aria-label')} ${file.name}`);
    if (error) {
        item.dataset.state = 'error';
        const text = item.querySelector('[data-file-error]');
        text.textContent = error;
        text.hidden = false;
    }

    return item;
}

const maxFilesOf = (root) => Number(root.dataset.maxFiles || 0) || (root.dataset.multiple === 'true' ? Infinity : 1);
// Files that count toward max-files: listed and not failed, and those the server listed as already uploaded.
const keptCount = (root) => dropzone(root).entries.filter((entry) => entry.state !== 'error').length
    + root.querySelectorAll('[data-file-item][data-state="done"]:not([data-entry])').length;

// Retry a failed upload, if there's still room for it: other files may have filled max-files since it failed.
function retry(root, entry) {
    const maxFiles = maxFilesOf(root);
    if (keptCount(root) >= maxFiles) {
        const message = say(messagesOf(root).tooMany, { count: maxFiles });
        const error = entry.el.querySelector('[data-file-error]');
        error.textContent = message;
        error.hidden = false;
        entry.el.querySelector('[data-file-retry]').hidden = true;
        announce(root, message);

        return;
    }
    upload(root, entry);
}

function addFiles(root, files) {
    const list = root.querySelector('[data-file-list]');
    const store = dropzone(root);
    const messages = messagesOf(root);
    const multiple = root.dataset.multiple === 'true';
    const maxFiles = maxFilesOf(root);
    let picked = [...files];
    // A single-file dropzone takes the newest pick in place of the old one.
    if (!multiple) {
        store.entries.forEach((entry) => removeEntry(root, entry, { quiet: true }));
        root.querySelectorAll('[data-file-item][data-state="done"]').forEach((item) => item.remove());
        picked = picked.slice(0, 1);
    }
    let added = 0;
    for (const file of picked) {
        const error = problem(root, file) ?? (keptCount(root) >= maxFiles ? say(messages.tooMany, { count: maxFiles }) : null);
        const entry = { file, state: error ? 'error' : 'ready', el: row(root, file, error), xhr: null };
        entry.el.dataset.entry = '';
        store.entries.push(entry);
        list.append(entry.el);
        entry.el.querySelector('[data-file-remove]').addEventListener('click', () => removeEntry(root, entry));
        entry.el.querySelector('[data-file-retry]').addEventListener('click', () => retry(root, entry));
        if (error) {
            announce(root, error);
        } else {
            added++;
            if (direct(root)) {
                upload(root, entry);
            }
        }
    }
    syncInput(root);
    if (added > 0 && !direct(root)) {
        announce(root, say(messages.added, { count: added }));
    }
}

function removeEntry(root, entry, { quiet = false } = {}) {
    const store = dropzone(root);
    entry.xhr?.abort();
    const next = entry.el.nextElementSibling?.querySelector('[data-file-remove]') ?? entry.el.previousElementSibling?.querySelector('[data-file-remove]');
    const hadFocus = entry.el.contains(document.activeElement);
    entry.el.remove();
    store.entries = store.entries.filter((other) => other !== entry);
    syncInput(root);
    if (!quiet) {
        tellLivewire(root);
        announce(root, say(messagesOf(root).removed, { name: entry.file.name }));
        // Focus stays in the list, or goes back to the picker when the list is empty, not to <body>.
        if (hadFocus) {
            (next ?? inputOf(root)).focus();
        }
    }
}

// upload-url: one request per file, with the CSRF token, so progress and failure are per file. The route answers
// JSON with the stored file's id (and a 422 with errors.file for a file it refuses); the id goes in a hidden input.
function upload(root, entry) {
    const messages = messagesOf(root);
    const track = entry.el.querySelector('[data-file-progress-track]');
    const bar = entry.el.querySelector('[data-file-progress]');
    const error = entry.el.querySelector('[data-file-error]');
    const retry = entry.el.querySelector('[data-file-retry]');
    entry.state = 'uploading';
    entry.el.dataset.state = 'uploading';
    error.hidden = true;
    retry.hidden = true;
    track.hidden = false;
    bar.style.width = '0%';

    const body = new FormData();
    body.append('file', entry.file);
    const xhr = new XMLHttpRequest();
    entry.xhr = xhr;
    xhr.open('POST', root.dataset.uploadUrl);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('X-CSRF-TOKEN', root.dataset.csrf ?? '');
    xhr.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable) {
            bar.style.width = `${Math.round((event.loaded / event.total) * 100)}%`;
        }
    });
    const fail = (message) => {
        entry.state = 'error';
        entry.el.dataset.state = 'error';
        track.hidden = true;
        error.textContent = message;
        error.hidden = false;
        retry.hidden = false;
        announce(root, message);
    };
    xhr.addEventListener('load', () => {
        entry.xhr = null;
        let json = null;
        try {
            json = JSON.parse(xhr.responseText);
        } catch {
            // Not JSON (an HTML error page): the generic message below says it failed.
        }
        // Done only with an id to submit: a non-empty string or a number. {"id": null} or an empty one isn't a stored file.
        const id = json?.id;
        const stored = (typeof id === 'string' && id !== '') || (typeof id === 'number' && Number.isFinite(id));
        if (xhr.status >= 200 && xhr.status < 300 && stored) {
            entry.state = 'done';
            entry.el.dataset.state = 'done';
            track.hidden = true;
            const hidden = Object.assign(document.createElement('input'), { type: 'hidden', name: root.dataset.valueName ?? '', value: String(json.id) });
            const form = inputOf(root).getAttribute('form');
            if (form) {
                hidden.setAttribute('form', form);
            }
            entry.el.append(hidden);
            syncInput(root);
            announce(root, say(messages.uploadedOne, { name: entry.file.name }));

            return;
        }
        fail(json?.errors?.file?.[0] ?? json?.message ?? say(messages.failed, { name: entry.file.name }));
    });
    xhr.addEventListener('error', () => {
        entry.xhr = null;
        fail(say(messages.failed, { name: entry.file.name }));
    });
    xhr.send(body);
}

// A file picked or dropped on a dropzone joins the list. The browser's pick replaces its FileList, so it's read
// here and the input is rebuilt from the whole list (syncInput).
document.addEventListener('change', (event) => {
    const input = event.target;
    const root = input.closest?.(ROOT);
    if (!root || input.type !== 'file' || !input.matches('input[type="file"]')) {
        return;
    }
    const kind = root.dataset.fileUpload;
    if (kind === 'button') {
        pickedButton(root, input);
    } else if (kind === 'image') {
        pickedImage(root, input);
    } else if (input.files.length > 0) {
        addFiles(root, input.files);
    }
});

// The drop area highlights while files are dragged over it; a drop goes through addFiles like a pick.
for (const type of ['dragenter', 'dragover']) {
    document.addEventListener(type, (event) => {
        const drop = event.target.closest?.('[data-file-drop]');
        if (drop && event.dataTransfer?.types.includes('Files') && !inputOf(drop.closest(ROOT)).disabled) {
            event.preventDefault();
            drop.setAttribute('data-dragging', '');
        }
    });
}
document.addEventListener('dragleave', (event) => {
    const drop = event.target.closest?.('[data-file-drop]');
    if (drop && !drop.contains(event.relatedTarget)) {
        drop.removeAttribute('data-dragging');
    }
});
document.addEventListener('drop', (event) => {
    const drop = event.target.closest?.('[data-file-drop]');
    if (!drop) {
        return;
    }
    drop.removeAttribute('data-dragging');
    const root = drop.closest(ROOT);
    if (event.dataTransfer?.files.length && !inputOf(root).disabled) {
        event.preventDefault();
        addFiles(root, event.dataTransfer.files);
        tellLivewire(root);
    }
});

// Removing a file the server rendered (already uploaded) takes its hidden id input with it.
document.addEventListener('click', (event) => {
    const remove = event.target.closest?.('[data-file-item]:not([data-entry]) [data-file-remove]');
    if (!remove) {
        return;
    }
    const root = remove.closest(ROOT);
    const item = remove.closest('[data-file-item]');
    const next = item.nextElementSibling?.querySelector('[data-file-remove]') ?? inputOf(root);
    const name = item.querySelector('[data-file-name]')?.textContent ?? '';
    item.remove();
    syncInput(root);
    announce(root, say(messagesOf(root).removed, { name }));
    next.focus();
});

// A direct upload still running would submit the form without that file's id: hold the submit and say why.
// Capture phase, like the required check in widget/field, so the submit guard sees defaultPrevented.
// Every uploader the form submits, inside it or linked to it from elsewhere with form="…".
const uploadersOf = (form) => [...document.querySelectorAll(ROOT)].filter((root) => inputOf(root)?.form === form);

document.addEventListener('submit', (event) => {
    const busy = uploadersOf(event.target).flatMap((root) => [...root.querySelectorAll('[data-file-item][data-state="uploading"]')]);
    if (busy.length === 0) {
        return;
    }
    event.preventDefault();
    const root = busy[0].closest(ROOT);
    announce(root, messagesOf(root).waiting);
    inputOf(root).focus();
}, true);

// --- Button -----------------------------------------------------------------------------------------------

function pickedButton(root, input) {
    const shown = root.querySelector('[data-file-name]');
    const clear = root.querySelector('[data-file-clear]');
    const error = root.querySelector('[data-file-error]');
    const files = [...input.files];
    const why = files.map((file) => problem(root, file)).find(Boolean);
    error.textContent = why ?? '';
    if (why) {
        input.value = '';
    }
    const kept = why ? [] : files;
    shown.textContent = kept.length === 0 ? shown.dataset.empty : kept.length === 1 ? `${kept[0].name} (${size(kept[0].size)})` : say(messagesOf(root).count, { count: kept.length });
    clear.hidden = kept.length === 0;
}

document.addEventListener('click', (event) => {
    const clear = event.target.closest?.('[data-file-upload="button"] [data-file-clear]');
    if (!clear) {
        return;
    }
    const root = clear.closest(ROOT);
    const input = inputOf(root);
    input.value = '';
    pickedButton(root, input);
    tellLivewire(root);
    input.focus();
});

// --- Image ------------------------------------------------------------------------------------------------

const previews = new WeakMap();
const saved = new WeakMap();

// The image the form keeps when no new one is picked: the one the page came with (read before any preview replaces
// it; Livewire's current one is data-src), or none once the person removed it.
function savedImage(root) {
    if (!saved.has(root)) {
        saved.set(root, root.querySelector('[data-file-image]')?.getAttribute('src') || null);
    }
    if (root.querySelector('[data-file-remove-flag]')?.value === '1') {
        return null;
    }

    return root.dataset.src || saved.get(root);
}

function showImage(root, url) {
    const image = root.querySelector('[data-file-image]');
    const placeholder = root.querySelector('[data-file-placeholder]');
    const old = previews.get(root);
    if (old) {
        URL.revokeObjectURL(old);
        previews.delete(root);
    }
    if (url) {
        image.src = url;
    } else {
        image.removeAttribute('src');
    }
    image.hidden = !url;
    placeholder.toggleAttribute('hidden', Boolean(url));
    root.querySelector('[data-file-clear]').hidden = !url;
    root.querySelector('[data-file-choose]').textContent = url ? root.dataset.changeLabel : root.dataset.chooseLabel;
}

function pickedImage(root, input) {
    const file = input.files[0];
    const error = root.querySelector('[data-file-error]');
    if (!file) {
        return;
    }
    const why = problem(root, file);
    error.textContent = why ?? '';
    if (why) {
        input.value = '';
        // The input is empty again, so the picture goes back to what the form will keep: the saved image, or none.
        if (previews.has(root)) {
            showImage(root, savedImage(root));
        }

        return;
    }
    savedImage(root);
    const url = URL.createObjectURL(file);
    showImage(root, url);
    previews.set(root, url);
    const flag = root.querySelector('[data-file-remove-flag]');
    if (flag) {
        flag.value = '0';
    }
}

document.addEventListener('click', (event) => {
    const clear = event.target.closest?.('[data-file-upload="image"] [data-file-clear]');
    if (!clear) {
        return;
    }
    const root = clear.closest(ROOT);
    const input = inputOf(root);
    input.value = '';
    root.querySelector('[data-file-error]').textContent = '';
    showImage(root, null);
    tellLivewire(root);
    // Tells the controller to delete the current image; picking a new one sets it back to 0.
    const flag = root.querySelector('[data-file-remove-flag]');
    if (flag) {
        flag.value = '1';
    }
    input.focus();
});

// A direct-upload dropzone that already lists stored files (after a failed submit, or editing) mustn't also
// require a new pick.
document.querySelectorAll(`${ROOT}[data-upload-url]`).forEach(syncInput);

// --- Livewire ---------------------------------------------------------------------------------------------

// Bound with wire:model, the widget uploads the files itself rather than leaving it to Livewire's change handler,
// which only sees the latest pick: in Livewire 4 a multiple input adds each pick to the property, so after a remove
// or a drop the property and the list would disagree. Here the property is replaced with what the list holds, every
// time it changes, and the livewire-upload-* events still fire on the input. Not with upload-url, which sends itself.
const bindingOf = (input) => [...input.attributes].find((attribute) => attribute.name.startsWith('wire:model'))?.value;
const wireOf = (input) => window.Livewire?.find(input.closest('[wire\\:id]')?.getAttribute('wire:id'));

function tellLivewire(root) {
    const input = inputOf(root);
    const property = bindingOf(input);
    const wire = property && !direct(root) ? wireOf(input) : null;
    if (!wire) {
        return;
    }
    const files = [...input.files];
    const multiple = input.multiple;
    if (files.length === 0) {
        wire.set(property, multiple ? [] : null);

        return;
    }
    const fire = (name, detail = {}) => input.dispatchEvent(new CustomEvent(`livewire-upload-${name}`, { bubbles: true, detail: { property, ...detail } }));
    const callbacks = [
        () => fire('finish'),
        () => fire('error'),
        (event) => fire('progress', { progress: Math.round((event.loaded * 100) / event.total) }),
        () => fire('cancel'),
    ];
    fire('start');
    if (multiple) {
        wire.$uploadMultiple(property, files, ...callbacks, false);
    } else {
        wire.$upload(property, files[0], ...callbacks);
    }
}

// Capture: runs before Livewire's own listener on the input, which then never sees the pick.
document.addEventListener('change', (event) => {
    const input = event.target;
    const root = input.closest?.(ROOT);
    if (!root || input.type !== 'file' || !bindingOf(input) || direct(root) || !wireOf(input)) {
        return;
    }
    event.stopPropagation();
    const kind = root.dataset.fileUpload;
    if (kind === 'button') {
        pickedButton(root, input);
    } else if (kind === 'image') {
        pickedImage(root, input);
    } else if (input.files.length > 0) {
        addFiles(root, input.files);
    }
    tellLivewire(root);
}, true);

// The list, name and preview are wire:ignore'd, so a render never empties them. When the property goes from files to
// none, PHP emptied it ($this->reset('photo') after saving): empty them to match. data-livewire-files is its count.
const liveCounts = new WeakMap();

onLivewireMorph((scope) => {
    const roots = [...(scope.matches?.(ROOT) ? [scope] : []), ...(scope.querySelectorAll?.(`${ROOT}[data-livewire-files]`) ?? [])];
    for (const root of roots) {
        const now = Number(root.dataset.livewireFiles ?? 0);
        const before = liveCounts.get(root);
        liveCounts.set(root, now);
        if (!(before > 0 && now === 0)) {
            continue;
        }
        const input = inputOf(root);
        const kind = root.dataset.fileUpload;
        input.value = '';
        if (kind === 'button') {
            pickedButton(root, input);
        } else if (kind === 'image') {
            // Back to the image the server has now (a just-saved one), or none.
            showImage(root, root.dataset.src || null);
        } else {
            dropzone(root).entries.forEach((entry) => removeEntry(root, entry, { quiet: true }));
        }
    }
});

// --- Reset --------------------------------------------------------------------------------------------------

// A form put back as it was empties its file inputs; what the widgets show and keep follows, or the next pick would bring
// the old files back. Files the server listed as already uploaded stay, as they did when the page came. After the reset
// itself, which runs once this event is done.
document.addEventListener('reset', (event) => {
    const roots = uploadersOf(event.target);
    setTimeout(() => roots.forEach((root) => {
        const input = inputOf(root);
        const kind = root.dataset.fileUpload;
        if (kind === 'button') {
            pickedButton(root, input);
        } else if (kind === 'image') {
            root.querySelector('[data-file-error]').textContent = '';
            showImage(root, savedImage(root));
        } else {
            dropzone(root).entries.forEach((entry) => removeEntry(root, entry, { quiet: true }));
        }
        tellLivewire(root);
    }));
});
