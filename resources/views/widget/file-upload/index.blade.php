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
    // Takes several files, listed one per row with a remove button.
    'multiple' => false,
    // The file types it takes, as for the accept attribute: extensions and MIME types, e.g. ".pdf,image/*". The picker
    // only offers those, other files are turned away as they're picked, and the hint names them.
    'accept' => null,
    // The largest file it takes: 500KB, 5MB, 2GB, or a number of kilobytes like Laravel's max: rule. Bigger files are
    // turned away as they're picked, and the hint says the limit. Your Form Request must still check it.
    'maxSize' => null,
    // The most files it takes; more than 1 turns on multiple. Extra ones are listed with an error instead of added.
    'maxFiles' => null,
    // Direct upload: each file is posted to this URL (as "file") as soon as it's picked, with progress and retry. The
    // route stores it and returns JSON with its id, and the form submits the ids under the name instead of the files.
    'uploadUrl' => null,
    // Files already stored, listed as uploaded (after a failed submit, or when editing): [['id' => 7, 'name' => 'a.pdf',
    // 'size' => 1024], …], size in bytes. Each id is submitted under the name until it's removed. Needs upload-url.
    'uploaded' => [],
    // The text in the drop area, before the browse link.
    'prompt' => 'Drag files here or',
    // The browse link's text.
    'browse' => 'browse',
    // Rewords or translates what it says, by key: tooBig (:name, :size), wrongType (:name), tooMany (:count),
    // failed (:name), waiting, added (:count), removed (:name), uploadedOne (:name).
    'messages' => [],
])

@php
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'file', attributes: $attributes);
    $id = $field->id;
    $limits = \Bladewell\Support\UploadLimits::from($maxSize, $accept);
    $hint = $limits->hint();
    $maxFiles = $maxFiles !== null ? max(1, (int) $maxFiles) : null;
    $multiple = $multiple || ($maxFiles !== null && $maxFiles > 1);
    $baseName = $name !== null ? preg_replace('/\[\]$/', '', $name) : null;
    // upload-url: each file is sent there as soon as it's picked, and the form submits what the route returns (an
    // id per file) from hidden inputs. The file input itself then has no name, so the files aren't sent twice.
    $direct = $uploadUrl !== null;
    // Stored files submit their ids under the name. Without upload-url the file input has that name too, and a picked
    // file would replace or mix in with the ids.
    if (! $direct && filled($uploaded)) {
        throw new \InvalidArgumentException('<x-widget.file-upload uploaded="…"> needs upload-url: stored files submit their ids under the name, which picked files would otherwise share.');
    }
    $inputName = $direct || $baseName === null ? null : ($multiple ? "{$baseName}[]" : $baseName);
    $valueName = $baseName === null ? null : ($multiple ? "{$baseName}[]" : $baseName);
    // Files already stored (after a failed submit, or when editing): [['id' => 7, 'name' => 'a.pdf', 'size' => 1024], …].
    $uploaded = collect($uploaded)->map(static fn (mixed $file): array => [
        'id' => (string) (data_get($file, 'id') ?? ''),
        'name' => (string) (data_get($file, 'name') ?? ''),
        'size' => is_numeric(data_get($file, 'size')) ? \Bladewell\Support\UploadLimits::size((int) data_get($file, 'size')) : '',
    ])->filter(static fn (array $file): bool => $file['id'] !== '')->values();
    // Plain English like every component; pass messages="[…]" to reword or translate any of them.
    $messages = [
        'tooBig' => ':name is larger than :size.',
        'wrongType' => ':name isn\'t a file type this accepts.',
        'tooMany' => 'You can add up to :count files.',
        'failed' => 'Couldn\'t upload :name.',
        'waiting' => 'Wait for the uploads to finish.',
        'added' => ':count added.',
        'removed' => ':name removed.',
        'uploadedOne' => ':name uploaded.',
        ...$messages,
    ];
    $hintId = $hint ? "{$id}-hint" : null;
    // wire:model: the script uploads through Livewire whenever the list changes. Livewire's renders would wipe the list
    // the script draws, so it's left out of them, and data-livewire-files says how many files the property holds, so
    // the script can tell when PHP emptied it ($this->reset()) and empty the list too.
    $live = str_starts_with((string) array_key_first(\Bladewell\Support\FormField::binding($attributes)), 'wire:model');
    [$found, $bound] = $field->fromLivewire();
    $liveFiles = $live && $found ? (is_countable($bound) ? count($bound) : (int) filled($bound)) : null;
@endphp

{{--
    A real <input type="file">, so it submits without JavaScript too. resources/js/widget/file-upload adds drag and
    drop, the file list with remove buttons, the size and type checks (the server must still validate), and, with
    upload-url, uploading each file straight away with a progress bar.
--}}
<x-widget.field
    :required="$attributes->has('required')"
    :id="$id"
    :label="$label"
    :error="$field->errors"
    :info="$info"
    :disabled="$disabled"
    bare
    data-file-upload
    :data-max-bytes="$limits->maxBytes ?? false"
    :data-accept="$accept ?? false"
    :data-max-files="$maxFiles ?? false"
    :data-multiple="$multiple ? 'true' : false"
    :data-upload-url="$uploadUrl ?? false"
    :data-csrf="$direct ? csrf_token() : false"
    :data-value-name="$direct ? $valueName : false"
    :data-messages="json_encode($messages)"
    :data-livewire-files="$liveFiles === null ? false : (string) $liveFiles"
    :class="$attributes->get('class')"
