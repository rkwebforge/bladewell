// Drives <x-widget.image-cropper>. The image moves and zooms under a fixed crop frame; one finger or the mouse drags it,
// two fingers pinch, Ctrl and the wheel (a trackpad pinch) zoom, and the keyboard does both. Delegated from `document`,
// so croppers added later work without setting up.
//
// Every number is in the image's own pixels, upright as its EXIF orientation says: the browser decodes it that way
// (imageOrientation: 'from-image'), so what's shown, what's cut and the coordinates sent all describe the same picture.
// Rotation is in quarter turns, applied before the crop: x, y, width and height are in the rotated image.
//
// With for, a picked image is taken before the file upload or Livewire sees it (window, capture phase), and only the
// cropped one Apply makes reaches them, so upload-url never sends the original. With src, the crop is written to hidden
// fields for the server to cut.

const ROOT = '[data-image-cropper]';
// iOS Safari can't draw a canvas of more than this many pixels: the output is scaled down to fit.
const MAX_PIXELS = 16777216;
// The most a picked image may be to open here. Decoded, it takes 4 bytes a pixel: 100 megapixels is 400 MB, past what
// a phone can spare, and a small file can open to a huge image. Larger ones are sent as they are, for the file upload's
// max-size and the server to judge.
const MAX_SOURCE_PIXELS = 100_000_000;
const MAX_SOURCE_BYTES = 100 * 1024 * 1024;
// Space around the crop frame, in CSS pixels, so the image beyond it shows.
const INSET = 24;

const states = new WeakMap();
// Inputs this script is handing a file to right now (the cropped image, or one it can't open): that change goes through.
const handing = new WeakSet();
// The cropped files each input was last given, put back if the next pick is cancelled.
const applied = new WeakMap();

const partsOf = (root) => ({
    stage: root.querySelector('[data-image-cropper-stage]'),
    canvas: root.querySelector('[data-image-cropper-canvas]'),
    status: root.querySelector('[data-image-cropper-status]'),
    error: root.querySelector('[data-image-cropper-error]'),
    notice: root.querySelector('[data-image-cropper-notice]'),
    editor: root.querySelector('[data-image-cropper-editor]'),
    action: (name) => root.querySelector(`[data-image-cropper-action="${name}"]`),
});
const inputOf = (root) => document.getElementById(root.dataset.for);
const ratioOf = (root) => Number(root.dataset.aspect) || 1;

// --- Geometry ----------------------------------------------------------------------------------------------------

// The image's size once rotated: a quarter turn swaps width and height.
const sizeOf = (state) => (state.rotate % 180 === 0 ? { w: state.width, h: state.height } : { w: state.height, h: state.width });

// The crop frame in the stage, in CSS pixels: as large as fits with INSET round it, at the aspect ratio.
function frameOf(root) {
    const box = partsOf(root).stage.getBoundingClientRect();
    const ratio = ratioOf(root);
    let w = Math.max(box.width - INSET * 2, 1);
    let h = w / ratio;
    if (h > box.height - INSET * 2) {
        h = Math.max(box.height - INSET * 2, 1);
        w = h * ratio;
    }

    return { stageWidth: box.width, stageHeight: box.height, w, h, left: (box.width - w) / 2, top: (box.height - h) / 2 };
}

// Zoom is CSS pixels per image pixel. At least enough for the image to cover the frame; at most enough to keep
// min-width pixels across it (or eight times the least, without one).
function zoomRange(root, state, frame) {
    const { w, h } = sizeOf(state);
    const least = Math.max(frame.w / w, frame.h / h);
    const minWidth = Number(root.dataset.minWidth) || 0;
    const most = minWidth ? Math.max(least, frame.w / Math.min(minWidth, w)) : least * 8;

    return { least, most };
}

// Keeps the zoom in range and the frame over the image.
function clamp(root, state, frame = frameOf(root)) {
    const { least, most } = zoomRange(root, state, frame);
    state.zoom = Math.min(most, Math.max(least, state.zoom));
    const { w, h } = sizeOf(state);
    const halfW = frame.w / (2 * state.zoom);
    const halfH = frame.h / (2 * state.zoom);
    state.cx = Math.min(w - halfW, Math.max(halfW, state.cx));
    state.cy = Math.min(h - halfH, Math.max(halfH, state.cy));
}

// The crop, in whole pixels of the rotated image, inside it, at the aspect ratio as near as whole pixels allow. Measured
// from the frame last drawn: hidden (a closed tab or modal), the area has no size, and a crop measured from that would
// be a few pixels. Never drawn, it's the largest crop that fits.
function cropOf(root, state) {
    const { w, h } = sizeOf(state);
    const across = state.frame ? Math.round(state.frame.w / state.zoom) : Infinity;
    const width = Math.max(1, Math.min(w, Math.round(h * ratioOf(root)), across));
    const height = Math.min(h, Math.max(1, Math.round(width / ratioOf(root))));
    const x = Math.min(w - width, Math.max(0, Math.round(state.cx - width / 2)));
    const y = Math.min(h - height, Math.max(0, Math.round(state.cy - height / 2)));

    return { x, y, width, height, rotate: state.rotate };
}

