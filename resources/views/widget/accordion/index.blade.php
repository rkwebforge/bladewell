@props([
    // The header text, or use the header slot for markup.
    'title' => null,
    // Starts open. A menu group is open unless set to false, so it paints the same on every page.
    'open' => null,
    // Items sharing a name open one at a time.
    'name' => null,
    // default, bordered, card, or menu (a group of links inside an accordion.menu).
    'variant' => 'default',
    // No padding or text style on the body, for content that runs edge to edge.
    'flush' => false,
    // An icon before the header, by icon name ("shopping-bag"). On a menu group in a collapsed
    // sidebar-layout it's what stays in view; a group without one shows its first letter there instead.
    'icon' => null,
])

@php
    // default: tinted panel while open. bordered: a divided list, for FAQs. card: each item its own card.
    $variants = [
        'default' => [
            'item' => 'rounded-2xl transition-colors duration-300 open:bg-field data-closing:bg-transparent motion-reduce:transition-none',
            'summary' => 'rounded-2xl px-5 py-4 hover:bg-field',
            'body' => 'px-5 pb-4',
        ],
        'bordered' => [
            'item' => 'border-line border-b first:border-t',
            'summary' => 'px-1 py-5 hover:text-primary',
            'body' => 'px-1 pb-5',
        ],
        'card' => [
            'item' => 'border-line bg-surface rounded-2xl border shadow-sm',
            'summary' => 'rounded-2xl px-5 py-4 hover:bg-field/60 group-open/accordion:rounded-b-none',
            'body' => 'px-5 pb-5',
        ],
        // A heading over a group of links in a sidebar: compact, and the links indented under a rule.
        'menu' => [
            'item' => '',
            'summary' => 'rounded-lg px-3 py-1.5 text-sm text-foreground/70 hover:bg-field hover:text-foreground',
            'body' => 'border-line ms-3 flex flex-col gap-0.5 border-s ps-2 pt-0.5 pb-1',
        ],
    ];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($variant, $variants)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.accordion>. Use one of: ".implode(', ', array_keys($variants)).'.');
    }
    $menu = $variant === 'menu';
    $style = $variants[$variant];
    $open ??= $menu;
    $chevron = [$menu ? 'size-4' : 'size-6', '-rotate-90 transition-transform duration-300 group-open/accordion:rotate-0 group-data-closing/accordion:-rotate-90 rtl:rotate-90 rtl:group-open/accordion:rotate-0 rtl:group-data-closing/accordion:rotate-90 motion-reduce:transition-none'];
    // A menu group in a collapsed sidebar-layout is only its icon: the heading and chevron fade out (the heading is
    // still read out), the links under it are hidden, and the icon is highlighted while the current page is one of them.
    // in-data-sidebar-*: those only apply inside a sidebar-layout, so a menu anywhere else is unchanged.
    if ($menu) {
        $style['summary'] .= ' lg:in-data-sidebar-collapsed:group-has-aria-[current=page]/accordion:bg-primary-fill lg:in-data-sidebar-collapsed:group-has-aria-[current=page]/accordion:text-on-primary';
        $chevron[] = 'shrink-0 lg:in-data-sidebar-collapsed:opacity-0';
    }
    $label = $menu ? 'min-w-0 flex-1 transition-opacity delay-100 duration-200 in-data-sidebar-layout:truncate lg:in-data-sidebar-collapsed:opacity-0 lg:in-data-sidebar-collapsed:delay-0 motion-reduce:transition-none' : null;
    $initial = $menu && $icon === null ? mb_strtoupper(mb_substr(trim(strip_tags((string) ($header ?? $title))), 0, 1)) : null;
    // In the rail, sidebar-layout's script can show the group's links in a popover beside its icon: this same element,
    // made a popover only while it's shown, so the links keep their place in the Tab order and nothing is copied. These
    // classes only apply while it's open as one (the element never carries an open attribute): titled from
    // data-menu-title, at most 18rem tall with its list scrolling past that, and a tail (::after) pointing at the icon,
    // at the height the script puts in --tail-y. The list scrolls rather than the popover, which would clip the tail.
    // The list keeps 2px all round, so its edge doesn't clip a link's focus ring, and 6px before its scrollbar when
    // it has one taking up room (data-scrollbar, set by the script), so a highlighted link doesn't run into it.
    $flyout = 'group/flyout open:border-line open:bg-surface open:text-foreground open:fixed open:inset-auto open:m-0 open:flex open:max-h-72 open:w-60 open:flex-col open:overflow-visible open:rounded-xl open:border open:p-1 open:shadow-xl open:transition-[opacity,translate] open:duration-150 starting:open:-translate-x-1 starting:open:opacity-0 rtl:starting:open:translate-x-1 motion-reduce:transition-none open:before:text-foreground/70 open:before:block open:before:px-3.5 open:before:pt-1.5 open:before:pb-1 open:before:text-xs open:before:font-semibold open:before:content-[attr(data-menu-title)] open:after:border-line open:after:bg-surface open:after:absolute open:after:-start-1.5 open:after:top-[var(--tail-y,1.375rem)] open:after:size-3 open:after:-translate-y-1/2 open:after:rotate-45 open:after:border-s open:after:border-b rtl:open:after:border-t rtl:open:after:border-b-0';