>
    {{-- The input covers the whole area, so a click anywhere opens the picker and files dropped on it land in it,
         even before the script runs. The text is for the eye; screen readers get the label and the hint. --}}
    <div
        data-file-drop
        @class([
            'border-line-strong bg-field relative flex flex-col items-center justify-center gap-2 rounded-[20px] border-2 border-dashed px-6 py-8 text-center transition-colors',
            'hover:border-primary has-[:focus-visible]:border-primary data-dragging:border-primary data-dragging:bg-primary/5' => ! $disabled,
            'group-data-invalid/field:border-error group-data-invalid/field:bg-error/5',
            'opacity-60' => $disabled,
        ])
    >
        <x-widget.icon name="upload" class="text-muted size-7" />
        <p aria-hidden="true" class="text-foreground text-sm">
            {{ $prompt }} <span class="text-primary font-medium underline underline-offset-2">{{ $browse }}</span>
        </p>
        @if ($hint)
            <p id="{{ $hintId }}" class="text-foreground/70 text-xs">{{ $hint }}</p>
        @endif
        <input
            type="file"
            id="{{ $id }}"
            @if ($inputName) name="{{ $inputName }}" @endif
            @if ($multiple) multiple @endif
            @if ($accept) accept="{{ $accept }}" @endif
            @disabled($disabled)
            {{ $field->controlAttributes($attributes->merge(['aria-describedby' => $hintId]), (bool) $info)->class([
                'absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed',
            ]) }}
        >
    </div>

    <ul data-file-list @if ($live) wire:ignore @endif aria-label="{{ $label ? $label.': ' : '' }}chosen files" class="mt-3 grid gap-2 empty:hidden">
        @foreach ($uploaded as $file)
            <li data-file-item data-state="done" class="group/file border-line bg-surface flex items-center gap-3 rounded-2xl border px-4 py-3">
                <x-widget.icon name="file" class="text-muted size-5" />
                <div class="min-w-0 flex-1">
                    <p class="flex min-w-0 items-baseline gap-2">
                        <span data-file-name class="truncate text-sm font-medium">{{ $file['name'] }}</span>
                        <span data-file-size class="text-foreground/70 shrink-0 text-xs">{{ $file['size'] }}</span>
                    </p>
                </div>
                @if ($valueName)
                    <input type="hidden" name="{{ $valueName }}" value="{{ $file['id'] }}" {{ $attributes->only(['form']) }}>
                @endif
                {{-- Uploaded (with upload-url): a check, so a stored file doesn't look like one still waiting. --}}
                <x-widget.icon name="check" aria-hidden="true" class="text-success hidden size-4 shrink-0 group-data-[state=done]/file:block" />
                <button type="button" data-file-remove aria-label="Remove {{ $file['name'] }}" class="text-foreground/70 hover:text-foreground hover:bg-field focus-visible:ring-primary tap-target-y relative grid size-8 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2">
                    <x-widget.icon name="x" class="size-4" />
                </button>
            </li>
        @endforeach
    </ul>

    {{-- One file row, filled in by the script. It's here rather than in the JS so you can restyle it in the Blade you own. --}}
    <template data-file-template>
        <li data-file-item class="group/file border-line bg-surface flex items-center gap-3 rounded-2xl border px-4 py-3 data-[state=error]:border-error/40 data-[state=error]:bg-error/5">
            <x-widget.icon name="file" class="text-muted size-5" />
            <div class="min-w-0 flex-1">
                <p class="flex min-w-0 items-baseline gap-2">
                    <span data-file-name class="truncate text-sm font-medium"></span>
                    <span data-file-size class="text-foreground/70 shrink-0 text-xs"></span>
                </p>
                <p data-file-error class="text-error mt-0.5 text-xs" hidden></p>
                <div data-file-progress-track class="bg-field mt-2 h-1 overflow-hidden rounded-full" hidden>
                    <div data-file-progress class="bg-primary h-full w-0 transition-[width] duration-200 motion-reduce:transition-none"></div>
                </div>
            </div>
            {{-- Uploaded (with upload-url): a check, so a stored file doesn't look like one still waiting. --}}
            <x-widget.icon name="check" aria-hidden="true" class="text-success hidden size-4 shrink-0 group-data-[state=done]/file:block" />
            <button type="button" data-file-retry aria-label="Retry" hidden class="text-foreground/70 hover:text-foreground hover:bg-field focus-visible:ring-primary tap-target-y relative grid size-8 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2">
                <x-widget.icon name="refresh-cw" class="size-4" />
            </button>
            <button type="button" data-file-remove aria-label="Remove" class="text-foreground/70 hover:text-foreground hover:bg-field focus-visible:ring-primary tap-target-y relative grid size-8 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2">
                <x-widget.icon name="x" class="size-4" />
            </button>
        </li>
    </template>

    {{-- Always on the page, so what the script says here is read out: files added, removed, rejected, uploaded. --}}
    <p data-file-status role="status" class="sr-only"></p>
</x-widget.field>