// Draws the upright image turned by state.rotate, filling the rotated image's box from (0, 0). The preview, or at Apply
// the full-size image: either is drawn at the full image's size, so the numbers don't change.
function paintImage(context, state, image = state.preview) {
    const { w, h } = sizeOf(state);
    context.translate(w / 2, h / 2);
    context.rotate((state.rotate * Math.PI) / 180);
    context.drawImage(image, -state.width / 2, -state.height / 2, state.width, state.height);
}

function draw(root) {
    const state = states.get(root);
    const { canvas } = partsOf(root);
    if (!state) {
        return;
    }
    const frame = frameOf(root);
    // Nothing to draw in, inside a closed tab, accordion or modal: wait to be shown (the ResizeObserver says when), or
    // the zoom would follow the area down to nothing and back.
    if (frame.stageWidth === 0 || frame.stageHeight === 0) {
        return;
    }
    // The crop area resized (a phone turned, a window narrowed): zoom with it, so the same part of the image is kept.
    if (state.frame && state.frame.w !== frame.w) {
        state.zoom *= frame.w / state.frame.w;
    }
    state.frame = frame;
    clamp(root, state, frame);
    const scale = window.devicePixelRatio || 1;
    // Only when the size changes: setting it clears the canvas and allocates its memory again.
    const width = Math.round(frame.stageWidth * scale);
    const height = Math.round(frame.stageHeight * scale);
    if (canvas.width !== width || canvas.height !== height) {
        canvas.width = width;
        canvas.height = height;
    }
    const context = canvas.getContext('2d');
    context.setTransform(scale, 0, 0, scale, 0, 0);
    context.clearRect(0, 0, frame.stageWidth, frame.stageHeight);

    context.save();
    context.translate(frame.left + frame.w / 2, frame.top + frame.h / 2);
    context.scale(state.zoom, state.zoom);
    context.translate(-state.cx, -state.cy);
    context.imageSmoothingQuality = 'high';
    paintImage(context, state);
    context.restore();

    // Dims what's left out, and outlines what's kept.
    const guide = new Path2D();
    if (root.dataset.shape === 'circle') {
        guide.ellipse(frame.left + frame.w / 2, frame.top + frame.h / 2, frame.w / 2, frame.h / 2, 0, 0, Math.PI * 2);
    } else {
        guide.rect(frame.left, frame.top, frame.w, frame.h);
    }
    const shade = new Path2D();
    shade.rect(0, 0, frame.stageWidth, frame.stageHeight);
    shade.addPath(guide);
    context.fillStyle = 'rgb(0 0 0 / 0.55)';
    context.fill(shade, 'evenodd');
    context.strokeStyle = 'rgb(255 255 255 / 0.9)';
    context.lineWidth = 2;
    context.stroke(guide);

    const { least, most } = zoomRange(root, state, frame);
    const parts = partsOf(root);
    parts.action('zoom-out').disabled = state.zoom <= least * 1.0001;
    parts.action('zoom-in').disabled = state.zoom >= most * 0.9999;
    // Narrower than min-width, as any crop of an image smaller than that is: say it may look blurry. Fixed text, so
    // it's read out once as it appears, not on every step of a zoom.
    const minWidth = Number(root.dataset.minWidth) || 0;
    const small = minWidth && cropOf(root, state).width < minWidth ? root.dataset.smallMessage.replace(':min', minWidth) : '';
    if (parts.notice.textContent !== small) {
        parts.notice.textContent = small;
    }
    writeFields(root, state);
}

// A drag or pinch sends more events than the screen shows frames (120 a second and up): draw once a frame.
const queued = new WeakSet();
function schedule(root) {
    if (queued.has(root)) {
        return;
    }
    queued.add(root);
    requestAnimationFrame(() => {
        queued.delete(root);
        draw(root);
    });
}

// --- What changes it -----------------------------------------------------------------------------------------

function reset(root, state) {
    state.rotate = 0;
    state.zoom = 0;
    const { w, h } = sizeOf(state);
    state.cx = w / 2;
    state.cy = h / 2;
}

// Zooms keeping the image point under (x, y), in CSS pixels in the stage, where it is.
function zoomAt(root, state, factor, x, y) {
    const frame = frameOf(root);
    const centreX = frame.left + frame.w / 2;
    const centreY = frame.top + frame.h / 2;
    const imageX = state.cx + (x - centreX) / state.zoom;
    const imageY = state.cy + (y - centreY) / state.zoom;
    state.zoom *= factor;
    clamp(root, state, frame);
    state.cx = imageX - (x - centreX) / state.zoom;
    state.cy = imageY - (y - centreY) / state.zoom;
}

const zoomCentre = (root, state, factor) => {
    const frame = frameOf(root);
    zoomAt(root, state, factor, frame.left + frame.w / 2, frame.top + frame.h / 2);
};

