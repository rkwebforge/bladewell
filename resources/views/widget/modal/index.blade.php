@props([
    // Required, and unique on the page: buttons open it with data-modal-open="{id}", scripts with modal.open('{id}').
    'id',
    // The heading at the top, and the modal's name for screen readers.
    'title' => null,
    // The modal's name for screen readers when it has no title; a title wins.
    'label' => null,
    // How wide it is: sm, md, lg or xl. full fills the whole screen (dialogs only).
    'size' => 'md',
    // dialog: a panel in the middle of the screen. drawer: a panel that slides in from the edge set by side.
    'variant' => 'dialog',
    // With variant="drawer", the edge it slides in from: start, end (the right in left-to-right pages) or bottom.
    'side' => 'end',
    // Keeps the title and footer in view while only the body scrolls. Drawers and size="full" always work this way.
    'scrollable' => false,
    // sheet: on phones, a dialog becomes a sheet that slides up from the bottom. Leave it out to keep the dialog
    // centred. Dialogs only, and not with size="full".
    'mobile' => null,
    // The X in the top corner. Never shown with disable-close.
    'closeButton' => true,
    // A click on the dimmed area outside the panel closes it too.
    'closeOnBackdrop' => false,
    // Locks it open: no Esc, backdrop or X. Only your code closes it, with modal.close(id, { force: true }) or a
    // modal-close event from Livewire.
    'disableClose' => false,
    // Puts the forms inside back to their starting values each time it closes (wire:model properties too).
    'resetOnClose' => false,
    // Opens as the page loads, e.g. :open="$errors->any()" to reopen a form after failed validation.
    'open' => false,
])

