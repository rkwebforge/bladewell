@props([
    // Where the link goes: another page, or a #section of this one.
    'href',
    // The page you're on: highlighted, announced as the current page, and where the menu opens scrolled to.
    // For #section links, resources/js/widget/accordion moves it as they're clicked and as the URL's #hash changes.
    'current' => false,
    // An icon before the text, by icon name ("layout-dashboard"). In a collapsed sidebar-layout
    // it's what stays in view; a link without one shows its first letter there instead.
    'icon' => null,
])

@php
    $initial = mb_strtoupper(mb_substr(trim(strip_tags((string) $slot)), 0, 1));
@endphp

<li class="list-none">
    {{-- Styled from aria-current, not from the prop, so the highlight follows the script when it moves aria-current.
         scroll-initial-target: the menu starts scrolled to the server's current link, centred, before the first paint. --}}
    {{-- wire:ignore.self: the script moves aria-current to the #section clicked and drops scroll-initial-target after load;
         a Livewire render would put both back. The text inside still updates. --}}
    {{-- Only inside a sidebar-layout. There the text stays on one line, so it doesn't wrap and jump as the sidebar narrows,
         and collapsed it fades out, still read by screen readers, leaving the icon. Not for a link in a group
         ([data-accordion-content]): in the rail that shows in a popover, where it reads as usual. --}}
    <a
        wire:ignore.self
        href="{{ $href }}"
        @if ($current) aria-current="page" @endif
        {{ $attributes->class([
            'focus-visible:ring-primary flex items-center gap-3 rounded-lg px-3 py-1.5 outline-none focus-visible:ring-2',
            'text-foreground/70 hover:bg-field hover:text-foreground',
            'aria-[current=page]:bg-primary-fill aria-[current=page]:text-on-primary aria-[current=page]:font-medium aria-[current=page]:hover:bg-primary-fill aria-[current=page]:hover:text-on-primary',
            'snap-center [scroll-initial-target:nearest]' => $current,
        ]) }}
    >@if ($icon)<x-widget.icon :name="$icon" class="size-5 shrink-0" />@else<span aria-hidden="true" class="hidden size-5 shrink-0 place-items-center text-xs font-semibold lg:[[data-sidebar-collapsed]_&:not([data-accordion-content]_*)]:grid">{{ $initial }}</span>@endif<span data-menu-label class="min-w-0 flex-1 transition-opacity delay-100 duration-200 in-data-sidebar-layout:truncate lg:[[data-sidebar-collapsed]_&:not([data-accordion-content]_*)]:opacity-0 lg:[[data-sidebar-collapsed]_&:not([data-accordion-content]_*)]:delay-0 motion-reduce:transition-none">{{ $slot }}</span></a>
</li>