// A quarter turn keeps the point at the frame's centre there. Clockwise, (x, y) in a w × h image goes to (h − y, x).
function turn(state, clockwise) {
    const { w, h } = sizeOf(state);
    [state.cx, state.cy] = clockwise ? [h - state.cy, state.cx] : [state.cy, w - state.cx];
    state.rotate = (state.rotate + (clockwise ? 90 : 270)) % 360;
}

// Says the crop's size once it settles, not on every step of a drag.
const timers = new WeakMap();
function announce(root, now = false) {
    const state = states.get(root);
    const { status } = partsOf(root);
    clearTimeout(timers.get(root));
    const say = () => {
        const crop = cropOf(root, state);
        status.textContent = root.dataset.sizeMessage.replace(':width', crop.width).replace(':height', crop.height);
    };
    if (now) {
        say();
    } else {
        timers.set(root, setTimeout(say, 400));
    }
}

// The server's error was about the crop as it was sent; once it's changed, it's noise (as on every field). The server
// checks again on submit.
function edited(root) {
    const field = root.querySelector('[data-field][data-invalid]');
    if (!field) {
        return;
    }
    field.removeAttribute('data-invalid');
    const error = field.querySelector('[data-field-error]');
    const stage = partsOf(root).stage;
    const id = error?.querySelector('[id]')?.id ?? error?.id;
    error?.remove();
    stage.setAttribute('aria-describedby', (stage.getAttribute('aria-describedby') ?? '').split(/\s+/).filter((token) => token && token !== id).join(' '));
}

// With the size of the image as shown, upright and unturned: if src is a resized copy, the server scales the crop to
// the stored file by its own size over this.
function writeFields(root, state) {
    const crop = { ...cropOf(root, state), image_width: state.width, image_height: state.height };
    for (const field of root.querySelectorAll('[data-image-cropper-field]')) {
        field.value = String(crop[field.dataset.imageCropperField]);
    }
}

// --- Opening an image --------------------------------------------------------------------------------------------

// Upright as its EXIF orientation says. createImageBitmap does it in one step; an <img> does too, where the options
// aren't there yet. release() lets the pixels go as soon as they aren't needed, rather than when memory runs short.
async function decode(source) {
    if (source instanceof Blob && 'createImageBitmap' in window) {
        try {
            const bitmap = await createImageBitmap(source, { imageOrientation: 'from-image' });

            return { image: bitmap, width: bitmap.width, height: bitmap.height, release: () => bitmap.close() };
        } catch {
            // Fall through to <img>, which can decode more in some browsers (HEIC in Safari).
        }
    }
    const image = new Image();
    const url = source instanceof Blob ? URL.createObjectURL(source) : source;
    image.src = url;
    try {
        await image.decode();
    } finally {
        if (source instanceof Blob) {
            // Decoded: the pixels stay with the element.
            setTimeout(() => URL.revokeObjectURL(url), 0);
        }
    }

    return { image, width: image.naturalWidth, height: image.naturalHeight, release: () => image.removeAttribute('src') };
}

// The longest side of the copy kept while cropping. A 48-megapixel photo takes about 190 MB whole, enough for iOS
// Safari to reload the tab, and drawing it whole on every frame stutters on slower phones; this is enough to fill the
// crop area sharply on any screen. Apply opens the file again at full size, so the crop loses nothing.
const PREVIEW = 2048;

// Why a picked file isn't opened: one with frames would lose all but the first; one too large to decode safely.
const ANIMATED = 'animated';
const TOO_LARGE = 'too large';

// What the file's header says, read without decoding it: its size in pixels, and whether it has frames. Width and
// height stay null where it can't tell (HEIC, AVIF); the decoded size is checked again after.
async function inspect(file) {
    const found = { width: null, height: null, animated: false };
    try {
        const bytes = new Uint8Array(await file.slice(0, 524288).arrayBuffer());
        const view = new DataView(bytes.buffer);
        const text = (at, length) => String.fromCharCode(...bytes.subarray(at, at + length));
        if (text(1, 3) === 'PNG') {
            found.width = view.getUint32(16);
            found.height = view.getUint32(20);
            // APNG: an acTL chunk before the first IDAT.
            for (let at = 8; at + 8 <= bytes.length && !found.animated; at += 12 + view.getUint32(at)) {
                const type = text(at + 4, 4);
                if (type === 'IDAT') {
                    break;
                }
                found.animated = type === 'acTL';
            }
        } else if (text(0, 4) === 'RIFF' && text(8, 4) === 'WEBP') {
            const chunk = text(12, 4);
            if (chunk === 'VP8X') {
                found.animated = (bytes[20] & 0x02) !== 0;
                found.width = 1 + (bytes[24] | (bytes[25] << 8) | (bytes[26] << 16));
                found.height = 1 + (bytes[27] | (bytes[28] << 8) | (bytes[29] << 16));
            } else if (chunk === 'VP8L') {
                found.width = 1 + (bytes[21] | ((bytes[22] & 0x3f) << 8));
                found.height = 1 + ((bytes[22] >> 6) | (bytes[23] << 2) | ((bytes[24] & 0x0f) << 10));
            } else if (chunk === 'VP8 ') {
                found.width = view.getUint16(26, true) & 0x3fff;
                found.height = view.getUint16(28, true) & 0x3fff;
            }
        } else if (bytes[0] === 0xff && bytes[1] === 0xd8) {
            // JPEG: the size is in the first start-of-frame segment, after any EXIF.
            for (let at = 2; at + 9 < bytes.length && bytes[at] === 0xff;) {
                const marker = bytes[at + 1];
                if (marker === 0xff) {
                    at++;
                } else if (marker >= 0xc0 && marker <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marker)) {
                    found.height = view.getUint16(at + 5);
                    found.width = view.getUint16(at + 7);
                    break;
                } else {
                    at += 2 + view.getUint16(at + 2);
                }
            }
        } else if (text(4, 4) === 'ftyp') {
            // HEIF and AVIF: an image sequence's brand among the file type's.
            const end = Math.min(view.getUint32(0), bytes.length);
            for (let at = 8; at + 4 <= end && !found.animated; at += 4) {
                found.animated = ['avis', 'msf1', 'hevs'].includes(text(at, 4));
            }
        }
    } catch {
        // A header cut short: decoding decides.
    }

    return found;
}