@endphp

{{--
    Native <details>: works with no JS (keyboard, screen readers, find-in-page all built in).
    The JS adds the open/close animation and takes over the `name` group ("one open at a time")
    so the item that closes animates too, instead of snapping shut.
--}}
@if ($menu)
    <li class="list-none">
@endif
{{-- wire:ignore.self: whether it's open, and the attributes the script moves (name, data-closing), are the person's once
     the page is up; a Livewire render would put back the server's and snap panels open or shut. What's inside still updates.
     So open only sets how a panel starts. Outside Livewire the attribute does nothing. --}}
<details
    data-accordion
    wire:ignore.self
    @if ($open) open @endif
    @if ($name) name="{{ $name }}" @endif
    {{ $attributes->class(['group/accordion w-full', $style['item']]) }}
>
    <summary @class([
        'focus-visible:ring-primary flex cursor-pointer list-none items-center justify-between gap-4 text-start outline-none select-none focus-visible:ring-2 [&::-webkit-details-marker]:hidden',
        // A menu group's heading has the same colour and weight as the links around it; its chevron marks it as a group.
        'text-foreground font-medium' => ! $menu,
        $style['summary'],
    ])>
        @if ($icon || $menu)
            <span class="flex min-w-0 flex-1 items-center gap-3">
                @if ($icon)
                    <x-widget.icon :name="$icon" class="size-5 shrink-0" />
                @elseif ($initial !== '')
                    <span aria-hidden="true" class="hidden size-5 shrink-0 place-items-center text-xs font-semibold lg:in-data-sidebar-collapsed:grid">{{ $initial }}</span>
                @endif
                <span data-menu-label @if ($label) class="{{ $label }}" @endif>{{ $header ?? $title }}</span>
            </span>
        @else
            <span>{{ $header ?? $title }}</span>
        @endif
        {{-- Closed, the chevron points to the end of the line (right, or left in right-to-left pages); open, down. --}}
        <x-widget.icon name="chevron-down" :class="implode(' ', $chevron)" />
    </summary>

    {{-- Padding lives on the inner div: the animated outer one must be able to reach 0 height.
         flush drops the padding and text style, for content that should run edge to edge (a table, a form). --}}
    {{-- wire:ignore.self here and on the menu's list: scripts set their attributes (the height as it animates; in a
         collapsed sidebar-layout, the popover, where it sits and its scrollbar room), and a Livewire render would put
         back the server's, shutting a popover someone has open. What's inside still updates. --}}
    <div
        data-accordion-content
        wire:ignore.self
        @if ($menu) data-menu-title="{{ trim(strip_tags((string) ($header ?? $title))) }}" @endif
        @class([$flyout.' lg:in-data-sidebar-collapsed:not-open:hidden' => $menu])
    >
        @if ($menu)
            <ul wire:ignore.self class="{{ $style['body'] }} group-open/flyout:ms-0 group-open/flyout:min-h-0 group-open/flyout:overflow-y-auto group-open/flyout:overscroll-contain group-open/flyout:border-0 group-open/flyout:p-0.5 group-open/flyout:data-scrollbar:pe-1.5 group-open/flyout:[scrollbar-width:thin]">
                {{ $slot }}
            </ul>
        @else
            <div @class(['text-foreground/75 text-sm '.$style['body'] => ! $flush])>
                {{ $slot }}
            </div>
        @endif
    </div>
</details>
@if ($menu)
    </li>
@endif
