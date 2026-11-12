@props([
    // The id of the file input it crops for, e.g. a file upload's: a picked or dropped image opens here, and Apply puts
    // the cropped one in the input in its place. The form, upload-url and wire:model then send that one. With multiple,
    // each image picked is cropped in turn. SVG, GIF, other animated images and other files go through as they are, and
    // it says so for images. One cropper an input. The
    // cropped file is sRGB, and has no EXIF data: no location, and no date taken either, so read that before it's
    // cropped if you need it. To crop the photo already saved again, pick it again, or crop it on the server with src.
    // Or src.
    'for' => null,
    // An image already stored, to crop on the server: its URL. The crop is sent as name[x], name[y], name[width],
    // name[height] and name[rotate], in the pixels of the image as shown, whose size is name[image_width] and
    // name[image_height]: a resized copy works, scaled to the stored file on the server. A Content Security Policy's
    // img-src must allow where it's served from, as for any image. Or for.
    'src' => null,
    // With src: the name the crop's fields submit under, e.g. "crop".
    'name' => null,
    // Above the image, and its name for screen readers.
    'label' => 'Crop image',
    // Width over height of the crop: 1 for a square, "16/9", "4/3".
    'aspect' => 1,
    // The guide drawn over the image: rect, or circle for avatars. The image itself is always cut as a rectangle.
    'shape' => 'rect',
    // The narrowest crop, in the image's pixels, so zooming in can't leave too few to look sharp. A picture already
    // narrower is still taken: refuse it on the server with the dimensions: rule if it must be larger.
    'minWidth' => null,
    // With for: the widest image Apply makes; a larger crop is scaled down. Phones can't draw much past 16 megapixels.
    'maxWidth' => null,
    // With for: the tallest image Apply makes.
    'maxHeight' => null,
    // With for: jpeg, png or webp. Left out, it keeps the picked image's (jpeg, png, webp), and makes others png. Never
    // one the input's accept leaves out: then the picked image's, jpeg or png. Where the browser can't write webp
    // (Safari), a photo goes as jpeg, and one with transparency as png.
    'format' => null,
    // With for: jpeg and webp quality, from 0 to 1.
    'quality' => 0.9,
    // How to move and zoom, under the image.
    'help' => 'Drag or use the arrow keys to move the crop. Pinch, Ctrl and scroll, or + and − to zoom.',
    // Read out as the crop changes. :width and :height are in pixels.
    'sizeMessage' => 'Crop :width by :height pixels',
    // Shown when the browser can't open the picked image (HEIC outside Safari, a damaged file, or one over 100 megapixels
    // or 100 MB, too large to open safely): it's sent uncropped, for the file upload's max-size and the server to judge.
    'failedMessage' => 'This image can\'t be edited here, so it will be sent as it is.',
    // With src: shown when the stored image doesn't load.
    'loadFailedMessage' => 'The image couldn\'t be loaded.',
    // With for: shown when Apply can't make the cropped image (a phone out of memory). The crop stays, to try again.
    'cropFailedMessage' => 'This image couldn\'t be cropped. Try again, or pick a smaller one.',
    // With for: shown when an SVG or an animated image (GIF, WebP, PNG, AVIF) is picked: it's sent uncropped.
    'uncroppedMessage' => 'Animated and vector images can\'t be cropped here, so they will be sent as they are.',
    // Shown while the crop is narrower than min-width, as any crop of a smaller image is. :min is min-width.
    'smallMessage' => 'This crop is under :min pixels wide, so it may look blurry.',
    // With for, when several images are picked: which one is open. :number and :count.
    'progressMessage' => 'Image :number of :count',
    // The rotate left button's name.
    'rotateLeftLabel' => 'Rotate left',
    // The rotate right button's name.
    'rotateRightLabel' => 'Rotate right',
    // The zoom out button's name.
    'zoomOutLabel' => 'Zoom out',
    // The zoom in button's name.
    'zoomInLabel' => 'Zoom in',
    // The reset button's text: the whole image, unrotated.
    'resetLabel' => 'Reset',
    // With for: the button that crops the picked image and hands it to the input.
    'applyLabel' => 'Apply',
    // With for: the button that drops the picked image.
    'cancelLabel' => 'Cancel',
    // With src: the error message(s) to show. Defaults to Laravel's for the name and its fields (crop, crop.width…).
    'error' => null,
    // With src: the error bag to read errors from.
    'bag' => 'default',
])