// Lets the pixels go now rather than when memory runs short.
function release(state) {
    if (state?.preview instanceof ImageBitmap) {
        state.preview.close();
    } else if (state?.preview instanceof HTMLCanvasElement) {
        // A canvas holds its pixels until it's sized down, even once nothing points at it.
        state.preview.width = 0;
        state.preview.height = 0;
    }
}

// Decodes an image into a fresh state, without showing it: the caller shows it only if it's still wanted.
async function load(source, file = null) {
    if (file) {
        if (file.size > MAX_SOURCE_BYTES) {
            throw TOO_LARGE;
        }
        const header = await inspect(file);
        if (header.animated) {
            throw ANIMATED;
        }
        if (header.width * header.height > MAX_SOURCE_PIXELS) {
            throw TOO_LARGE;
        }
    }
    const full = await decode(source);
    if (full.width * full.height > MAX_SOURCE_PIXELS) {
        full.release();
        throw TOO_LARGE;
    }
    const scale = Math.min(1, PREVIEW / Math.max(full.width, full.height));
    let preview = full.image;
    if (scale < 1) {
        preview = document.createElement('canvas');
        preview.width = Math.round(full.width * scale);
        preview.height = Math.round(full.height * scale);
        const context = preview.getContext('2d');
        context.imageSmoothingQuality = 'high';
        context.drawImage(full.image, 0, 0, preview.width, preview.height);
        full.release();
    }
    return { preview, whole: scale === 1, width: full.width, height: full.height, file, rotate: 0, zoom: 0, cx: 0, cy: 0, frame: null, pointers: new Map() };
}

function show(root, state) {
    release(states.get(root));
    states.set(root, state);
    reset(root, state);
    const parts = partsOf(root);
    parts.error.textContent = '';
    parts.notice.textContent = '';
    root.hidden = false;
    parts.editor.hidden = false;
    const apply = parts.action('apply');
    if (apply) {
        apply.disabled = false;
    }
    draw(root);
    // A picked image was opened by the person, so say so; a stored one opens with the page, which says nothing yet.
    if (state.file) {
        announce(root, true);
    }
}

// --- With for: the picked files ---------------------------------------------------------------------------------

// A pick is cropped an image at a time (several, on an input with multiple), and the input gets them all at the end.
// SVG has no pixels to cut, and a GIF or other image with frames would lose all but the first, so they go through as
// they are, with any other files, and the cropper says so.
//
// Each pick is a session. Opening and cutting take a while on a large photo, and a new pick, a reset or the cropper's
// removal can end the session meanwhile: every step checks its session is still the one, and lets go of what it made
// if not, so an older image never replaces a newer one. session.working is the step running, if any.
const sessions = new WeakMap();
const UNCROPPABLE = ['image/svg+xml', 'image/gif'];
// Some systems give HEIC and others no type at all: the name says it's an image.
const IMAGE_NAME = /\.(jpe?g|png|webp|avif|heic|heif|bmp)$/i;
const croppable = (file) => (file.type.startsWith('image/') && !UNCROPPABLE.includes(file.type)) || (file.type === '' && IMAGE_NAME.test(file.name));

function rootFor(input) {
    return input.id ? document.querySelector(`${ROOT}[data-for="${CSS.escape(input.id)}"]`) : null;
}

// Puts files in the input and tells everything listening, as a pick would.
function hand(input, files) {
    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
    handing.add(input);
    try {
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    } finally {
        handing.delete(input);
    }
}

// Back to what the form had before this pick, which the file upload, never shown the pick, still shows.
function restore(input) {
    const transfer = new DataTransfer();
    (applied.get(input) ?? []).forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
}

