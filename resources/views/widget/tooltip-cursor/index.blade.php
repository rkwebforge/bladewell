@props([
    // What it says: a short label or reading for what's under the pointer ("Tuesday: 14 orders").
    'text',
    // Defaults to one made for it; the element inside points at it with aria-describedby.
    'id' => null,
])

@php
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id ?? 'tooltip-cursor', explicit: $id !== null);
@endphp

{{--
    Wraps one element, and follows the pointer while it's over it: just below and after the pointer, moving to the
    other side of it near the window's edges. With the keyboard there's no pointer, so it sits under the focused element
    instead. It never takes the pointer (it would be in the way of what's under it) and Esc hides it. It sits in the top
    layer (a popover), so a table or a modal can't clip it. resources/js/widget/tooltip-cursor points the element's
    aria-describedby at it, so screen readers read it too. On a touch screen a long press on a link or a button inside
    shows it; there, iOS's own long-press preview of a link is turned off, as it would cover the tooltip.
--}}
<span data-tooltip-cursor="{{ $id }}" {{ $attributes->class(['inline-flex [-webkit-touch-callout:none]']) }}>{{ $slot }}<span
        id="{{ $id }}"
        popover="manual"
        role="tooltip"
        {{-- Never a Tab stop: a showing popover would otherwise take focus between its element and the next one. --}}
        tabindex="-1"
        {{-- The script moves it with the pointer (inline top and left); a Livewire render would wipe that. The text still updates. --}}
        wire:ignore.self
        data-tooltip-cursor-bubble
        class="bg-foreground text-surface pointer-events-none fixed inset-auto m-0 hidden max-w-64 rounded-lg px-2.5 py-1.5 text-xs leading-snug font-medium shadow-lg open:block"
    >{{ $text }}</span></span><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
