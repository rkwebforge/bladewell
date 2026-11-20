@props([
    // Any Laravel paginator; a cursor paginator gets the buttons without the item numbers.
    'paginator',
])

@php
    // Cursor paginators have no item numbers, so they get the buttons alone.
    $from = method_exists($paginator, 'firstItem') ? $paginator->firstItem() : null;
    $to = method_exists($paginator, 'lastItem') ? $paginator->lastItem() : null;
    $total = $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $paginator->total() : null;
    // Counts grouped as the app's locale groups them (1.234 in German) where intl is there, with 0-9 digits like the page
    // links beside them; plain 1,234 where it isn't.
    $formatter = class_exists(\NumberFormatter::class) ? new \NumberFormatter(str_replace('-', '_', app()->getLocale()).'@numbers=latn', \NumberFormatter::DECIMAL) : null;
    $count = static fn (int $n): string => (string) ($formatter?->format($n) ?: number_format($n));
@endphp

<nav aria-label="Pagination" {{ $attributes->class(['flex flex-col items-center gap-3 px-0 py-5 sm:flex-row sm:justify-between sm:p-5']) }}>
    @if ($from !== null)
        <p class="text-foreground/75 text-sm tabular-nums">
            Showing <span class="text-foreground font-medium">{{ $count($from) }}</span>
            to <span class="text-foreground font-medium">{{ $count($to) }}</span>
            @if ($total !== null) of <span class="text-foreground font-medium">{{ $count($total) }}</span> results @endif
        </p>
    @endif

    <div @class(['flex gap-2', 'sm:ms-auto' => $from === null])>
        <x-widget.button variant="neutral" size="sm" :href="$paginator->previousPageUrl()" :disabled="$paginator->onFirstPage()" icon-start="chevron-left" rel="prev" class="rtl:[&_svg]:rotate-180">Previous</x-widget.button>
        <x-widget.button variant="neutral" size="sm" :href="$paginator->nextPageUrl()" :disabled="! $paginator->hasMorePages()" icon-end="chevron-right" rel="next" class="rtl:[&_svg]:rotate-180">Next</x-widget.button>
    </div>
</nav>
