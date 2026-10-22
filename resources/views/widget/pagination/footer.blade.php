@props([
    // A length-aware paginator (paginate()).
    'paginator',
    // The sizes to offer; the current one is always offered too.
    'perPageOptions' => [10, 25, 50, 100],
    // The query parameter the size submits as; your controller reads it (and checks it against its own list).
    'perPageName' => 'per_page',
    // Livewire: the component property for rows per page, e.g. "perPage". Changing it updates in place and goes
    // back to page 1 (resetPage, from WithPagination), as the form does without Livewire.
    'perPageModel' => null,
])

@php
    $pageName = $paginator->getPageName();
    $selectId = app(\Bladewell\Support\ElementIds::class)->claim('per-page-'.$pageName);
    $perPage = $paginator->perPage();
    // The current size is always selectable, even if the controller allowed one that isn't offered here.
    $options = collect($perPageOptions)->push($perPage)->map(fn (mixed $size): int => (int) $size)->unique()->sort()->values();

    // Keep the rest of the query (filters, sorting). The page is dropped: a new page size starts again at page 1.
    parse_str(parse_url($paginator->url(1), PHP_URL_QUERY) ?? '', $query);
    unset($query[$pageName], $query[$perPageName]);
    $hidden = collect(\Illuminate\Support\Arr::dot($query))
        ->filter(fn (mixed $value): bool => is_scalar($value))
        // Arr::dot gives "filter.status"; a form field needs "filter[status]".
        ->mapWithKeys(fn (mixed $value, string $key): array => [preg_replace('/\.([^.]+)/', '[$1]', $key) => $value]);
@endphp

<div {{ $attributes->class(['flex flex-wrap items-center justify-center gap-x-6 gap-y-3 px-0 py-5 text-sm sm:justify-between sm:p-5']) }}>
    {{-- A GET form: resources/js/widget/pagination submits it as soon as the size changes, and hides Apply. --}}
    {{-- data-per-page-model: Livewire sends the change itself, so resources/js/widget/pagination doesn't submit. --}}
    <form method="GET" action="{{ $paginator->path() }}{{ $paginator->fragment() ? '#'.$paginator->fragment() : '' }}" data-per-page @if ($perPageModel) data-per-page-model @endif class="flex items-center gap-2">
        @foreach ($hidden as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        <label for="{{ $selectId }}" class="text-foreground/75">Rows per page</label>
        {{-- A native select: a few sizes, the phone's own picker, and it works without JavaScript. Its arrow is drawn here
             (appearance-none hides the browser's), so it has room from the edge and matches the other fields. --}}
        <span class="relative inline-flex">
            <select
                id="{{ $selectId }}"
                name="{{ $perPageName }}"
                @if ($perPageModel) wire:model.live="{{ $perPageModel }}" wire:change="resetPage('{{ $pageName }}')" @endif
                class="bg-field border-line hover:border-primary focus:border-primary h-8 cursor-pointer appearance-none rounded border ps-2.5 pe-8 tabular-nums outline-none"
            >
                @foreach ($options as $size)
                    <option value="{{ $size }}" @selected($size === $perPage)>{{ $size }}</option>
                @endforeach
            </select>
            <x-widget.icon name="chevron-down" class="text-foreground/70 pointer-events-none absolute end-2.5 top-1/2 size-4 -translate-y-1/2" />
        </span>
        {{-- Only without JavaScript (resources/js/widget/pagination submits on change). Decided by CSS, the scripting media
             feature, which the browser knows before it first paints, so it never shows and then vanishes. --}}
        <x-widget.button type="submit" variant="neutral" size="sm" :submit-guard="false" data-per-page-apply class="not-noscript:hidden">Apply</x-widget.button>
    </form>

    <div class="flex items-center gap-4">
        <p class="text-foreground/75 tabular-nums">
            <span class="text-foreground font-medium">{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}</span>
            of {{ number_format($paginator->total()) }}
        </p>
        <nav aria-label="Pagination" class="flex items-center gap-1">
            <x-widget.button variant="neutral" size="sm" icon="chevron-left" label="Previous page" :href="$paginator->previousPageUrl()" :disabled="$paginator->onFirstPage()" rel="prev" />
            <x-widget.button variant="neutral" size="sm" icon="chevron-right" label="Next page" :href="$paginator->nextPageUrl()" :disabled="! $paginator->hasMorePages()" rel="next" />
        </nav>
    </div>
</div>