function close(root) {
    release(states.get(root));
    states.delete(root);
    root.hidden = true;
}

function clearMessages(root) {
    const parts = partsOf(root);
    parts.error.textContent = '';
    parts.notice.textContent = '';
}

// The label and why, with nothing to crop: what was picked went as it is.
function explain(root, { failed = false, uncropped = false }) {
    const parts = partsOf(root);
    parts.error.textContent = failed ? root.dataset.failedMessage : '';
    parts.notice.textContent = uncropped ? root.dataset.uncroppedMessage : '';
    if (failed || uncropped) {
        parts.editor.hidden = true;
        root.hidden = false;
    }
}

function track(session, work) {
    session.working = work;
    work.finally(() => {
        if (session.working === work) {
            session.working = null;
        }
    });

    return work;
}

function begin(root, input, files) {
    const picked = input.multiple ? files : files.slice(0, 1);
    const queue = picked.filter(croppable);
    const uncropped = picked.some((file) => UNCROPPABLE.includes(file.type));
    if (queue.length === 0) {
        // Nothing to crop: it goes through, and the cropper says why if it was an image it leaves alone.
        if (!sessions.has(root)) {
            close(root);
            clearMessages(root);
            explain(root, { uncropped });
        }

        return false;
    }
    close(root);
    clearMessages(root);
    const session = { input, queue, done: picked.filter((file) => !croppable(file)), count: queue.length, failed: false, uncropped, working: null };
    sessions.set(root, session);
    next(root, session);

    return true;
}

// Opens the next image, or hands everything over when there are none left. Resolves once it's open (or handed over).
function next(root, session) {
    return track(session, (async () => {
        for (let file = session.queue.shift(); file; file = session.queue.shift()) {
            const progress = root.querySelector('[data-image-cropper-progress]');
            const number = session.count - session.queue.length;
            progress.textContent = session.count > 1 ? root.dataset.progressMessage.replace(':number', number).replace(':count', session.count) : '';
            let state = null;
            try {
                state = await load(file, file);
            } catch (reason) {
                // With frames, or not an image this browser can open (or safely can): it goes as it is, and the file
                // upload checks it like any other.
                session[reason === ANIMATED ? 'uncropped' : 'failed'] = true;
            }
            if (sessions.get(root) !== session) {
                release(state);

                return;
            }
            if (state) {
                show(root, state);
                partsOf(root).stage.focus();

                return;
            }
            session.done.push(file);
        }
        finish(root, session);
    })());
}

function finish(root, session) {
    const { input, done } = session;
    sessions.delete(root);
    close(root);
    explain(root, session);
    if (done.length > 0) {
        applied.set(input, done);
        hand(input, done);
    } else {
        restore(input);
    }
    input.focus();
}

const TYPES = { jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp' };
const EXTENSIONS = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };
const SUFFIXES = { 'image/jpeg': ['.jpg', '.jpeg'], 'image/png': ['.png'], 'image/webp': ['.webp'] };

// Whether the input's accept takes the type: "image/webp", "image/*" or ".webp". No accept takes anything.
function accepts(input, type) {
    const tokens = (input?.accept ?? '').split(',').map((token) => token.trim().toLowerCase()).filter(Boolean);

    return tokens.length === 0 || tokens.some((token) => token === type || (token.endsWith('/*') && type.startsWith(token.slice(0, -1))) || SUFFIXES[type].includes(token));
}

// A copy on white, for JPEG: it has no transparency, and what's see-through would come out black.
function flatten(canvas) {
    const flat = document.createElement('canvas');
    flat.width = canvas.width;
    flat.height = canvas.height;
    const context = flat.getContext('2d');
    context.fillStyle = '#fff';
    context.fillRect(0, 0, flat.width, flat.height);
    context.drawImage(canvas, 0, 0);

    return flat;
}

// Whether any of it is see-through, read from a small copy: shrinking averages alpha, so it still shows.
function transparent(canvas) {
    const sample = document.createElement('canvas');
    sample.width = Math.min(64, canvas.width);
    sample.height = Math.min(64, canvas.height);
    const context = sample.getContext('2d', { willReadFrequently: true });
    context.drawImage(canvas, 0, 0, sample.width, sample.height);
    const pixels = context.getImageData(0, 0, sample.width, sample.height).data;
    for (let alpha = 3; alpha < pixels.length; alpha += 4) {
        if (pixels[alpha] < 255) {
            return true;
        }
    }

    return false;
}

// A canvas keeps its pixels until it's sized down, not just until nothing points at it: a multi-image pick on a phone
// would otherwise hold every crop at once, and iOS reloads the tab, losing them all.
const emptyCanvas = (canvas) => {
    canvas.width = 0;
    canvas.height = 0;
};

const encode = (canvas, type, quality) => new Promise((resolve) => {
    const flat = type === 'image/jpeg' ? flatten(canvas) : null;
    (flat ?? canvas).toBlob((blob) => {
        if (flat) {
            emptyCanvas(flat);
        }
        resolve(blob);
    }, type, quality);
});

