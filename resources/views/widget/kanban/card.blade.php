@props([
    // Required: what a move sends as card, such as the order's id. Unique within the board.
    'id' => null,
    // Required: the card's name in its move button ("Move Order #1042") and in what's announced.
    'label' => null,
    // Can't be moved, by anyone, however it's asked.
    'locked' => false,
])

@php
    if (! is_scalar($id) || trim((string) $id) === '') {
        throw new \InvalidArgumentException('<x-widget.kanban.card> needs an id, such as the order\'s id: a move sends it as card.');
    }
    if (! is_string($label) || trim($label) === '') {
        throw new \InvalidArgumentException("<x-widget.kanban.card id=\"{$id}\"> needs a label, e.g. label=\"Order #1042\": it names the card's move button and what's announced.");
    }
@endphp

{{-- wire:key: a Livewire render matches cards by id, so a moved card isn't swapped for its neighbour. --}}
<li
    data-kanban-card="{{ $id }}"
    data-label="{{ $label }}"
    @if ($locked) data-locked @endif
    wire:key="kanban-card-{{ $id }}"
    {{ $attributes->class([
        'group/card border-line bg-surface relative rounded-xl border p-3 text-sm shadow-xs transition-opacity aria-busy:opacity-60 motion-reduce:transition-none',
        // Picked up to be placed with the keys or a tap.
        'data-kanban-picked:ring-primary data-kanban-picked:shadow-lg data-kanban-picked:ring-2',
        // Dragged: a copy follows the pointer, and this waits, faded, where letting go would put it.
        'data-kanban-dragging:border-primary data-kanban-dragging:border-dashed data-kanban-dragging:opacity-40',
        // Let go: hidden for the moment the copy takes to glide into its place.
        'data-kanban-landing:opacity-0',
        // A mouse can drag it once the script is ready, except from a readonly column.
        'group-data-[kanban-ready]/kanban:cursor-grab group-data-[readonly]/column:cursor-auto' => ! $locked,
        // So can a finger held on it: on a touch screen that holding mustn't select its text or bring up the phone's
        // menu. Not pointer-coarse:, which Tailwind 4.0 doesn't have.
        'group-data-[kanban-ready]/kanban:[@media(pointer:coarse)]:select-none group-data-[kanban-ready]/kanban:[-webkit-touch-callout:none] group-data-[readonly]/column:[@media(pointer:coarse)]:select-auto group-data-[readonly]/column:[-webkit-touch-callout:default]' => ! $locked,
    ]) }}
>
    <div class="min-w-0 pe-9">{{ $slot }}</div>
    @unless ($locked)
        {{-- Hidden until the script is ready, and in a readonly column: there it couldn't move the card. The menu is
             empty here; the script fills it as it opens, from the columns as they are then. --}}
        <div class="absolute end-1.5 top-1.5 hidden group-data-[kanban-ready]/kanban:block group-data-[readonly]/column:hidden">
            <x-widget.dropdown :label="'Move '.$label" icon="grip-vertical" size="sm" align="end" data-kanban-move />
        </div>
    @endunless
</li><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
