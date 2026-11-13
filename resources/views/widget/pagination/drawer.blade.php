@props([
    // A length-aware paginator (paginate()).
    'paginator',
])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $sheetId = app(\Bladewell\Support\ElementIds::class)->claim('pages-'.$paginator->getPageName());
    // Narrower buttons on phones so all five fit inside a table on a 320px screen; shrink-0 so flex never squashes them instead.
    $nav = 'text-foreground bg-field flex h-10 w-9 shrink-0 items-center justify-center rounded-full transition-colors sm:w-14';
@endphp

<nav aria-label="Pagination" {{ $attributes->class(['flex items-center justify-center gap-1 px-0 py-5 sm:gap-2 sm:p-5']) }}>
    @foreach ([
        ['url' => $paginator->url(1), 'off' => $paginator->onFirstPage(), 'icon' => 'chevrons-left', 'label' => 'First page'],
        ['url' => $paginator->previousPageUrl(), 'off' => $paginator->onFirstPage(), 'icon' => 'chevron-left', 'label' => 'Previous page'],
    ] as $link)
        @if ($link['off'])
            <span class="{{ $nav }} opacity-40" aria-disabled="true"><x-widget.icon :name="$link['icon']" class="size-6" /></span>
        @else
            <a href="{{ $link['url'] }}" aria-label="{{ $link['label'] }}" class="{{ $nav }} hover:bg-line focus-visible:ring-primary outline-none focus-visible:ring-2"><x-widget.icon :name="$link['icon']" class="size-6" /></a>
        @endif
    @endforeach

    <button
        type="button"
        data-modal-open="{{ $sheetId }}"
        aria-label="Page {{ $current }} of {{ $last }}, choose a page"
        class="bg-field border-line-strong text-foreground hover:bg-line focus-visible:ring-primary flex h-10 min-w-12 shrink-0 items-center sm:min-w-14 justify-center rounded-full border px-3 tabular-nums transition-all outline-none focus-visible:ring-2 active:scale-95"
    >{{ $current }}</button>

    @foreach ([
        ['url' => $paginator->nextPageUrl(), 'off' => ! $paginator->hasMorePages(), 'icon' => 'chevron-right', 'label' => 'Next page'],
        ['url' => $paginator->url($last), 'off' => ! $paginator->hasMorePages(), 'icon' => 'chevrons-right', 'label' => 'Last page'],
    ] as $link)
        @if ($link['off'])
            <span class="{{ $nav }} opacity-40" aria-disabled="true"><x-widget.icon :name="$link['icon']" class="size-6" /></span>
        @else
            <a href="{{ $link['url'] }}" aria-label="{{ $link['label'] }}" class="{{ $nav }} hover:bg-line focus-visible:ring-primary outline-none focus-visible:ring-2"><x-widget.icon :name="$link['icon']" class="size-6" /></a>
        @endif
    @endforeach
</nav>

{{-- Bottom sheet reuses the modal behaviour (data-modal): Esc, backdrop click, focus return, scroll lock, and a finger
     dragging it down (data-modal-sheet: the dialog itself is what slides). wire:ignore.self, as on the modal: a Livewire
     render would take away the browser's `open` and shut the sheet mid-choice. --}}
<dialog
    id="{{ $sheetId }}"
    data-modal
    wire:ignore.self
    data-modal-sheet
    data-close-on-backdrop
    tabindex="-1"
    aria-label="Choose a page"
    class="fixed inset-x-0 top-auto bottom-0 m-0 mx-auto h-auto max-h-none w-full max-w-110 translate-y-full bg-transparent p-0 outline-none transition-all transition-discrete duration-300 open:translate-y-0 starting:open:translate-y-full motion-reduce:transition-none backdrop:bg-foreground/40"
>
    {{-- The bottom padding clears the home indicator where the page is drawn under it (viewport-fit=cover, as NativePHP
         and home-screen web apps do); env() is 0 everywhere else. --}}
    <div class="bg-surface relative rounded-t-3xl px-6 pt-10 pb-[calc(1.5rem+env(safe-area-inset-bottom))] shadow-2xl">
        {{-- Shows the sheet can be dragged down. Only a cue: Esc, the backdrop and the X close it just the same. --}}
        <div aria-hidden="true" class="bg-line pointer-events-none absolute inset-x-0 top-2 mx-auto h-1 w-9 rounded-full"></div>

        <button type="button" data-modal-close aria-label="Close" class="text-muted hover:text-foreground focus-visible:ring-primary tap-target absolute top-4 right-4 grid size-8 place-items-center rounded-full outline-none focus-visible:ring-2">
            <x-widget.icon name="x" class="size-5" />
        </button>

        {{-- Filled in by resources/js/widget/pagination when the sheet opens: rendering every page's link here
             up front cost ~400 KB of HTML per 1,000 pages on every page load, for a sheet that is rarely opened. --}}
        <ul
            data-page-list
            data-url="{{ $paginator->url(1) }}"
            data-page-name="{{ $paginator->getPageName() }}"
            data-current="{{ $current }}"
            data-last="{{ $last }}"
            class="flex max-h-52 flex-col overflow-y-auto"
        ></ul>
    </div>
</dialog>
