@props([
    // Starts collapsed to a rail of icons on large screens. With remember, pass back what the person last chose:
    // :collapsed="request()->cookie('sidebar') === 'collapsed'".
    'collapsed' => false,
    // Keep the person's collapsed or expanded choice in this cookie ("sidebar"), as collapsed or expanded, for the next
    // page to pass to collapsed. Leave the cookie out of Laravel's encryption (see the remember note).
    'remember' => null,
    // What a group's icon does in the rail: popover shows its links beside the rail, in a list that scrolls past 18rem;
    // expand opens the sidebar again with the group open.
    'groups' => 'popover',
    // The sidebar's id, which its buttons point at. Defaults to one made for it.
    'id' => null,
    // With a panel slot (a chat, notifications): what the panel and the top bar's button for it are called.
    'panelLabel' => 'Chat',
    // The icon on the top bar's button for the panel.
    'panelIcon' => 'message-circle',
])

@php
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($groups, ['popover', 'expand'], true)) {
        throw new \InvalidArgumentException("Unknown groups [{$groups}] for <x-widget.sidebar-layout>. Use one of: popover, expand.");
    }
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id ?? 'sidebar', explicit: $id !== null);
    $panelId = isset($panel) ? app(\Bladewell\Support\ElementIds::class)->claim($id.'-panel') : null;
    $button = 'focus-visible:ring-primary text-foreground/70 hover:bg-field hover:text-foreground grid size-9 shrink-0 place-items-center rounded-lg outline-none transition-colors focus-visible:ring-2 motion-reduce:transition-none';
    // The sidebar's own easing: quick to start, long to settle, so it glides rather than slides.
    $ease = 'duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] motion-reduce:transition-none';
@endphp

{{--
    An app's frame: a sidebar (brand, navigation, footer), a top bar that stays put, and the page scrolling under it.

    On large screens the sidebar sits beside the page and collapses to a rail of icons from the top bar's button, its
    width easing as the labels fade. On smaller ones it's off screen, and the menu button slides it in over a dimmed
    page, which is made inert meanwhile, so focus and screen readers stay in the sidebar until it closes.

    It fills the window (h-dvh), or its parent where that has a height of its own (max-h-full), like the examples here.
    The page scrolls inside it, not the window, so the top bar never moves.

    Put an <x-widget.accordion.menu> in the sidebar slot, with an icon on each link and group: collapsed, the menu
    shows those icons, names each one beside it on hover or focus, and a group's icon shows its links in a popover
    beside the rail (or, with groups="expand", opens the sidebar again).
    Collapsed, the brand and footer slots show their first 4.5rem, so lead with a 2rem logo mark or avatar.

    No inline style and no inline script: collapsed comes from the server, so the page paints in the right state.
--}}
{{-- wire:ignore.self here and below: the script sets whether it's collapsed or open, and the state of the buttons; a
     Livewire render would put back the server's. What's inside still updates. --}}
<div
    data-sidebar-layout
    wire:ignore.self
    @if ($collapsed) data-sidebar-collapsed @endif
    @if ($remember) data-sidebar-remember="{{ $remember }}" @endif
    data-sidebar-groups="{{ $groups }}"
    {{ $attributes->class(['group/layout text-foreground relative flex h-dvh max-h-full overflow-hidden bg-[color-mix(in_oklab,var(--color-field)_45%,var(--color-surface))]']) }}