@php
    $widths = ['sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl'];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($size, ['sm', 'md', 'lg', 'xl', 'full'], true)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.modal>. Use one of: sm, md, lg, xl, full.");
    }
    if (! in_array($variant, ['dialog', 'drawer'], true)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.modal>. Use one of: dialog, drawer.");
    }
    if (! in_array($side, ['start', 'end', 'bottom'], true)) {
        throw new \InvalidArgumentException("Unknown side [{$side}] for <x-widget.modal>. Use one of: start, end, bottom.");
    }
    if (! in_array($mobile, [null, 'sheet'], true)) {
        throw new \InvalidArgumentException("Unknown mobile [{$mobile}] for <x-widget.modal>. Use one of: sheet, or leave it out.");
    }
    // Combinations that can't apply would otherwise be ignored without a word.
    if ($variant === 'drawer' && $size === 'full') {
        throw new \InvalidArgumentException('<x-widget.modal variant="drawer"> takes size sm, md, lg or xl: a drawer is already as tall as the screen, so full has no meaning.');
    }
    if ($mobile === 'sheet' && ($variant === 'drawer' || $size === 'full')) {
        throw new \InvalidArgumentException('<x-widget.modal mobile="sheet"> is for a centred dialog: a drawer or size="full" already fits a phone.');
    }
    $drawer = $variant === 'drawer';
    $full = $size === 'full';
    $width = $widths[$size] ?? null;
    // Header and footer stay put while only the body scrolls: asked for with `scrollable`, and always so for
    // drawers and full-screen dialogs, where the panel is exactly as tall as the screen.
    $pinned = $scrollable || $drawer || $full;
    // mobile="sheet": a centred dialog becomes a bottom sheet below the sm breakpoint.
    $sheet = $mobile === 'sheet';
    // An X that can't close anything is just noise, so a locked modal never shows one.
    $closeButton = $closeButton && ! $disableClose;
    // Openers find the dialog by this id, so a duplicate must fail loudly rather than be renamed.
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id, explicit: true);
    $hasFooter = isset($footer);
    // Drawers slide in on exactly the curve and 300ms they slide out on, so opening is the closing played backwards.
    // 300ms is also the ceiling: the exit must stay within the dialog's own closing transition or it gets cut off.
    $glide = 'will-change-[translate]';

    // Each drawer side slides in from its own edge (the end edge flips in RTL). Classes written out in full for Tailwind.
    $drawerPanel = [
        'end' => "ms-auto h-full {$width} translate-x-full rtl:-translate-x-full group-open:translate-x-0 rtl:group-open:translate-x-0 starting:group-open:translate-x-full rtl:starting:group-open:-translate-x-full",
        'start' => "me-auto h-full {$width} -translate-x-full rtl:translate-x-full group-open:translate-x-0 rtl:group-open:translate-x-0 starting:group-open:-translate-x-full rtl:starting:group-open:translate-x-full",
        'bottom' => "mx-auto mt-auto max-h-[85dvh] {$width} rounded-t-3xl translate-y-full group-open:translate-y-0 starting:group-open:translate-y-full",
    ];

    // In a page drawn under the notch and home indicator (viewport-fit=cover, as NativePHP and home-screen web apps
    // do), the panel's edges that meet the screen's keep their content clear of them. env() is 0 everywhere else,
    // so ordinary pages look the same. Physical sides on purpose: the notch doesn't flip in RTL.
    $safeArea = match (true) {
        $full => 'pt-[env(safe-area-inset-top)] pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)]',
        $drawer && $side === 'end' => 'pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] ltr:pr-[env(safe-area-inset-right)] rtl:pl-[env(safe-area-inset-left)]',
        $drawer && $side === 'start' => 'pt-[env(safe-area-inset-top)] pb-[env(safe-area-inset-bottom)] ltr:pl-[env(safe-area-inset-left)] rtl:pr-[env(safe-area-inset-right)]',
        $drawer => 'pb-[env(safe-area-inset-bottom)]',
        $sheet => 'max-sm:pb-[env(safe-area-inset-bottom)]',
        // Not null: it's a key in the panel's class list below, and null as an array key is deprecated in PHP 8.5.
        default => '',
    };
    // The X is placed from the panel's edge, padding included, so it moves in by the same insets.
    $closeAt = match (true) {
        $full, $drawer && $side === 'end' => 'top-[calc(1rem+env(safe-area-inset-top))] ltr:right-[calc(1rem+env(safe-area-inset-right))] rtl:left-[calc(1rem+env(safe-area-inset-left))]',
        $drawer && $side === 'start' => 'end-4 top-[calc(1rem+env(safe-area-inset-top))]',
        default => 'end-4 top-4',
    };
    // A panel at the bottom of the screen is a sheet, which resources/js/modal lets a finger drag down to close: only
    // when the backdrop may close it too, so a sheet that can't be dismissed stays that way.
    $bottomSheet = ($drawer && $side === 'bottom') || $sheet;
    $swipe = $bottomSheet && $closeOnBackdrop && ! $disableClose;
@endphp

