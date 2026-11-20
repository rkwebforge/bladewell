@props([
    // A length-aware paginator (paginate()).
    'paginator',
    // The pages as one joined, bordered group with the current page filled, instead of separate links.
    'segmented' => false,
])

@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();

    // Seven slots from the sm breakpoint up: first, last, the current page with its neighbours, and "…" for gaps.
    $wide = match (true) {
        $last <= 7 => range(1, $last),
        $current <= 4 => [...range(1, 5), $last],
        $current >= $last - 3 => [1, ...range($last - 4, $last)],
        default => [1, $current - 1, $current, $current + 1, $last],
    };
    // Five on phones, where seven don't fit in 320px: first, current and last.
    $narrow = match (true) {
        $last <= 5 => range(1, $last),
        $current <= 3 => [1, 2, 3, $last],
        $current >= $last - 2 => [1, $last - 2, $last - 1, $last],
        default => [1, $current, $last],
    };

    // One list for both, so every link is rendered once: each slot (and each "…", keyed by the page it
    // follows) says which layouts show it.
    // The pages in each layout that a "…" follows.
    $gaps = fn (array $pages): array => array_values(array_filter($pages, fn (int $page, int $i): bool => isset($pages[$i + 1]) && $pages[$i + 1] > $page + 1, ARRAY_FILTER_USE_BOTH));
    [$wideGaps, $narrowGaps] = [$gaps($wide), $gaps($narrow)];
    $visibility = fn (bool $inWide, bool $inNarrow): string => match (true) {
        $inWide && $inNarrow => '',
        $inWide => 'max-sm:hidden',
        default => 'sm:hidden',
    };
    $slots = [];
    foreach (collect([...$wide, ...$narrow])->unique()->sort()->values() as $page) {
        $slots[] = ['page' => $page, 'class' => $visibility(in_array($page, $wide, true), in_array($page, $narrow, true))];
        [$inWide, $inNarrow] = [in_array($page, $wideGaps, true), in_array($page, $narrowGaps, true)];
        if ($inWide || $inNarrow) {
            $slots[] = ['page' => '…', 'class' => $visibility($inWide, $inNarrow)];
        }
    }

    // segmented: the same slots as one joined, bordered group with the current page filled.
    // Focus rings are inset there, since the group clips anything drawn outside it.
    // Both looks use 28px cells on phones (the "…" narrower still), so all seven fit inside a table on a 320px screen.
    $link = $segmented ? 'hover:bg-field focus-visible:ring-primary outline-none focus-visible:ring-2 focus-visible:ring-inset' : 'hover:bg-field focus-visible:ring-primary outline-none focus-visible:ring-2';
    $arrow = $segmented
        ? 'tap-target-y relative text-foreground grid size-7 shrink-0 place-items-center sm:size-10'
        : 'tap-target-y relative text-foreground grid size-7 shrink-0 place-items-center rounded-md sm:size-9';
    $number = $segmented
        ? 'tap-target-y relative grid h-7 min-w-7 shrink-0 place-items-center px-1 tabular-nums sm:h-10 sm:min-w-10 sm:px-3'
        : 'tap-target-y relative min-w-7 shrink-0 rounded-md px-1 py-1 text-center tabular-nums sm:min-w-8 sm:px-3';
    $currentLook = $segmented ? 'bg-primary-fill text-on-primary font-semibold' : 'text-primary font-bold';
    $gap = $segmented ? 'text-muted grid h-7 w-5 shrink-0 place-items-center select-none sm:h-10 sm:w-auto sm:min-w-10' : 'text-muted shrink-0 px-1 select-none sm:px-2';
    // A faded cell would fade its border too, so segmented dims only the icon.
    $off = $segmented ? 'text-muted' : 'opacity-30';
@endphp

<nav aria-label="Pagination" {{ $attributes->class(['flex items-center justify-center px-0 py-5 sm:p-5', 'gap-0.5 sm:gap-1' => ! $segmented]) }}>
    @if ($segmented)
        <div class="border-line divide-line bg-surface flex divide-x overflow-hidden rounded-xl border">
    @endif

    @if ($paginator->onFirstPage())
        <span class="{{ $arrow }} {{ $off }}" aria-disabled="true"><x-widget.icon name="chevron-left" class="size-6 rtl:rotate-180" /></span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page" class="{{ $arrow }} {{ $link }}"><x-widget.icon name="chevron-left" class="size-6 rtl:rotate-180" /></a>
    @endif

    @foreach ($slots as $slot)
        @if ($slot['page'] === '…')
            <span class="{{ $gap }} {{ $slot['class'] }}" aria-hidden="true">…</span>
        @elseif ($slot['page'] === $current)
            <span aria-current="page" class="{{ $number }} {{ $currentLook }} {{ $slot['class'] }}">{{ $slot['page'] }}</span>
        @else
            <a href="{{ $paginator->url($slot['page']) }}" aria-label="Page {{ $slot['page'] }}" class="{{ $number }} text-foreground {{ $link }} {{ $slot['class'] }}">{{ $slot['page'] }}</a>
        @endif
    @endforeach

    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page" class="{{ $arrow }} {{ $link }}"><x-widget.icon name="chevron-right" class="size-6 rtl:rotate-180" /></a>
    @else
        <span class="{{ $arrow }} {{ $off }}" aria-disabled="true"><x-widget.icon name="chevron-right" class="size-6 rtl:rotate-180" /></span>
    @endif

    @if ($segmented)
        </div>
    @endif
</nav>