>
    {{-- Below lg: the dimmed page behind the open sidebar or panel. A click on it closes them. --}}
    <div
        data-sidebar-close
        aria-hidden="true"
        @class([
            'invisible absolute inset-0 z-30 bg-black/40 opacity-0 backdrop-blur-[2px] transition-[opacity,visibility] lg:hidden',
            'group-data-sidebar-open/layout:visible group-data-sidebar-open/layout:opacity-100',
            'group-data-sidebar-panel-open/layout:visible group-data-sidebar-panel-open/layout:opacity-100',
            $ease,
        ])
    ></div>

    <div
        id="{{ $id }}"
        data-sidebar
        @class([
            // Below lg: a panel off the start edge, slid in over the page while open. invisible keeps the closed one
            // out of the Tab order and away from screen readers. Opening turns it visible at once (only translate eases),
            // so the script can move focus into it; closing keeps it visible until the slide ends.
            'bg-surface invisible absolute inset-y-0 start-0 z-40 flex w-72 max-w-[calc(100%-3rem)] -translate-x-full flex-col shadow-2xl transition-[translate,visibility] rtl:translate-x-full',
            'group-data-sidebar-open/layout:visible group-data-sidebar-open/layout:translate-x-0 group-data-sidebar-open/layout:transition-[translate]',
            // lg and up: a column beside the page, on the frame's tint, narrowing to a rail.
            'lg:visible lg:relative lg:z-auto lg:w-64 lg:max-w-none lg:shrink-0 lg:translate-x-0 lg:bg-transparent lg:shadow-none lg:transition-[width] lg:rtl:translate-x-0',
            'lg:group-data-sidebar-collapsed/layout:w-18',
            $ease,
        ])
    >
        <div class="flex h-16 shrink-0 items-center gap-3 px-5 lg:mt-2">
            <div class="-m-1 flex min-w-0 flex-1 items-center gap-3 overflow-hidden p-1 whitespace-nowrap">{{ $brand ?? '' }}</div>
            <button type="button" data-sidebar-close aria-label="Close menu" class="{{ $button }} -me-2 lg:hidden">
                <x-widget.icon name="x" class="size-5" />
            </button>
        </div>

        {{-- The menu fills what's left and scrolls on its own. Padded so that, collapsed, each icon sits in the middle
             of the rail; overflow-x hidden, so nothing scrolls sideways while the width eases. --}}
        <div class="flex min-h-0 flex-1 flex-col [&_[data-accordion-menu]]:min-h-0 [&_[data-accordion-menu]]:flex-1 [&_[data-accordion-menu]]:overflow-x-hidden [&_[data-accordion-menu]]:px-3.5 [&_[data-accordion-menu]]:py-2">
            {{ $sidebar ?? '' }}
        </div>

        @isset($footer)
            <div class="border-line shrink-0 border-t px-5 py-3.5"><div class="-m-1 overflow-hidden p-1 whitespace-nowrap">{{ $footer }}</div></div>
        @endisset
    </div>

    <div data-sidebar-content wire:ignore.self class="flex min-w-0 flex-1 flex-col lg:py-2 lg:pe-2">
        {{-- The page scrolls here, under the top bar. On lg it's a raised sheet on the frame's tint. --}}
        <div data-sidebar-scroll class="bg-surface lg:border-line relative min-h-0 flex-1 overflow-y-auto [scrollbar-width:thin] lg:rounded-2xl lg:border lg:shadow-sm">
            {{-- Frosted, and lined only once the page has scrolled under it (data-scrolled, set by the script). Its own top
                 corners are rounded to the sheet's: Chrome doesn't clip a backdrop filter to a rounded scroller. --}}
            <header data-sidebar-topbar wire:ignore.self class="lg:rounded-t-[calc(1rem-1px)] bg-surface/80 data-scrolled:border-line data-scrolled:shadow-sm sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-transparent px-4 backdrop-blur-xl transition-[border-color,box-shadow] duration-200 sm:px-6 motion-reduce:transition-none">
                <button type="button" data-sidebar-menu wire:ignore.self aria-label="Open menu" aria-controls="{{ $id }}" aria-expanded="false" class="{{ $button }} -ms-2 lg:hidden">
                    <x-widget.icon name="menu" class="size-5" />
                </button>
                <button type="button" data-sidebar-collapse wire:ignore.self aria-label="Sidebar" aria-controls="{{ $id }}" aria-expanded="{{ $collapsed ? 'false' : 'true' }}" class="{{ $button }} -ms-2 hidden lg:grid">
                    <x-widget.icon name="panel-left" class="size-5 rtl:-scale-x-100" />
                </button>
                <span aria-hidden="true" class="bg-line me-1 h-5 w-px"></span>
                <div class="flex min-w-0 flex-1 items-center gap-3">{{ $topbar ?? '' }}</div>
                @if ($panelId)
                    <button type="button" data-sidebar-panel-toggle wire:ignore.self aria-label="{{ $panelLabel }}" aria-controls="{{ $panelId }}" aria-expanded="false" class="{{ $button }} aria-expanded:bg-field aria-expanded:text-foreground -me-2">
                        <x-widget.icon :name="$panelIcon" class="size-5" />
                    </button>
                @endif
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    @if ($panelId)
        {{-- The panel slot (a chat, say), opened from its button at the end of the top bar.
             As wide as the sidebar, at every size. Below lg: off the end edge, slid in over the dimmed page, which is
             inert meanwhile, as the sidebar is. lg and up: a sheet beside the page, which narrows to make room as its width eases open: the page can still
             be used. Closed, it's invisible, so out of the Tab order; opening turns it visible at once, so the script can
             move focus into it; closing keeps it visible until it has slid or narrowed away.
             wire:ignore.self: the script opens and closes it; what's inside still updates. --}}
        <aside
            id="{{ $panelId }}"
            data-sidebar-panel
            wire:ignore.self
            aria-label="{{ $panelLabel }}"
            @class([
                'bg-surface invisible absolute inset-y-0 end-0 z-40 flex w-72 max-w-[calc(100%-3rem)] translate-x-full flex-col shadow-2xl transition-[translate,visibility] rtl:-translate-x-full',
                'group-data-sidebar-panel-open/layout:visible group-data-sidebar-panel-open/layout:translate-x-0 group-data-sidebar-panel-open/layout:transition-[translate]',
                'lg:relative lg:z-auto lg:w-0 lg:max-w-none lg:shrink-0 lg:translate-x-0 lg:overflow-hidden lg:bg-transparent lg:shadow-none lg:transition-[width,visibility] lg:rtl:translate-x-0',
                'lg:group-data-sidebar-panel-open/layout:w-[16.5rem] lg:group-data-sidebar-panel-open/layout:transition-[width]',
                $ease,
            ])
        >
            {{-- Its own width on lg, so what's inside doesn't squeeze as the panel opens and closes around it. --}}
            <div class="bg-surface lg:border-line flex h-full min-h-0 w-full flex-col lg:my-2 lg:me-2 lg:h-[calc(100%-1rem)] lg:w-64 lg:overflow-hidden lg:rounded-2xl lg:border lg:shadow-sm">
                <div class="border-line flex h-16 shrink-0 items-center justify-between gap-3 border-b px-4 sm:px-5">
                    <h2 class="truncate font-semibold">{{ $panelLabel }}</h2>
                    <button type="button" data-sidebar-panel-close aria-label="Close {{ mb_strtolower($panelLabel) }}" class="{{ $button }} -me-2">
                        <x-widget.icon name="x" class="size-5" />
                    </button>
                </div>
                <div class="flex min-h-0 flex-1 flex-col">{{ $panel }}</div>
            </div>
        </aside>
    @endif

    {{-- Collapsed: the name of the link or group under the pointer or focus, beside the rail. One for the whole
         sidebar, in the top layer so the menu's scrolling can't clip it. Its tail (::before) points at the icon; overflow-visible,
         as a popover clips what sticks out of it. aria-hidden: each link is already named by
         its own text, which only fades out of sight. The script positions it (inline top and left); a Livewire render
         would wipe that. --}}
    <div
        data-sidebar-tip
        popover="manual"
        aria-hidden="true"
        wire:ignore.self
        class="bg-foreground text-surface before:bg-foreground pointer-events-none fixed inset-auto m-0 overflow-visible rounded-lg px-2.5 py-1.5 before:absolute before:-start-1 before:top-1/2 before:size-2 before:-translate-y-1/2 before:rotate-45 before:rounded-[1px] text-xs leading-snug font-medium whitespace-nowrap opacity-0 shadow-lg transition-opacity duration-150 open:opacity-100 starting:open:opacity-0 motion-reduce:transition-none"
    ></div>
</div>