{{--
    Native <dialog>: the browser traps focus, blocks the page behind it, closes on Esc and returns focus to
    the opener. The dialog is the full-screen layer and its empty area is the clickable backdrop; the panel
    inside is the modal. Open with data-modal-open="{{ $id }}" or modal.open('{{ $id }}').
--}}
{{-- wire:ignore.self: the browser marks an open dialog with `open`, which a Livewire render would take away again,
     closing it under the person (a form's validation errors included). What's inside still updates. Open and close it
     from a component with $this->dispatch('modal-open', id: '…') and 'modal-close'. --}}
<dialog
    id="{{ $id }}"
    data-modal
    wire:ignore.self
    tabindex="-1"
    @if ($title) aria-labelledby="{{ $id }}-title" @elseif ($label) aria-label="{{ $label }}" @endif
    @if ($closeOnBackdrop) data-close-on-backdrop @endif
    @if ($disableClose) data-disable-close @endif
    @if ($resetOnClose) data-reset-on-close @endif
    @if ($open) data-open-on-load @endif
    @class([
        'group fixed inset-0 m-0 h-dvh max-h-none w-full max-w-none flex-col overscroll-contain bg-transparent outline-none transition-all transition-discrete duration-300 open:flex motion-reduce:transition-none backdrop:bg-foreground/40',
        // The backdrop fades in and out rather than popping. It sits beside the dialog in the top layer, so the
        // dialog's own opacity doesn't reach it. No backdrop blur: re-blurring the whole page as it opens
        // stalls rendering for a few hundred milliseconds, and the panel visibly jumps instead of sliding.
        'backdrop:opacity-0 backdrop:transition-opacity backdrop:duration-300 open:backdrop:opacity-100 starting:open:backdrop:opacity-0 motion-reduce:backdrop:transition-none',
        // A drawer stays opaque and only slides; fading it at the same time hides the motion.
        'opacity-0 open:opacity-100 starting:open:opacity-0' => ! $drawer,
        'overflow-y-auto px-2.5 py-8' => ! $pinned && ! $drawer && ! $full,
        'overflow-hidden px-2.5 py-8' => $pinned && ! $drawer && ! $full,
        // Clip, not hidden: hidden still scrolls programmatically, so showModal() focusing the off-screen panel
        // scrolls the dialog sideways and the panel lands, then snaps back. Clip can't be scrolled at all.
        'overflow-clip p-0' => $drawer,
        'overflow-hidden p-0' => $full,
        'max-sm:px-0 max-sm:pt-8 max-sm:pb-0' => $sheet,
    ])
>
    <div
        @if ($bottomSheet) data-modal-sheet @endif
        {{ $attributes->class([
            'bg-surface text-foreground relative w-full shadow-xl transition-transform duration-300 motion-reduce:transition-none',
            'flex flex-col' => $pinned,
            // Centred dialog: grows in slightly as it opens.
            "m-auto rounded-3xl scale-95 group-open:scale-100 starting:group-open:scale-95 {$width}" => ! $drawer && ! $full,
            'max-h-full' => $pinned && ! $drawer && ! $full,
            'h-full max-w-none' => $full,
            $drawerPanel[$side] => $drawer,
            $glide => $drawer,
            'max-sm:mt-auto max-sm:mb-0 max-sm:max-w-none max-sm:scale-100 max-sm:rounded-b-none max-sm:translate-y-full max-sm:group-open:translate-y-0 max-sm:starting:group-open:translate-y-full max-sm:will-change-[translate] max-sm:group-open:duration-500 max-sm:group-open:ease-[cubic-bezier(0.25,0.46,0.45,0.94)]' => $sheet,
            $safeArea => $safeArea !== '',
        ]) }}
    >
        @if ($swipe)
            {{-- Shows the sheet can be dragged down. Only a cue: Esc, the backdrop and the X close it just the same. --}}
            <div aria-hidden="true" @class(['bg-line pointer-events-none absolute inset-x-0 top-2 mx-auto h-1 w-9 rounded-full', 'sm:hidden' => $sheet])></div>
        @endif

        @if ($title)
            <div @class(['px-6 pt-6', 'pe-14' => $closeButton, 'pb-6' => ! $pinned, 'shrink-0 pb-4' => $pinned])>
                <h2 id="{{ $id }}-title" class="text-lg font-semibold">{{ $title }}</h2>
            </div>
        @endif

        @if ($closeButton)
            <button
                type="button"
                data-modal-close
                aria-label="Close"
                class="text-muted hover:text-foreground focus-visible:ring-primary tap-target absolute {{ $closeAt }} z-10 grid size-8 place-items-center rounded-full outline-none focus-visible:ring-2"
            >
                <x-widget.icon name="x" class="size-5" />
            </button>
        @endif

        <div @class([
            'px-6',
            'pt-6' => ! $title,
            // No title row to hold the X, so the body keeps clear of it.
            'pe-14' => ! $title && $closeButton,
            'pb-6' => ! $hasFooter,
            'min-h-0 flex-1 overflow-y-auto overscroll-contain' => $pinned,
        ])>
            {{ $slot }}
        </div>

        @if ($hasFooter)
            <div @class([
                'flex flex-wrap items-center justify-end gap-3 px-6 pb-6',
                'pt-6' => ! $pinned,
                'border-line shrink-0 border-t pt-4' => $pinned,
            ])>{{ $footer }}</div>
        @endif
    </div>
</dialog>
