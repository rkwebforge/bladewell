@props([
    // Required: what a move sends as from and to, such as the stage's key or id. Unique within the board.
    'id' => null,
    // Required: the column's heading, also used in the "Move to …" menu and in what's announced.
    'title' => null,
    // Shown while the column has no cards.
    'empty' => 'Nothing here',
    // Shown, but no card moves into or out of it: a stage only your app sets, such as Delivered.
    'readonly' => false,
])

@php
    if (! is_scalar($id) || trim((string) $id) === '') {
        throw new \InvalidArgumentException('<x-widget.kanban.column> needs an id, such as the stage\'s key or id: a move sends it as from and to.');
    }
    if (! is_string($title) || trim($title) === '') {
        throw new \InvalidArgumentException("<x-widget.kanban.column id=\"{$id}\"> needs a title: it heads the column and names it in the move menu.");
    }
    // A derived id only: the column's own id is your data and may hold anything; it goes in data-kanban-column instead.
    $domId = app(\Bladewell\Support\ElementIds::class)->claim('kanban-column');
    // Counted from the cards drawn, so the number is there before the script runs; the script keeps it in step.
    $count = substr_count((string) $slot, 'data-kanban-card=');
@endphp

<section
    data-kanban-column="{{ $id }}"
    data-title="{{ $title }}"
    aria-labelledby="{{ $domId }}-title"
    @if ($readonly) data-readonly @endif
    {{ $attributes->class(['group/column bg-field flex w-72 max-w-[85vw] shrink-0 snap-start flex-col rounded-2xl p-3']) }}
>
    <header class="flex items-center justify-between gap-2 px-1 pb-3">
        <h3 id="{{ $domId }}-title" class="truncate text-sm font-semibold">{{ $title }}</h3>
        {{-- The list says how many cards it holds; this is for the eye. --}}
        <span data-kanban-count aria-hidden="true" class="text-muted shrink-0 text-xs tabular-nums">{{ $count }}</span>
    </header>
    <ol data-kanban-cards aria-labelledby="{{ $domId }}-title" class="flex min-h-12 flex-col gap-2">{{ $slot }}</ol>
    <p data-kanban-empty class="text-muted px-1 py-3 text-center text-sm group-has-[[data-kanban-card]]/column:hidden">{{ $empty }}</p>
</section><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