@php
    if (($for === null) === ($src === null)) {
        throw new \InvalidArgumentException('<x-widget.image-cropper> takes for (the id of a file input to crop for) or src (a stored image to crop on the server), and only one.');
    }
    if ($src !== null && ($name === null || $name === '')) {
        throw new \InvalidArgumentException('<x-widget.image-cropper src="…"> needs a name, e.g. name="crop": the crop is sent as name[x], name[y], name[width], name[height] and name[rotate].');
    }
    if (! in_array($shape, ['rect', 'circle'], true)) {
        throw new \InvalidArgumentException("Unknown shape [{$shape}] for <x-widget.image-cropper>. Use one of: rect, circle.");
    }
    if ($format !== null && ! in_array($format, ['jpeg', 'png', 'webp'], true)) {
        throw new \InvalidArgumentException("Unknown format [{$format}] for <x-widget.image-cropper>. Use one of: jpeg, png, webp.");
    }
    // "16/9" or a number; the script gets the plain ratio.
    $ratio = is_string($aspect) && str_contains($aspect, '/')
        ? (float) explode('/', $aspect, 2)[0] / max((float) explode('/', $aspect, 2)[1], 0.0001)
        : (float) $aspect;
    if ($ratio <= 0) {
        throw new \InvalidArgumentException("<x-widget.image-cropper aspect=\"{$aspect}\"> needs a width over height above 0, like 1 or \"16/9\".");
    }
    $id = app(\Bladewell\Support\ElementIds::class)->claim('cropper-'.($for ?? $name));
    // With src, the server can turn down the crop as a whole (crop) or one of its numbers (crop.width): show either.
    $key = $name !== null ? \Bladewell\Support\FormField::key($name) : null;
    $bagged = $key !== null && isset($errors) ? $errors->getBag($bag) : null;
    $messages = $error ?? ($bagged ? [...$bagged->get($key), ...array_merge([], ...array_values($bagged->get($key.'.*')))] : []);
@endphp

{{--
    resources/js/image-cropper draws the image on the canvas, moves and zooms it under the crop, and with for, makes the
    cropped file. Everything is kept in the image's own pixels, upright as its EXIF orientation says, so what's shown,
    what's cut and what's sent agree. wire:ignore: a Livewire render must not close it mid-crop. On a touch screen a
    swipe on the crop area moves the image rather than the page, as in every cropper; the page scrolls from elsewhere.
--}}
<div
    data-image-cropper
    @if ($for) data-for="{{ $for }}" hidden @else data-src="{{ $src }}" @endif
    data-aspect="{{ $ratio }}"
    data-shape="{{ $shape }}"
    @if ($minWidth) data-min-width="{{ (int) $minWidth }}" @endif
    @if ($maxWidth) data-max-width="{{ (int) $maxWidth }}" @endif
    @if ($maxHeight) data-max-height="{{ (int) $maxHeight }}" @endif
    @if ($format) data-format="{{ $format }}" @endif
    data-quality="{{ (float) $quality }}"
    data-size-message="{{ $sizeMessage }}"
    data-failed-message="{{ $failedMessage }}"
    data-load-failed-message="{{ $loadFailedMessage }}"
    data-crop-failed-message="{{ $cropFailedMessage }}"
    data-uncropped-message="{{ $uncroppedMessage }}"
    data-small-message="{{ $smallMessage }}"
    data-progress-message="{{ $progressMessage }}"
    wire:ignore
    role="group"
    aria-labelledby="{{ $id }}-label"
    {{ $attributes->class(['w-full max-w-md']) }}
