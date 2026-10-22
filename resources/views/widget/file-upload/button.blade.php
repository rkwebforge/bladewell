@props([
    // What it submits as; its error is found under it (files aren't refilled after a failed submit). With multiple,
    // [] is added if you leave it off. Optional with wire:model, which then names it.
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
    // Takes several files; beside the button it then says how many were chosen.
    'multiple' => false,
    // The file types it takes, as for the accept attribute: extensions and MIME types, e.g. ".pdf,image/*". The picker
    // only offers those, other files are turned away as they're picked, and the hint names them.
    'accept' => null,
    // The largest file it takes: 500KB, 5MB, 2GB, or a number of kilobytes like Laravel's max: rule. Bigger files are
    // turned away as they're picked, and the hint says the limit. Your Form Request must still check it.
    'maxSize' => null,
    // The button's text.
    'buttonLabel' => 'Choose file',
    // Shown beside the button while no file is chosen.
    'emptyText' => 'No file chosen',
    // Rewords or translates what it says, by key: tooBig (:name, :size), wrongType (:name), count (:count, shown
    // when several files are chosen).
    'messages' => [],
])

@php
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'file', attributes: $attributes);
    $id = $field->id;
    $limits = \Bladewell\Support\UploadLimits::from($maxSize, $accept);
    $hint = $limits->hint();
    $hintId = $hint ? "{$id}-hint" : null;
    $inputName = $name === null ? null : ($multiple ? preg_replace('/\[\]$/', '', $name).'[]' : $name);
    $messages = [
        'tooBig' => ':name is larger than :size.',
        'wrongType' => ':name isn\'t a file type this accepts.',
        'count' => ':count files',
        ...$messages,
    ];
    // wire:model: Livewire's renders are kept off the name and error the script writes (see the dropzone).
    $live = str_starts_with((string) array_key_first(\Bladewell\Support\FormField::binding($attributes)), 'wire:model');
    [$found, $bound] = $field->fromLivewire();
    $liveFiles = $live && $found ? (is_countable($bound) ? count($bound) : (int) filled($bound)) : null;
    // The input sits inside the button-look label, which opens the picker. A second <label for> it (the field's) is
    // one too many for some screen readers, so the field's is plain text, and both name the input: "Photo, Choose image".
    $labelledBy = $attributes->hasAny(['aria-label', 'aria-labelledby']) ? null : trim(($label ? "{$id}-label " : '')."{$id}-button");
@endphp

{{-- Compact: a button and the chosen file's name beside it, for tight forms and table rows. Same checks as the dropzone. --}}
<x-widget.field
    :required="$attributes->has('required')"
    :id="$id"
    :label="$label"
    :labels-control="false"
    :error="$field->errors"
    :info="$info"
    :disabled="$disabled"
    bare
    data-file-upload="button"
    :data-max-bytes="$limits->maxBytes ?? false"
    :data-accept="$accept ?? false"
    :data-messages="json_encode($messages)"
    :data-livewire-files="$liveFiles === null ? false : (string) $liveFiles"
    :class="$attributes->get('class')"
>
    <div @if ($live) wire:ignore @endif class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
        {{-- The input sits inside the button-look label, so a click there opens the picker and its focus rings the label. --}}
        <label @class([
            'bg-field text-foreground hover:bg-line has-[:focus-visible]:ring-primary relative inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-offset-2 ring-offset-surface',
            'cursor-pointer' => ! $disabled,
            'cursor-not-allowed opacity-60' => $disabled,
        ])>
            <x-widget.icon name="upload" class="size-4" />
            <span id="{{ $id }}-button">{{ $buttonLabel }}</span>
            <input
                type="file"
                id="{{ $id }}"
                @if ($inputName) name="{{ $inputName }}" @endif
                @if ($multiple) multiple @endif
                @if ($accept) accept="{{ $accept }}" @endif
                @disabled($disabled)
                {{ $field->controlAttributes($attributes->merge(['aria-describedby' => $hintId, 'aria-labelledby' => $labelledBy]), (bool) $info)->class(['sr-only']) }}
            >
        </label>
        <span data-file-name data-empty="{{ $emptyText }}" class="text-foreground/75 min-w-0 truncate text-sm">{{ $emptyText }}</span>
        <button type="button" data-file-clear aria-label="Clear the chosen file" hidden class="text-foreground/70 hover:text-foreground hover:bg-field focus-visible:ring-primary tap-target-y relative -ms-1 grid size-8 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2">
            <x-widget.icon name="x" class="size-4" />
        </button>
    </div>
    @if ($hint)
        <p id="{{ $hintId }}" class="text-foreground/70 mt-1.5 text-xs">{{ $hint }}</p>
    @endif
    <p data-file-error @if ($live) wire:ignore @endif role="alert" class="text-error mt-1 text-xs empty:hidden"></p>
</x-widget.field>
