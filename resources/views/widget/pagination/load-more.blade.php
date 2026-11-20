@props([
    // Any Laravel paginator; with cursorPaginate() it loads just as fast on page 500 as on page 2.
    'paginator',
    // CSS selector for the list the items are rendered in, e.g. "#order-list". New items are appended to it.
    'target',
    // The button's text.
    'label' => 'Load more',
    // Livewire: the rows-per-page property to raise by a page each time (e.g. "perPage"), so the component renders the
    // longer list; appended items would be gone at its next render. Without it, the button moves to the next page.
    'perPageModel' => null,
])

@php
    // The same id on every page, so the script can find this block in the next page's HTML and swap it in.
    $id = app(\Bladewell\Support\ElementIds::class)->claim('load-more-'.$paginator->getPageName());
    $total = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $paginator->total() : null;
    // Items seen so far, counting earlier pages: loading more keeps them on screen.
    $seen = method_exists($paginator, 'lastItem') ? $paginator->lastItem() : null;
    // Counts grouped as the app's locale groups them (1.234 in German) where intl is there, with 0-9 digits like the page
    // links beside them; plain 1,234 where it isn't.
    $formatter = class_exists(\NumberFormatter::class) ? new \NumberFormatter(str_replace('-', '_', app()->getLocale()).'@numbers=latn', \NumberFormatter::DECIMAL) : null;
    $count = static fn (int $n): string => (string) ($formatter?->format($n) ?: number_format($n));
@endphp

{{--
    Without JS the button is a plain link to the next page. With it, resources/js/widget/pagination fetches
    that page, appends the items it finds in `target` to the list here, and replaces this block with the new one.
--}}
<div id="{{ $id }}" data-load-more data-target="{{ $target }}" @if ($perPageModel) data-per-page-model="{{ $perPageModel }}" data-per-page="{{ $paginator->perPage() }}" @endif {{ $attributes->class(['flex flex-col items-center gap-3 px-0 py-5 sm:p-5']) }}>
    @if ($total !== null && $seen !== null)
        <p class="text-foreground/75 text-sm tabular-nums">Showing {{ $count($seen) }} of {{ $count($total) }}</p>
        {{-- A thin bar for how far through the list you are; the text above says the same for screen readers. --}}
        <div class="bg-field h-1 w-40 overflow-hidden rounded-full" aria-hidden="true">
            {{-- A whole-percent class from the ranges at the end of base.css, not style="". --}}
            <div class="bg-primary w-[{{ max(0, min(100, (int) round($seen / max($total, 1) * 100))) }}%] h-full rounded-full"></div>
        </div>
    @endif

    @if ($paginator->hasMorePages())
        <x-widget.button variant="neutral" :href="$paginator->nextPageUrl()" data-load-more-link rel="next" class="aria-busy:pointer-events-none aria-busy:opacity-60">{{ $label }}</x-widget.button>
    @else
        <p class="text-muted text-sm">That's everything.</p>
    @endif

    <p data-load-more-status role="status" class="text-error text-sm empty:hidden"></p>
</div>
