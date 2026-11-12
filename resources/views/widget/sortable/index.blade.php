@props([
    // Required: shown above the list, and its name for screen readers and in what's announced as items move.
    'label' => null,
    // What the order submits as: the items' ids, first to last, as name[]. Its error and old input are found under it.
    // Optional with wire:model, which then names it; leave both out when move-url or a script saves each move.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // list: one item under another. grid: tiles in rows that wrap, such as images, ordered as they read.
    'layout' => 'list',
    // A route each move is sent to as JSON (POST: item, position, after, order, and version when set), with the CSRF
    // token, one request at a time. An answer that isn't 2xx puts the list back in the order last saved and shows the
    // answer's message; a 409 also offers to reload. Without it, a move fires sortable:move and, with a name, changes
    // what the form submits.
    'moveUrl' => null,
    // With move-url: the order's version as this page drew it, such as the parent's updated_at or a counter. Sent with
    // each move, so your route can answer 409 when someone else has reordered since. A 2xx answer of
    // {"version": …} gives the next one.
    'version' => null,
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the list.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Shown, and submitted, but nothing in it moves. Also for a filtered or paged list: an order there means nothing for
    // the items not shown.
    'readonly' => false,
    // Greyed out: nothing moves, and the order isn't submitted.
    'disabled' => false,
])

@php
    if (! is_string($label) || trim($label) === '') {
        throw new \InvalidArgumentException('<x-widget.sortable> needs a label, e.g. label="Questions": it names the list for screen readers and in what\'s announced as items move.');
    }
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($layout, ['list', 'grid'], true)) {
        throw new \InvalidArgumentException("Unknown layout [{$layout}] for <x-widget.sortable>. Use one of: list, grid.");
    }
    $readonly = $readonly && ! $disabled;
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'sortable', attributes: $attributes);
    $bindings = $field->bindings($attributes);
    $submits = $name !== null || $bindings->isNotEmpty();
    // Its elements' ids. With nothing to name it, one made up in the same order on every render, not FormField's random
    // one: Livewire matches elements by id, and a new one each render would draw the list afresh, dropping focus.
    $domId = $submits || $id !== null ? $field->id : app(\Bladewell\Support\ElementIds::class)->claim('sortable');
    // The items' ids, from the items drawn, so the order submits before the script runs. After a failed submit (or with
    // wire:model, from the property) the order sent comes first: the script puts the items in that order as it starts,
    // so the person's reordering isn't lost to an error elsewhere in the form.
    preg_match_all('/data-sortable-item="([^"]*)"/', (string) $slot, $found);
    $ids = array_map(static fn (string $value): string => html_entity_decode($value, ENT_QUOTES | ENT_HTML5), $found[1]);
    $sent = $submits ? $field->old() : null;
    if (is_array($sent)) {
        $sent = array_values(array_unique(array_map(static fn (mixed $value): string => (string) ($value instanceof \BackedEnum ? $value->value : $value), array_filter($sent, static fn (mixed $value): bool => is_scalar($value) || $value instanceof \BackedEnum))));
        $ids = [...array_values(array_intersect($sent, $ids)), ...array_values(array_diff($ids, $sent))];
    }
    // As FormField::aria(), by this list's ids: the error, or else the hint, then the caller's own.
    $describedBy = implode(' ', array_filter([$field->hasError() ? "{$domId}-error" : null, $info && ! $field->hasError() ? "{$domId}-info" : null, $attributes->get('aria-describedby')]));
@endphp

{{--
    Without the script nothing moves: the list shows the saved order, and with a name submits it unchanged.
    Items people put in order, by a handle that drags or picks the item up for the arrow keys, and by Move buttons on
    each. resources/js/sortable moves them, announces each move, keeps focus where it was, sends it to move-url, and
    keeps the hidden select (what submits as name[] and what wire:model binds) in the new order. The script marks the
    list ready (data-sortable-ready), which shows the handles and buttons: without it nothing could move, so they stay
    hidden. wire:ignore.self keeps that mark through a Livewire render; the items inside still update.
--}}
<x-widget.field :id="$domId" :label="$label" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" :labels-control="false" bare :class="$attributes->get('class')">
    <div
        data-sortable
        data-label="{{ $label }}"
        data-layout="{{ $layout }}"
        @if ($moveUrl) data-move-url="{{ $moveUrl }}" data-csrf="{{ csrf_token() }}" @endif
        @if ($moveUrl && $version !== null) data-version="{{ $version }}" @endif
        @if ($readonly) data-readonly @endif
        @if ($disabled) data-disabled @endif
        wire:ignore.self
        {{ $field->forwarded($attributes)->except(array_keys($bindings->getAttributes()))->merge(['class' => 'group/sortable flex min-w-0 flex-col gap-3']) }}
    >
        {{-- role="list": Safari drops a list's role once its markers are styled away. --}}
        <ol
            id="{{ $domId }}"
            data-sortable-items
            role="list"
            aria-labelledby="{{ $domId }}-label"
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($field->hasError()) aria-invalid="true" @endif
            @class([
                'm-0 list-none p-0',
                'flex flex-col gap-2' => $layout === 'list',
                'grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-3' => $layout === 'grid',
                'opacity-60' => $disabled,
            ])
        >{{ $slot }}</ol>

        {{-- The empty slot: shown while there are no items, by CSS, so it comes and goes with them with no script. --}}
        @isset($empty)
            <div data-sortable-empty class="text-muted hidden text-sm [[data-sortable-items]:not(:has(>[data-sortable-item]))+&]:block">{{ $empty }}</div>
        @endisset

        {{-- The script's: what it said, and the buttons it shows. wire:ignore keeps a render from emptying the one and
             hiding the others, such as the Undo for the move that render answered. With nothing to show it takes back
             the gap above it. --}}
        <div wire:ignore class="flex min-h-0 flex-wrap items-center gap-x-3 gap-y-2 [&:not(:has(p:not(:empty),button:not([hidden])))]:-mt-3">
            {{-- What a move did, or why it didn't. In the accessibility tree while empty, so the first message is read out. --}}
            <p data-sortable-status role="status" class="text-foreground min-w-0 flex-1 text-sm empty:sr-only"></p>
            {{-- While an item is picked up: puts it back. --}}
            <x-widget.button size="sm" variant="secondary" data-sortable-cancel hidden>Cancel</x-widget.button>
            {{-- After a move: makes the opposite one. --}}
            <x-widget.button size="sm" variant="secondary" data-sortable-undo hidden>Undo</x-widget.button>
            {{-- After a 409: someone else reordered first. Shows the order as it's saved now. --}}
            <x-widget.button size="sm" variant="secondary" data-sortable-reload hidden>Reload</x-widget.button>
        </div>

        {{-- How the handles work, for screen readers: each handle points here once the script is ready. --}}
        <p id="{{ $domId }}-help" data-sortable-help class="sr-only">Press Space or Enter to pick it up, the arrow keys to move it, and Space or Enter again to put it down. Escape puts it back.</p>

        {{-- The order as it submits (name[]), and what wire:model binds. --}}
        @if ($submits)
            <select multiple hidden data-sortable-value @if ($name) name="{{ $name }}[]" @endif @disabled($disabled) {{ $bindings }}>
                @foreach ($ids as $value)
                    <option value="{{ $value }}" selected>{{ $value }}</option>
                @endforeach
            </select>
        @endif
    </div>
</x-widget.field><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
