@props([
    // Required: what the order and a move send for it, such as the question's id. Unique within the list.
    'id' => null,
    // Required: the item's name on its handle and Move buttons ("Move Shipping times") and in what's announced.
    'label' => null,
])

{{-- The list's own props, so an item draws itself as a row or a tile, and leaves out what can't move it. --}}
@aware([
    'layout' => 'list',
    'readonly' => false,
    'disabled' => false,
])

@php
    if (! is_scalar($id) || trim((string) $id) === '') {
        throw new \InvalidArgumentException('<x-widget.sortable.item> needs an id, such as the question\'s id: the order and each move send it.');
    }
    if (! is_string($label) || trim($label) === '') {
        throw new \InvalidArgumentException("<x-widget.sortable.item id=\"{$id}\"> needs a label, e.g. label=\"Shipping times\": it names the item's handle and buttons, and what's announced.");
    }
    $grid = $layout === 'grid';
    $movable = ! $readonly && ! $disabled;
    // A derived id only: the item's own id is your data and may hold anything; it goes in data-sortable-item instead.
    $domId = app(\Bladewell\Support\ElementIds::class)->claim('sortable-item');
    // aria-disabled, not disabled, at the ends: a disabled button drops focus, and the one just pressed would lose it.
    $buttonClass = 'aria-disabled:cursor-not-allowed aria-disabled:opacity-50 aria-disabled:active:scale-100';
@endphp

{{-- wire:key: a Livewire render matches items by id, so a moved item isn't swapped for its neighbour. --}}
<li
    data-sortable-item="{{ $id }}"
    data-label="{{ $label }}"
    wire:key="sortable-item-{{ $id }}"
    {{ $attributes->class([
        // Opacity and box-shadow: the faded states below, and the ring after a move fading out.
        'border-line bg-surface relative flex rounded-xl border text-sm shadow-xs transition-[opacity,box-shadow] aria-busy:opacity-60 motion-reduce:transition-none',
        'items-center gap-2 p-2' => ! $grid,
        'flex-col gap-2 p-2' => $grid,
        // Picked up to be placed with the arrow keys.
        'data-sortable-picked:ring-primary data-sortable-picked:shadow-lg data-sortable-picked:ring-2',
        // Dragged: a copy follows the pointer, and this waits, faded, where letting go would put it.
        'data-sortable-dragging:border-primary data-sortable-dragging:border-dashed data-sortable-dragging:opacity-40',
        // Let go: hidden for the moment the copy takes to glide into its place.
        'data-sortable-landing:opacity-0',
        // Just moved, for a moment, so the eye finds where it went; red when the server sent it back (the script says why).
        'data-sortable-moved:ring-primary data-sortable-moved:ring-2 data-sortable-refused:ring-error data-sortable-refused:ring-2',
    ]) }}
>
    @if ($grid)
        <div class="min-w-0">{{ $slot }}</div>
    @endif

    {{-- A row's handle leads it and its buttons end it; a tile has its content on top, then a line with both. --}}
    @if ($movable)
        <div @class(['contents' => ! $grid, 'hidden items-center justify-between gap-1 group-data-[sortable-ready]/sortable:flex' => $grid])>
            {{-- Drags with a mouse, a pen or a finger at once: touch-none keeps the finger's moves for the drag, not the
                 page's scrolling, which the rest of the item still does. Pressed (Space, Enter or a click), it picks the
                 item up for the arrow keys. The script points its description at the list's instructions. --}}
            <button
                type="button"
                data-sortable-handle
                aria-label="Move {{ $label }}"
                aria-pressed="false"
                title="Drag to move"
                class="text-muted hover:bg-field hover:text-foreground focus-visible:ring-primary aria-pressed:bg-primary/10 aria-pressed:text-primary hidden size-8 shrink-0 cursor-grab touch-none place-items-center rounded-lg outline-none select-none group-data-[sortable-ready]/sortable:grid focus-visible:ring-2 [-webkit-touch-callout:none]"
            ><x-widget.icon name="grip-vertical" class="size-4" /></button>

            @unless ($grid)
                <div class="min-w-0 flex-1">{{ $slot }}</div>
            @endunless

            {{-- Each named by itself and the item: "Move up, Shipping times". A tile's go back and forth along the rows,
                 their arrows turned for a right-to-left page. --}}
            <span id="{{ $domId }}-label" hidden>{{ $label }}</span>
            <div data-sortable-actions class="hidden shrink-0 gap-1 group-data-[sortable-ready]/sortable:flex">
                @if ($grid)
                    <x-widget.button variant="neutral" size="sm" icon="arrow-left" label="Move earlier" id="{{ $domId }}-up" aria-labelledby="{{ $domId }}-up {{ $domId }}-label" data-sortable-action="up" class="{{ $buttonClass }} rtl:-scale-x-100" />
                    <x-widget.button variant="neutral" size="sm" icon="arrow-right" label="Move later" id="{{ $domId }}-down" aria-labelledby="{{ $domId }}-down {{ $domId }}-label" data-sortable-action="down" class="{{ $buttonClass }} rtl:-scale-x-100" />
                @else
                    <x-widget.button variant="neutral" size="sm" icon="arrow-up" label="Move up" id="{{ $domId }}-up" aria-labelledby="{{ $domId }}-up {{ $domId }}-label" data-sortable-action="up" class="{{ $buttonClass }}" />
                    <x-widget.button variant="neutral" size="sm" icon="arrow-down" label="Move down" id="{{ $domId }}-down" aria-labelledby="{{ $domId }}-down {{ $domId }}-label" data-sortable-action="down" class="{{ $buttonClass }}" />
                @endif
            </div>
        </div>
    @elseif (! $grid)
        <div class="min-w-0 flex-1">{{ $slot }}</div>
    @endif
</li><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
