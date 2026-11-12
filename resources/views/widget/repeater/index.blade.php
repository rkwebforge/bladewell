@props([
    // The array the rows submit as, as their fields name it: "lines" for lines[{key}][product]. Errors for the array
    // itself (lines: min, max, required) show under the rows.
    'name',
    // Shown above the rows, and the group's name for screen readers.
    'label' => null,
    // The fewest rows: at this many, the remove buttons are disabled. Check it on the server too, with min: on the array.
    'min' => 0,
    // The most rows: at this many, the add button is disabled. Check it on the server too, with max: on the array.
    'max' => null,
    // The add button's text.
    'addLabel' => 'Add row',
    // What a row is called, numbered after it: "Row 1", "Line 2". Also starts each row's button names, "Remove Row 2".
    'rowLabel' => 'Row',
    // The remove button's name, followed by the row's: "Remove Row 2".
    'removeLabel' => 'Remove',
    // The move up button's name, followed by the row's.
    'moveUpLabel' => 'Move up',
    // The move down button's name, followed by the row's.
    'moveDownLabel' => 'Move down',
    // Read out after adding a row. :row is the row's name, "Row 4".
    'addedMessage' => ':row added',
    // Read out after removing a row.
    'removedMessage' => ':row removed',
    // Read out after moving a row. :row is its name before the move, :position where it is now.
    'movedMessage' => ':row moved to position :position',
    // Rows on one line, the buttons beside the fields and no title above them (screen readers still hear "Row 2"), for
    // short rows: an email and a role, a key and a value. Fields take equal columns; wider screens fit more per line.
    'compact' => false,
    // The move up and move down buttons. false for rows whose order means nothing, as a list of filters.
    'movable' => true,
    // The key the template row is rendered with, replaced by a new one for each row added. A repeater inside another
    // needs its own, e.g. "__option__".
    'placeholder' => '__row__',
    // The error message(s) for the array. Defaults to the ones Laravel has for the name.
    'error' => null,
    // The error bag to read errors from.
    'bag' => 'default',
    // In a Livewire component: the method the add button calls, e.g. "addLine". With any of the three actions the
    // component's array owns the rows, and the script only keeps focus, the buttons' states and the announcements right.
    // A button whose action isn't set is left out: add-action alone gives rows that can't be removed or moved.
    'addAction' => null,
    // In a Livewire component: the method a remove button calls with the row's key, e.g. "removeLine".
    'removeAction' => null,
    // In a Livewire component: the method a move button calls with the row's key and -1 (up) or 1 (down), e.g. "moveLine".
    'moveAction' => null,
])

@php
    // Its id is the array's, so an error summary's link to "lines" lands here.
    $field = \Bladewell\Support\FormField::make($name, null, $errors ?? null, $error, $bag, 'repeater');
    $livewire = $addAction !== null || $removeAction !== null || $moveAction !== null;

    // Rows read these through @aware, which looks further out when this repeater leaves one unset: inside another
    // repeater's row they'd take the outer one's, naming an answer's button "Remove Question 2" or calling its method.
    // The rows are drawn by now, so say which to set rather than ship them wrong.
    $read = ['addAction' => $addAction, 'compact' => $compact, 'movable' => $movable, 'rowLabel' => $rowLabel, 'removeLabel' => $removeLabel, 'moveUpLabel' => $moveUpLabel, 'moveDownLabel' => $moveDownLabel, 'placeholder' => $placeholder, 'removeAction' => $removeAction, 'moveAction' => $moveAction];
    foreach ($read as $prop => $own) {
        $seen = $__env->getConsumableComponentData($prop, $own);
        if ($seen !== $own) {
            $attribute = \Illuminate\Support\Str::kebab($prop);
            throw new \InvalidArgumentException("<x-widget.repeater name=\"{$name}\"> sits inside a component that sets {$attribute}=\"{$seen}\", which its rows would take as their own. Give this repeater its own {$attribute}.");
        }
    }
@endphp

{{--
    resources/js/repeater adds, removes and moves rows, and keeps the buttons' disabled states and the announcements up
    to date. Rows are numbered by a CSS counter, so their names follow the order with no script and no flash.
--}}
<fieldset
    data-repeater="{{ $name }}"
    data-placeholder="{{ $placeholder }}"
    data-min="{{ (int) $min }}"
    @if ($max !== null) data-max="{{ (int) $max }}" @endif
    data-row-label="{{ $rowLabel }}"
    data-added="{{ $addedMessage }}"
    data-removed="{{ $removedMessage }}"
    data-moved="{{ $movedMessage }}"
    @if ($livewire) data-repeater-livewire @endif
    id="{{ $field->id }}"
    @if ($field->hasError()) aria-describedby="{{ $field->errorId() }}" @endif
    {{ $attributes->class(['m-0 grid min-w-0 gap-4 border-0 p-0']) }}
>
    @if ($label)
        <legend class="text-style-2 text-foreground mb-[11.5px] p-0">{{ $label }}</legend>
    @endif

    <div data-repeater-rows @class(['grid [counter-reset:repeater-row]', 'gap-4' => ! $compact, 'gap-3' => $compact])>
        {{ $slot }}
    </div>

    {{-- The empty slot: shown while there are no rows, by CSS, so it comes and goes with them with no script. --}}
    @isset($empty)
        <div data-repeater-empty class="text-muted hidden [[data-repeater-rows]:not(:has(>[data-repeater-row]))+&]:block">{{ $empty }}</div>
    @endisset

    {{-- data-repeater-error: removed once a row is added or removed, as the message was about the rows before. --}}
    @if ($field->hasError())
        <div data-repeater-error class="flex items-start gap-1">
            <x-widget.icon name="circle-alert" class="text-error mt-0.5 size-3.5" />
            <p id="{{ $field->errorId() }}" class="text-error break-words">{{ implode(' ', $field->errors) }}</p>
        </div>
    @endif

    {{-- In Livewire only the component adds rows: without add-action there'd be a button that does nothing. --}}
    @if (! $livewire || $addAction !== null)
        <div>
            <x-widget.button variant="secondary" size="sm" icon-start="plus" data-repeater-add :wire:click="$addAction">{{ $addLabel }}</x-widget.button>
        </div>
    @endif

    <span data-repeater-status role="status" class="sr-only"></span>
</fieldset>
