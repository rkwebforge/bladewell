@props([
    // What it submits as; its error is found under it (files aren't refilled after a failed submit). Optional with
    // wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be changed.
    'disabled' => false,
    // The image types it takes, as for the accept attribute, e.g. "image/png,image/svg+xml". The picker only offers
    // those, other files are turned away as they're picked, and the hint names them.
    'accept' => 'image/*',
    // The largest file it takes: 500KB, 5MB, 2GB, or a number of kilobytes like Laravel's max: rule. Bigger files are
    // turned away as they're picked, and the hint says the limit. Your Form Request must still check it.
    'maxSize' => null,
    // The current image's URL, shown until a new one is picked; without it, a placeholder.
    'src' => null,
    // The image's alternative text.
    'alt' => '',
    // circle (avatars) or square (rounded corners, for logos).
    'shape' => 'circle',
    // The preview's size: sm, md or lg.
    'size' => 'md',
    // A hidden field of this name submits 1 when the current image is removed (and 0 otherwise), so your
    // controller knows to delete it.
    'removeName' => null,
    // The button's text while there's no image.
    'chooseLabel' => 'Upload',
    // The button's text once there's an image.
    'changeLabel' => 'Change',
    // The remove button's text.
    'removeLabel' => 'Remove',
    // Rewords or translates what it says, by key: tooBig (:name, :size), wrongType (:name).
    'messages' => [],
])

@php
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'file', attributes: $attributes);
    $id = $field->id;
    $limits = \Bladewell\Support\UploadLimits::from($maxSize, $accept);
    $hint = $limits->hint();
    $hintId = $hint ? "{$id}-hint" : null;
    $shapes = ['circle' => 'rounded-full', 'square' => 'rounded-2xl'];
    $sizes = ['sm' => 'size-14', 'md' => 'size-20', 'lg' => 'size-28'];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($shape, $shapes)) {
        throw new \InvalidArgumentException("Unknown shape [{$shape}] for <x-widget.file-upload.image>. Use one of: ".implode(', ', array_keys($shapes)).'.');
    }
    if (! array_key_exists($size, $sizes)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.file-upload.image>. Use one of: ".implode(', ', array_keys($sizes)).'.');
    }
    $messages = [
        'tooBig' => ':name is larger than :size.',
        'wrongType' => ':name isn\'t an image this accepts.',
        ...$messages,
    ];
    // wire:model: Livewire's renders are kept off the preview, buttons and error the script draws (see the dropzone).
    $live = str_starts_with((string) array_key_first(\Bladewell\Support\FormField::binding($attributes)), 'wire:model');
    [$found, $bound] = $field->fromLivewire();
    $liveFiles = $live && $found ? (is_countable($bound) ? count($bound) : (int) filled($bound)) : null;
    // The input sits inside the button-look label, which opens the picker. A second <label for> it (the field's) is
    // one too many for some screen readers, so the field's is plain text, and both name the input: "Photo, Choose image".
    $labelledBy = $attributes->hasAny(['aria-label', 'aria-labelledby']) ? null : trim(($label ? "{$id}-label " : '')."{$id}-button");
@endphp

{{--
    A profile photo or logo: the current image (src, from your app), a button to choose a new one, and Remove.
    The preview is a blob: URL, so a Content Security Policy needs img-src blob: for it. remove-name="remove_avatar"
    submits remove_avatar=1 when the current image is removed, so the controller knows to delete it.
--}}
<x-widget.field
    :required="$attributes->has('required')"
    :id="$id"
    :label="$label"
    :labels-control="false"
    :error="$field->errors"
    :info="$info"
    :disabled="$disabled"
    bare
    data-file-upload="image"
    :data-max-bytes="$limits->maxBytes ?? false"
    :data-accept="$accept ?? false"
    :data-messages="json_encode($messages)"
    :data-choose-label="$chooseLabel"
    :data-change-label="$changeLabel"
    :data-livewire-files="$liveFiles === null ? false : (string) $liveFiles"
    {{-- The preview is wire:ignore'd, so after PHP empties the property the script shows the current src from here. --}}
    :data-src="$live ? (string) $src : false"
    :class="$attributes->get('class')"
>
    <div @if ($live) wire:ignore @endif class="flex items-center gap-4">
        <div data-file-preview @class(['bg-field border-line text-muted grid shrink-0 place-items-center overflow-hidden border', $shapes[$shape], $sizes[$size]])>
            <img data-file-image @if ($src) src="{{ $src }}" @else hidden @endif alt="{{ $alt }}" class="size-full object-cover">
            <x-widget.icon name="image" data-file-placeholder :hidden="(bool) $src" class="size-1/3" />
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
            <div class="flex flex-wrap items-center gap-2">
                <label @class([
                    'bg-field text-foreground hover:bg-line has-[:focus-visible]:ring-primary relative inline-flex items-center rounded-xl px-4 py-2 text-sm font-medium transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-offset-2 ring-offset-surface',
                    'cursor-pointer' => ! $disabled,
                    'cursor-not-allowed opacity-60' => $disabled,
                ])>
                    <span id="{{ $id }}-button" data-file-choose>{{ $src ? $changeLabel : $chooseLabel }}</span>
                    <input
                        type="file"
                        id="{{ $id }}"
                        @if ($name) name="{{ $name }}" @endif
                        accept="{{ $accept }}"
                        @disabled($disabled)
                        {{ $field->controlAttributes($attributes->merge(['aria-describedby' => $hintId, 'aria-labelledby' => $labelledBy]), (bool) $info)->class(['sr-only']) }}
                    >
                </label>
                <button type="button" data-file-clear @if (! $src) hidden @endif @disabled($disabled) class="text-error hover:bg-error/10 focus-visible:ring-primary rounded-xl px-3 py-2 text-sm font-medium outline-none focus-visible:ring-2">{{ $removeLabel }}</button>
            </div>
            @if ($hint)
                <p id="{{ $hintId }}" class="text-foreground/70 text-xs">{{ $hint }}</p>
            @endif
            <p data-file-error role="alert" class="text-error text-xs empty:hidden"></p>
        </div>
    </div>
    @if ($removeName)
        <input type="hidden" name="{{ $removeName }}" value="0" data-file-remove-flag {{ $attributes->only(['form']) }}>
    @endif
</x-widget.field>
