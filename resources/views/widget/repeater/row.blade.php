@props([
    // The row's key, as in its fields' names: lines[{key}][product]. An existing record's id, or the key a row
    // added in the browser was given. The repeater's placeholder makes this the template new rows are copied from.
    'key',
])

{{-- The repeater's own props, so a row names its buttons, knows whether it is the template and what Livewire calls. --}}
@aware([
    'name',
    'rowLabel' => 'Row',
    'removeLabel' => 'Remove',
    'moveUpLabel' => 'Move up',
    'moveDownLabel' => 'Move down',
    'placeholder' => '__row__',
    'compact' => false,
    'movable' => true,
    'addAction' => null,
    'removeAction' => null,
    'moveAction' => null,
])

@php
    // In a name, a dot or bracket starts another level: lines[a.b] files its errors and old input under lines.a.b.
    if (strpbrk((string) $key, '.[]') !== false) {
        throw new \InvalidArgumentException("<x-widget.repeater.row key=\"{$key}\"> can't take a dot or bracket: in the fields' names it would start another level, losing the row's old input and errors. Key the rows by an id instead.");
    }
    $template = (string) $key === $placeholder;
    // In Livewire the component's methods do the work, so a button whose method isn't set would do nothing: left out.
    $livewire = $addAction !== null || $removeAction !== null || $moveAction !== null;
    $removable = ! $livewire || $removeAction !== null;
    $movable = $movable && (! $livewire || $moveAction !== null);
    // Built from the name and key, so the template's ids carry the placeholder and every row added gets its own.
    $id = app(\Bladewell\Support\ElementIds::class)->claim(\Bladewell\Support\FormField::idFor("{$name}[{$key}]"));
    // In Livewire the component's methods move and remove rows; the key goes in as a JS string, quoted and escaped.
    $call = fn (?string $method, string ...$arguments): ?string => $method === null ? null : $method.'('.implode(', ', [\Illuminate\Support\Js::from((string) $key)->toHtml(), ...$arguments]).')';
@endphp

@if ($template)
    <template data-repeater-template>
@endif
{{-- Each button is named by itself and the row's title: "Remove Row 2", the number coming from the CSS counter. --}}
<div data-repeater-row="{{ $key }}" @if ($livewire) wire:key="{{ $id }}" @endif role="group" aria-labelledby="{{ $id }}-title" {{ $attributes->class([
    '[counter-increment:repeater-row]',
    'border-line rounded-2xl border p-4' => ! $compact,
    // Compact: the buttons follow the fields, below them on a phone (a line under each row tells rows apart there) and
    // beside them from sm up, level with the boxes.
    'border-line flex flex-col gap-2 border-b pb-3 sm:flex-row sm:gap-3 sm:border-0 sm:pb-0' => $compact,
]) }}>
    {{-- A card's title and buttons come first; a compact row's buttons come after its fields in the markup too, so Tab
         reaches them in the order they're seen. --}}
    @foreach ($compact ? ['fields', 'title'] : ['title', 'fields'] as $part)
        @if ($part === 'title')
            <div @class(['flex items-center justify-between gap-2', 'mb-4' => ! $compact, 'justify-end sm:items-start sm:pt-[39.5px]' => $compact])>
                <p id="{{ $id }}-title" @class(['text-style-2 text-foreground font-medium', 'sr-only' => $compact])>
                    <span>{{ $rowLabel }}</span> <span class="after:content-[counter(repeater-row)]"></span>
                </p>
                <div data-repeater-actions class="flex shrink-0 gap-1">
                    @if ($movable)
                        <x-widget.button variant="neutral" size="sm" icon="arrow-up" :label="$moveUpLabel" id="{{ $id }}-up" aria-labelledby="{{ $id }}-up {{ $id }}-title" data-repeater-action="up" :wire:click="$call($moveAction, '-1')" />
                        <x-widget.button variant="neutral" size="sm" icon="arrow-down" :label="$moveDownLabel" id="{{ $id }}-down" aria-labelledby="{{ $id }}-down {{ $id }}-title" data-repeater-action="down" :wire:click="$call($moveAction, '1')" />
                    @endif
                    @if ($removable)
                        <x-widget.button variant="neutral" size="sm" icon="trash" :label="$removeLabel" id="{{ $id }}-remove" aria-labelledby="{{ $id }}-remove {{ $id }}-title" data-repeater-action="remove" :wire:click="$call($removeAction)" />
                    @endif
                </div>
            </div>
        @else
            {{-- Compact columns are at least as wide as a field's frame (min-w-44), so none overflows its column. --}}
            <div data-repeater-fields @class(['grid', 'gap-4 sm:grid-cols-2' => ! $compact, 'min-w-0 flex-1 gap-3 sm:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]' => $compact])>
                {{ $slot }}
            </div>
        @endif
    @endforeach
</div>
@if ($template)
    </template>
@endif