>
    {{-- The frame every form control sits in: its label, and the server's errors under the controls. --}}
    <x-widget.field :id="$id" :label="$label" :error="$messages" bare :labels-control="false">
        <div class="grid gap-3">
            {{-- Hidden on its own when a picked image can't be opened, leaving the label and why. --}}
            <div data-image-cropper-editor class="grid gap-3">
                <p data-image-cropper-progress class="text-foreground/70 text-xs empty:hidden"></p>
                {{-- role="application": the arrow keys move the crop, even with a screen reader's own keys on. --}}
                <div
                    data-image-cropper-stage
                    role="application"
                    aria-roledescription="crop area"
                    aria-labelledby="{{ $id }}-label"
                    aria-describedby="{{ $id }}-help{{ $messages !== [] ? " {$id}-error" : '' }}"
                    tabindex="0"
                    class="bg-field focus-visible:ring-primary ring-offset-surface relative aspect-[4/3] w-full cursor-grab touch-none overflow-hidden rounded-2xl outline-none select-none focus-visible:ring-2 focus-visible:ring-offset-2 active:cursor-grabbing"
                >
                    {{-- Absolute, so the canvas the script sizes to the stage can never change the stage's size in turn. --}}
                    <canvas data-image-cropper-canvas aria-hidden="true" class="absolute inset-0 block size-full"></canvas>
                </div>
                <p id="{{ $id }}-help" class="text-foreground/70 text-xs">{{ $help }}</p>

                <div class="flex flex-wrap items-center gap-2">
                    <x-widget.button variant="neutral" size="sm" icon="rotate-ccw" :label="$rotateLeftLabel" data-image-cropper-action="rotate-left" />
                    {{-- The same arrow, mirrored: the icon set has no clockwise one. --}}
                    <x-widget.button variant="neutral" size="sm" icon="rotate-ccw" :label="$rotateRightLabel" data-image-cropper-action="rotate-right" class="[&_svg]:-scale-x-100" />
                    <x-widget.button variant="neutral" size="sm" icon="minus" :label="$zoomOutLabel" data-image-cropper-action="zoom-out" />
                    <x-widget.button variant="neutral" size="sm" icon="plus" :label="$zoomInLabel" data-image-cropper-action="zoom-in" />
                    <x-widget.button variant="neutral" size="sm" data-image-cropper-action="reset">{{ $resetLabel }}</x-widget.button>
                    @if ($for)
                        <span class="ms-auto flex gap-2">
                            <x-widget.button variant="neutral" size="sm" data-image-cropper-action="cancel">{{ $cancelLabel }}</x-widget.button>
                            <x-widget.button size="sm" data-image-cropper-action="apply">{{ $applyLabel }}</x-widget.button>
                        </span>
                    @endif
                </div>
            </div>

            @if ($src)
                {{-- Filled in by the script once the image has loaded. Until then, or if it doesn't load, they're empty, which
                     the server's required rules turn down: there's no crop to make. --}}
                @foreach (['x', 'y', 'width', 'height', 'rotate', 'image_width', 'image_height'] as $number)
                    <input type="hidden" name="{{ $name }}[{{ $number }}]" value="" data-image-cropper-field="{{ $number }}">
                @endforeach
            @endif

            {{-- Why a picked image went uncropped, or that a crop may look blurry: worth knowing, not an error. --}}
            <p data-image-cropper-notice role="status" class="text-foreground/70 text-xs empty:hidden"></p>
            <p data-image-cropper-error role="alert" class="text-error text-xs empty:hidden"></p>
            <span data-image-cropper-status role="status" class="sr-only"></span>
        </div>
    </x-widget.field>
</div>
