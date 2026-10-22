@props([
    // A page to open when it's chosen. Without one, choosing it clicks it: give it wire:click, or listen for the click.
    'href' => null,
    // An icon before the label, by name, as the icon widget takes it.
    'icon' => null,
    // More words it's found by, besides its label: "settings account profile".
    'keywords' => null,
    // A key hint shown at the end of the row, such as "G D". Only shown: the palette doesn't bind it.
    'shortcut' => null,
])

{{--
    An option of the palette's list: not focused itself (focus stays in the search box), but highlighted with the arrow
    keys or the pointer and chosen with Enter or a click. Your attributes go on it, so wire:click works.
--}}
<div
    role="option"
    id="command-item-{{ \Illuminate\Support\Str::random(8) }}"
    aria-selected="false"
    data-command-item
    @if ($href) data-href="{{ $href }}" @endif
    @if ($keywords) data-keywords="{{ $keywords }}" @endif
    {{ $attributes->class(['text-foreground aria-selected:bg-field flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2.5 text-sm']) }}
>
    @if ($icon)
        <x-widget.icon :name="$icon" class="text-foreground/70 size-4 shrink-0" />
    @endif
    <span data-command-label class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    @if ($shortcut)
        <kbd aria-hidden="true" class="border-line text-foreground/70 rounded-md border px-1.5 py-0.5 font-sans text-xs">{{ $shortcut }}</kbd>
    @endif
</div>
