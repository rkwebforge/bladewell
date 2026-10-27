@props([
    // The node's text, which also names it for screen readers.
    'label',
    // Short text at the end of the row, such as a count or a level. Read out with the label.
    'meta' => null,
    // An icon before the label, by name, as the icon widget takes it.
    'icon' => null,
    // A URL: the node is a link, followed on click or Enter.
    'href' => null,
    // Starts open, showing what's under it.
    'open' => false,
    // checkbox look: what this node submits when ticked. A node without one only groups the ones under it.
    'value' => null,
    // checkbox look: can't be ticked or unticked, and ticking a parent leaves it as it is.
    'disabled' => false,
    // Where its children come from when it's first opened: a URL in your app that returns tree items as HTML.
    // For trees too big to send at once, like a referral network.
    'childrenUrl' => null,
])

{{-- Which look the tree has, from the tree around it: on the page, or the one children fetched later come back in. --}}
@aware(['variant' => 'list'])

@php
    $branch = $slot->isNotEmpty() || $childrenUrl !== null;
    $open = $open && $slot->isNotEmpty();
    // Named by its label (and meta), not by everything inside it, which would include every child's text too. Random
    // rather than counted: children fetched later are rendered in another request, whose counting starts over.
    $ids = 'tree-item-'.\Illuminate\Support\Str::random(8);
    $value = $value instanceof \BackedEnum ? $value->value : $value;
    $checkbox = $variant === 'checkbox';
    $card = in_array($variant, ['cards', 'org'], true);

    // Each look's row. Only the tree's own look is rendered, so a node carries nothing it doesn't use.
    $looks = [
        // A compact row, like a file explorer.
        'list' => 'items-center rounded-lg px-2 py-1.5 hover:bg-field',
        'checkbox' => 'items-center rounded-lg px-2 py-1.5 hover:bg-field cursor-pointer data-disabled:cursor-not-allowed',
        // A card, with the open arrow at its end.
        'cards' => 'border-line bg-surface w-72 items-start rounded-2xl border p-4 shadow-sm hover:border-line-strong',
        // A smaller card, centred over its children, with the open arrow under its text.
        'org' => 'border-line bg-surface min-w-36 max-w-56 flex-col items-center rounded-2xl border px-4 py-3 text-center shadow-sm hover:border-line-strong',
    ];
    $look = $looks[$variant] ?? $looks['list'];
@endphp

{{--
    The script mirrors each node's state onto its row (data-expanded, data-busy, data-checked, data-mixed), where only
    that row's own icons see it.
    wire:ignore.self: whether it's open or ticked, and the ids, are the person's once the page is up; a Livewire render
    would put back the server's. What's inside still updates.
--}}
<li
    role="treeitem"
    data-tree-item
    tabindex="-1"
    wire:ignore.self
    aria-labelledby="{{ $ids }}-label @if ($meta !== null) {{ $ids }}-meta @endif"
    @isset($details) aria-describedby="{{ $ids }}-details" @endisset
    @if ($branch) aria-expanded="{{ $open ? 'true' : 'false' }}" @endif
    @if ($childrenUrl) data-children-url="{{ $childrenUrl }}" @endif
    @if ($value !== null) data-value="{{ $value }}" @endif
    @if ($disabled) aria-disabled="true" @endif
    {{ $attributes->class(['outline-none [&:focus-visible>[data-tree-row]]:ring-2 [&:focus-visible>[data-tree-row]]:ring-primary']) }}
>
    <div
        data-tree-row
        wire:ignore.self
        @if ($open) data-expanded @endif
        @if ($disabled) data-disabled @endif
        @class(['group/row text-foreground relative flex gap-2 text-sm transition-colors data-disabled:opacity-60', $look, 'cursor-pointer' => $branch || $href])
    >
        {{-- The open arrow, or the spinner while children load. Empty on a list's leaf, as a spacer so labels line up. --}}
        @if ($branch || ! $card)
            <span data-tree-toggle aria-hidden="true" @class(['text-foreground/70 grid size-5 shrink-0 place-items-center', 'order-last' => $card])>
                @if ($branch)
                    <x-widget.icon name="chevron-down" class="size-4 -rotate-90 transition-transform duration-200 group-data-busy/row:hidden group-data-expanded/row:rotate-0 motion-reduce:transition-none rtl:rotate-90 rtl:group-data-expanded/row:rotate-0" />
                    <span class="border-line border-t-primary hidden size-4 animate-spin rounded-full border-2 group-data-busy/row:block"></span>
                @endif
            </span>
        @endif

        @if ($checkbox)
            {{-- The tick box. The node itself carries aria-checked; this is only its picture. --}}
            <span aria-hidden="true" class="border-muted bg-surface text-on-primary group-data-checked/row:border-primary-fill group-data-checked/row:bg-primary-fill group-data-mixed/row:border-primary-fill group-data-mixed/row:bg-primary-fill grid size-5 shrink-0 place-items-center rounded-md border">
                <x-widget.icon name="check" class="hidden size-3.5 stroke-3 group-data-checked/row:block" />
                <x-widget.icon name="minus" class="hidden size-3.5 stroke-3 group-data-mixed/row:block" />
            </span>
        @endif

        @if ($icon)
            <x-widget.icon :name="$icon" class="text-foreground/70 size-4 shrink-0 {{ $variant === 'cards' ? 'mt-0.5' : '' }}" />
        @endif

        <span class="min-w-0 flex-1">
            <span id="{{ $ids }}-label" wire:ignore.self @class(['block break-words', 'font-semibold' => $card])>
                {{-- tabindex -1: the node takes focus, not the link; Enter on the node follows it (see the script). --}}
                @if ($href)
                    <a href="{{ $href }}" tabindex="-1" class="hover:text-primary underline-offset-4 hover:underline">{{ $label }}</a>
                @else
                    {{ $label }}
                @endif
            </span>
            @isset($details)
                <span id="{{ $ids }}-details" wire:ignore.self class="text-foreground/70 mt-1 block text-xs">{{ $details }}</span>
            @endisset
        </span>

        @if ($meta !== null)
            <span id="{{ $ids }}-meta" wire:ignore.self @class(['text-muted shrink-0 text-xs tabular-nums', 'mt-0.5' => $variant === 'org'])>{{ $meta }}</span>
        @endif
    </div>

    @if ($branch)
        {{-- Children fetched on first open are the script's: wire:ignore keeps a Livewire render from emptying them. Others
             still render, but wire:ignore.self keeps hidden as the person left it: open or closed is theirs, as on the node. --}}
        <ul role="group" data-tree-group @if (! $open) hidden @endif @if ($childrenUrl) wire:ignore @else wire:ignore.self @endif>
            {{ $slot }}
        </ul>
    @endif
</li>