// The type to make: format, else the picked image's if it's jpeg, png or webp. Anything else (HEIC, AVIF, BMP) as
// jpeg, or png when it has transparency: a photo as png is several times the size, and could be over max-size. Never
// one the input won't take, or the file upload would turn down the cropper's own file: then the picked image's, jpeg
// or png.
function typeFor(root, state, canvas) {
    const input = inputOf(root);
    const picked = Object.values(TYPES).includes(state.file.type) ? state.file.type : null;
    const wanted = TYPES[root.dataset.format] ?? picked ?? (transparent(canvas) ? 'image/png' : 'image/jpeg');

    return [wanted, picked, 'image/jpeg', 'image/png'].find((type) => type && accepts(input, type)) ?? wanted;
}

// The crop as a file, named like the picked image, at most max-width × max-height and MAX_PIXELS.
async function render(root, state) {
    const crop = cropOf(root, state);
    const maxWidth = Number(root.dataset.maxWidth) || Infinity;
    const maxHeight = Number(root.dataset.maxHeight) || Infinity;
    const scale = Math.min(1, maxWidth / crop.width, maxHeight / crop.height, Math.sqrt(MAX_PIXELS / (crop.width * crop.height)));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(crop.width * scale));
    canvas.height = Math.max(1, Math.round(crop.height * scale));
    const context = canvas.getContext('2d');
    context.imageSmoothingQuality = 'high';
    context.scale(canvas.width / crop.width, canvas.height / crop.height);
    context.translate(-crop.x, -crop.y);
    // The full-size image, opened again just for this and let go straight after; a small one was kept whole.
    const full = state.whole ? null : await decode(state.file);
    paintImage(context, state, full?.image ?? state.preview);
    full?.release();

    const type = typeFor(root, state, canvas);
    const quality = Number(root.dataset.quality) || 0.9;
    let blob;
    try {
        blob = await encode(canvas, type, quality);
        // Null when the browser couldn't make it, out of memory as a phone can be: apply says so and keeps the crop.
        if (!blob) {
            throw new Error('The cropped image could not be encoded.');
        }
        // A browser that can't write webp (Safari, as of writing) quietly writes png instead, several times the size of
        // a photo as jpeg. A photo with nothing see-through goes as jpeg, if the input takes it; one with transparency
        // stays png.
        if (blob.type !== type && type === 'image/webp' && accepts(inputOf(root), 'image/jpeg') && !transparent(canvas)) {
            blob = (await encode(canvas, 'image/jpeg', quality)) ?? blob;
        }
    } finally {
        emptyCanvas(canvas);
    }
    const name = `${state.file.name.replace(/\.[^.]+$/, '')}.${EXTENSIONS[blob.type] ?? 'png'}`;

    return new File([blob], name, { type: blob.type, lastModified: Date.now() });
}

// Cuts the open image and opens the next. Resolves to false when it couldn't be cut (a phone out of memory), with the
// crop kept and Apply on again, so it can be tried again.
function apply(root) {
    const state = states.get(root);
    const session = sessions.get(root);
    // A second press while a large photo is still being cut would make a second file.
    if (!state || !session || session.working) {
        return Promise.resolve(true);
    }
    const parts = partsOf(root);
    parts.action('apply').disabled = true;

    return track(session, (async () => {
        let file = null;
        try {
            file = await render(root, state);
        } catch {
            // Said below.
        }
        // A new pick took over meanwhile: this one's gone.
        if (sessions.get(root) !== session) {
            return true;
        }
        if (!file) {
            parts.error.textContent = root.dataset.cropFailedMessage;
            parts.action('apply').disabled = false;

            return false;
        }
        session.done.push(file);
        await next(root, session);

        return true;
    })());
}

// Every image left open for the input, cut as it stands, one after the other. Waits for whatever is opening or being
// cut first, never going round without something to wait for. False if one couldn't be cut.
async function applyAll(root) {
    const session = sessions.get(root);
    while (sessions.get(root) === session) {
        const step = session.working ?? (states.get(root) ? apply(root) : null);
        if (!step || (await step) === false) {
            return false;
        }
    }

    return true;
}

// Leaves this image out; the next one opens, or the form gets what was kept.
function cancel(root) {
    const session = sessions.get(root);
    if (session && !session.working && states.has(root)) {
        next(root, session);
    }
}

// Before anything else on the page: the file upload, Livewire and the app's own listeners only get the cropped images.
window.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file' || handing.has(input)) {
        return;
    }
    const root = rootFor(input);
    if (root && begin(root, input, [...input.files])) {
        event.stopImmediatePropagation();
    }
}, true);

