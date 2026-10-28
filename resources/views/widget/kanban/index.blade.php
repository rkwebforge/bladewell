@props([
    // Required: names the board for screen readers ("Orders") and in what's announced as cards move.
    'label' => null,
    // The board's id; scripts can reach it by it, so it must be unique. Made up when left out.
    'id' => null,
    // A route each move is sent to as JSON (POST: card, from, to, position, after), with the CSRF token. An answer
    // that isn't 2xx puts the card back and shows the answer's message. Without it, the move only fires kanban:move.
    'moveUrl' => null,
    // Shown, but nothing on it moves.
    'readonly' => false,
])

@php
    if (! is_string($label) || trim($label) === '') {
        throw new \InvalidArgumentException('<x-widget.kanban> needs a label, e.g. label="Orders": it names the board for screen readers and in what\'s announced as cards move.');
    }
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id ?? 'kanban', explicit: $id !== null);
@endphp

{{--
    Columns of cards that people move: a "Move…" menu on each card lists the other columns and the moves within its
    own. resources/js/widget/kanban builds that menu as it opens, moves the card, announces it and sends it to
    move-url. The script marks the board ready (data-kanban-ready), which shows the move buttons: without it a card
    can't be moved, so they stay hidden. wire:ignore.self keeps that mark through a Livewire render; the columns and
    cards inside still update.
--}}
<section
    id="{{ $id }}"
    aria-label="{{ $label }}"
    data-kanban
    @if ($moveUrl) data-move-url="{{ $moveUrl }}" data-csrf="{{ csrf_token() }}" @endif
    @if ($readonly) data-readonly @endif
    wire:ignore.self
    {{ $attributes->class(['group/kanban flex min-w-0 flex-col gap-3']) }}
>
    {{-- The script's: what it said, and the buttons it shows. wire:ignore keeps a render from emptying the one and hiding
         the others, such as the Undo for the move that render answered. --}}
    <div wire:ignore class="flex min-h-0 flex-wrap items-center gap-x-3 gap-y-2">
        {{-- What a move did, or why it didn't. In the accessibility tree while empty, so the first message is read out. --}}
        <p data-kanban-status role="status" class="text-foreground min-w-0 flex-1 text-sm empty:sr-only"></p>
        {{-- While a card is picked up: puts it back. --}}
        <x-widget.button size="sm" variant="secondary" data-kanban-cancel hidden>Cancel</x-widget.button>
        {{-- After a move: makes the opposite one. --}}
        <x-widget.button size="sm" variant="secondary" data-kanban-undo hidden>Undo</x-widget.button>
    </div>

    {{-- When the columns don't all fit, a button for each, to scroll it into view: on a phone, one column at a time.
         The script fills it from the columns; wire:ignore keeps a render from emptying it, and the script fills it again
         when the columns change. --}}
    <div data-kanban-jump wire:ignore hidden class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1"></div>

    {{-- Each column is as tall as its cards (items-start); a drag counts the space below a short one as its lane.
         Snapping to a column is turned off while a card is dragged: it would pull back against the scrolling at the
         edges. While the columns scroll, the script makes the row focusable, so the keyboard can scroll it too;
         wire:ignore.self keeps that through a render. --}}
    <div data-kanban-columns wire:ignore.self class="group-data-[kanban-moving]/kanban:snap-none focus-visible:outline-primary flex items-start snap-x snap-mandatory gap-4 overflow-x-auto overscroll-x-contain pb-2 outline-none focus-visible:outline-2 focus-visible:-outline-offset-2">
        {{ $slot }}
    </div>

    {{-- The menu items the script builds the "Move…" menu from, so they look like any dropdown's. --}}
    <template data-kanban-templates>
        <x-widget.dropdown.item data-kanban-item>Move</x-widget.dropdown.item>
        <x-widget.dropdown.divider />
    </template>
</section><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
