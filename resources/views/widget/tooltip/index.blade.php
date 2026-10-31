@props([
    // What it says: a few words that name or explain what it's on ("Copy link", "Last synced 2 minutes ago").
    'text',
    // Which side of it the tooltip goes: top (the default), bottom, start or end. It moves to the opposite side when
    // there's no room.
    'placement' => 'top',
    // Defaults to one made for it; the element inside points at it with aria-describedby.
    'id' => null,
])

@php
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($placement, ['top', 'bottom', 'start', 'end'], true)) {
        throw new \InvalidArgumentException("Unknown placement [{$placement}] for <x-widget.tooltip>. Use one of: top, bottom, start, end.");
    }
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id ?? 'tooltip', explicit: $id !== null);
@endphp

{{--
    Wraps one element: a button, a link, an icon. The tooltip shows when the pointer rests on it or it gets keyboard
    focus, stays while the pointer moves onto the tooltip, and hides on Esc (WCAG 1.4.13). It's text only: anything to
    click goes in a dropdown or a modal. It sits in the top layer (a popover), so a table or a modal can't clip it.
    resources/js/widget/tooltip points the element's aria-describedby at it, so screen readers read it too. On a touch
    screen a tap on plain text or an icon shows it, and a long press on a button or a link; there, iOS's own long-press
    preview of a link is turned off, as it would cover the tooltip.
--}}
<span data-tooltip="{{ $id }}" data-placement="{{ $placement }}" {{ $attributes->class(['inline-flex [-webkit-touch-callout:none]']) }}>{{ $slot }}<span
        id="{{ $id }}"
        popover="manual"
        role="tooltip"
        {{-- Never a Tab stop: a showing popover would otherwise take focus between its element and the next one. --}}
        tabindex="-1"
        {{-- The script positions it (inline top and left); a Livewire render would wipe that. The text still updates. --}}
        wire:ignore.self
        data-tooltip-bubble
        class="group/tip bg-foreground text-surface pointer-events-auto fixed inset-auto m-0 hidden max-w-64 overflow-visible rounded-lg px-2.5 py-1.5 text-xs leading-snug font-medium shadow-lg open:block opacity-0 transition-opacity duration-150 open:opacity-100 starting:open:opacity-0 motion-reduce:transition-none"
    >{{ $text }}{{-- The tail, on the side facing the element (data-side, set by the script, which also lines it up with the
         element's middle when the tooltip is pushed in from the window's edge). --}}<span
            data-tooltip-arrow
            aria-hidden="true"
            class="bg-foreground absolute size-2 rotate-45 rounded-[1px] group-data-[side=top]/tip:-bottom-1 group-data-[side=bottom]/tip:-top-1 group-data-[side=left]/tip:-right-1 group-data-[side=right]/tip:-left-1"
        ></span></span></span><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