// A drop on a file upload's drop zone, which adds the files itself rather than through the input's change.
window.addEventListener('drop', (event) => {
    const upload = event.target instanceof Element ? event.target.closest('[data-file-upload]') : null;
    const input = upload?.querySelector('input[type="file"]');
    const root = input && !input.disabled ? rootFor(input) : null;
    if (root && begin(root, input, [...(event.dataTransfer?.files ?? [])])) {
        event.preventDefault();
        event.stopImmediatePropagation();
        upload.querySelector('[data-dragging]')?.removeAttribute('data-dragging');
    }
}, true);

// With wire:model, the crop is uploaded to Livewire first: the form waits for that, or it would be sent without it.
// False if the upload failed or never finished: the form isn't sent without the photo, and the crop stays in the input
// for the file upload to try again.
const boundToLivewire = (input) => Boolean(window.Livewire) && [...input.attributes].some((attribute) => attribute.name.startsWith('wire:model'));
const uploaded = (input) => new Promise((resolve) => {
    const done = (event) => {
        clearTimeout(timer);
        input.removeEventListener('livewire-upload-finish', done);
        input.removeEventListener('livewire-upload-error', done);
        resolve(event?.type === 'livewire-upload-finish');
    };
    const timer = setTimeout(done, 60000);
    input.addEventListener('livewire-upload-finish', done);
    input.addEventListener('livewire-upload-error', done);
});

// A form sent with a crop still open sends the crops: each image left is applied as it stands, then the form is sent
// again as it was sent. If one can't be cut, it isn't sent: the cropper says why, and the person tries again.
window.addEventListener('submit', (event) => {
    const form = event.target;
    const cropping = [...document.querySelectorAll(`${ROOT}[data-for]`)].filter((root) => sessions.has(root) && inputOf(root)?.form === form);
    if (cropping.length === 0) {
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    Promise.all(cropping.map(async (root) => {
        const input = inputOf(root);
        const upload = boundToLivewire(input) ? uploaded(input) : null;
        if (!(await applyAll(root))) {
            partsOf(root).stage.focus();

            return false;
        }

        return upload ? upload : true;
    })).then((results) => {
        if (results.every(Boolean)) {
            form.requestSubmit(event.submitter ?? undefined);
        }
    });
}, true);

// A form put back as it was empties its file inputs: what the cropper holds for them goes too.
document.addEventListener('reset', (event) => {
    for (const root of document.querySelectorAll(`${ROOT}[data-for]`)) {
        const input = inputOf(root);
        if (input?.form === event.target) {
            applied.delete(input);
            sessions.delete(root);
            close(root);
            clearMessages(root);
        }
    }
});

// --- Pointer, wheel and keys ---------------------------------------------------------------------------------------

const stageOf = (event) => (event.target instanceof Element ? event.target.closest('[data-image-cropper-stage]') : null);
const local = (stage, event) => {
    const box = stage.getBoundingClientRect();

    return { x: event.clientX - box.left, y: event.clientY - box.top };
};

// Which cropper each pointer is moving: its up can land off the crop area, where the browser didn't capture it, and a
// finger left counted as down would turn the next drag into a pinch.
const pointing = new Map();

document.addEventListener('pointerdown', (event) => {
    const stage = stageOf(event);
    const root = stage?.closest(ROOT);
    const state = root && states.get(root);
    if (!state || (event.pointerType === 'mouse' && event.button !== 0)) {
        return;
    }
    event.preventDefault();
    stage.focus();
    try {
        stage.setPointerCapture(event.pointerId);
    } catch {
        // A pointer the browser doesn't track (a synthetic one) still moves the image through the events below.
    }
    state.pointers.set(event.pointerId, local(stage, event));
    pointing.set(event.pointerId, root);
});

document.addEventListener('pointermove', (event) => {
    const root = pointing.get(event.pointerId);
    const state = root && states.get(root);
    const stage = root && partsOf(root).stage;
    if (!state?.pointers.has(event.pointerId)) {
        return;
    }
    const before = [...state.pointers.values()];
    state.pointers.set(event.pointerId, local(stage, event));
    const after = [...state.pointers.values()];
    if (after.length === 1) {
        state.cx -= (after[0].x - before[0].x) / state.zoom;
        state.cy -= (after[0].y - before[0].y) / state.zoom;
    } else {
        // Two fingers: zoom by how far they've spread, around the point between them, and follow it as it moves.
        const [a, b] = after;
        const [c, d] = before;
        const middle = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
        const previous = { x: (c.x + d.x) / 2, y: (c.y + d.y) / 2 };
        const spread = Math.hypot(a.x - b.x, a.y - b.y) / Math.max(Math.hypot(c.x - d.x, c.y - d.y), 1);
        zoomAt(root, state, spread, previous.x, previous.y);
        state.cx -= (middle.x - previous.x) / state.zoom;
        state.cy -= (middle.y - previous.y) / state.zoom;
    }
    edited(root);
    schedule(root);
});

for (const type of ['pointerup', 'pointercancel']) {
    document.addEventListener(type, (event) => {
        const root = pointing.get(event.pointerId);
        pointing.delete(event.pointerId);
        const state = root && states.get(root);
        if (state?.pointers.delete(event.pointerId) && state.pointers.size === 0) {
            announce(root);
        }
    });
}

// Ctrl or ⌘ and the wheel, which is also what a trackpad pinch sends; the plain wheel scrolls the page as ever. On each
// crop area, not the document: a wheel listener that may cancel makes the browser wait for it before every scroll, so
// one on the document would slow scrolling on every page, cropper or not.
function wheel(event) {
    const stage = event.currentTarget;
    const root = stage.closest(ROOT);
    const state = states.get(root);
    if (!state || !(event.ctrlKey || event.metaKey)) {
        return;
    }
    event.preventDefault();
    const point = local(stage, event);
    zoomAt(root, state, Math.exp(-event.deltaY * 0.01), point.x, point.y);
    edited(root);
    schedule(root);
    announce(root);
}

document.addEventListener('keydown', (event) => {
    const stage = stageOf(event);
    const root = stage?.closest(ROOT);
    const state = root && states.get(root);
    if (!state || event.target !== stage || event.altKey || event.ctrlKey || event.metaKey) {
        return;
    }
    // Ten screen pixels a press, fifty with Shift: the same distance at any zoom.
    const step = (event.shiftKey ? 50 : 10) / state.zoom;
    const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    if (moves[event.key]) {
        state.cx += moves[event.key][0];
        state.cy += moves[event.key][1];
    } else if (event.key === '+' || event.key === '=') {
        zoomCentre(root, state, 1.25);
    } else if (event.key === '-' || event.key === '_') {
        zoomCentre(root, state, 0.8);
    } else if (event.key === 'Enter' && root.dataset.for) {
        event.preventDefault();
        apply(root);

        return;
    } else if (event.key === 'Escape' && root.dataset.for) {
        event.preventDefault();
        cancel(root);

        return;
    } else {
        return;
    }
    event.preventDefault();
    edited(root);
    draw(root);
    announce(root);
});

// --- Buttons --------------------------------------------------------------------------------------------------------

document.addEventListener('click', (event) => {
    const button = event.target instanceof Element ? event.target.closest('[data-image-cropper-action]') : null;
    const root = button?.closest(ROOT);
    const state = root && states.get(root);
    if (!state || button.disabled) {
        return;
    }
    const action = button.dataset.imageCropperAction;
    if (action === 'apply') {
        apply(root);

        return;
    }
    if (action === 'cancel') {
        cancel(root);

        return;
    }
    if (action === 'rotate-left' || action === 'rotate-right') {
        turn(state, action === 'rotate-right');
    } else if (action === 'zoom-in') {
        zoomCentre(root, state, 1.25);
    } else if (action === 'zoom-out') {
        zoomCentre(root, state, 0.8);
    } else if (action === 'reset') {
        reset(root, state);
    }
    edited(root);
    draw(root);
    announce(root, true);
    // A zoom button disabled at the limit would drop focus: keep it in the cropper.
    if (button.disabled) {
        partsOf(root).stage.focus();
    }
});

// --- Setting up -----------------------------------------------------------------------------------------------------

// The frame follows the stage's size: a rotated phone, a resized window.
const resized = new ResizeObserver((entries) => entries.forEach((entry) => draw(entry.target.closest(ROOT))));

function setUp(root) {
    const stage = partsOf(root).stage;
    if (!stage || stage.dataset.watched !== undefined) {
        return;
    }
    stage.dataset.watched = '';
    resized.observe(stage);
    stage.addEventListener('wheel', wheel, { passive: false });
    if (root.dataset.src) {
        load(root.dataset.src).then((state) => {
            // Taken off the page while it loaded.
            if (!root.isConnected) {
                release(state);

                return;
            }
            show(root, state);
        }).catch(() => {
            partsOf(root).error.textContent = root.dataset.loadFailedMessage;
        });
    }
}

// A cropper taken off the page (a closed modal, a Livewire @if) lets go of its image and stops being watched. Mid-pick,
// the input still holds what was picked, uncropped and with its EXIF: it's put back as it was before the pick.
function tearDown(root) {
    const stage = partsOf(root).stage;
    if (stage?.dataset.watched !== undefined) {
        resized.unobserve(stage);
        delete stage.dataset.watched;
    }
    const session = sessions.get(root);
    if (session) {
        sessions.delete(root);
        restore(session.input);
    }
    release(states.get(root));
    states.delete(root);
}

const croppersIn = (scope) => (scope.matches?.(ROOT) ? [scope] : []).concat([...scope.querySelectorAll(ROOT)]);

export function initImageCroppers(scope = document) {
    croppersIn(scope).forEach(setUp);
}

initImageCroppers();

new MutationObserver((records) => {
    for (const record of records) {
        for (const node of record.addedNodes) {
            if (node instanceof Element) {
                initImageCroppers(node);
            }
        }
        // Moved rather than removed, it's still on the page: nothing to let go of.
        for (const node of record.removedNodes) {
            if (node instanceof Element) {
                croppersIn(node).filter((root) => !root.isConnected).forEach(tearDown);
            }
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });
