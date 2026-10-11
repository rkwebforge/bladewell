@props([
    // Needed with selectable, filters or hideable columns (it names their forms and menu); a paginated table gets one from
    // its page name. Page links end in #id, so a new page opens scrolled to the table.
    'id' => null,
    // Labels, or arrays: ['label' => 'Amount', 'key' => 'amount', 'align' => 'end', 'sortable' => true, 'hideable' => true].
    // hideLabel => true keeps the heading for screen readers only, for a column whose cells say what they are (row actions).
    // align is start (default), center or end; put numbers at the end so their digits line up. hideable lists the
    // column in the Columns menu (add 'hidden' => true to start it hidden); up to 12 columns.
    'columns' => [],
    // A paginator (paginate(), simplePaginate() or cursorPaginate()) or any array or collection; the slot draws the rows.
    'rows' => null,
    // Tints every other row.
    'striped' => false,
    // Kept so older markup still renders; start alignment is now the default for every column.
    'alignLeft' => false,
    // false leaves out the header row.
    'header' => true,
    // With a paginator: drawer, numbers, segmented, jump, footer (rows per page), summary or infinite (scrolls inside
    // itself, loading more as you go); false for none.
    'pagination' => 'drawer',
    // Pads a short last page with blank rows, so the pagination bar stays where it was.
    'fill' => true,
    // What the empty state says. With filters, it also offers Clear filters.
    'empty' => 'No data found',
    // The table's name for screen readers (a hidden <caption>); also names its scroll area and filters.
    'caption' => null,
    // pagination="infinite": rows in view at once (1 to 50) before the table scrolls inside itself.
    'visibleRows' => 10,
    // comfortable (56px rows) or compact (44px), for dense admin screens.
    'density' => 'comfortable',
    // e.g. "24rem": the body scrolls inside the table and the header stays in view.
    'maxHeight' => null,
    // Placeholder rows while data is on its way (e.g. while a Livewire or fetch request runs).
    'loading' => false,
    // How many placeholder rows loading draws (and while-loading="skeleton", without a paginator).
    'skeletonRows' => 5,
    // While a page, sort or filter change loads: dim (the rows stay, faded, with a spinner) or skeleton (placeholder rows).
    'whileLoading' => 'dim',
    // Seconds to keep fetched pages in memory: going back to a page, sort or filter seen within that time is instant.
    // In memory only, never written to storage, and gone on reload.
    'cacheFor' => null,
    // The sorted column's key. Defaults to the query string, but only for keys marked sortable. With multi-sort, a
    // comma list in order of priority, e.g. "status,amount". Clicking a header cycles ascending, descending, then no sort.
    'sort' => null,
    // asc or desc (a comma list with multi-sort, one per key); defaults to the query string.
    'direction' => null,
    // Lets people sort by several columns: Shift+click (or Shift+Enter) a header to add it; a plain click sorts by it alone.
    'multiSort' => false,
    // The query parameters the sort links set; change them when two sortable tables share a page. In a Livewire
    // component, the properties they set.
    'sortName' => 'sort',
    // The same, for the direction parameter.
    'directionName' => 'direction',
    // A checkbox per row (give rows a :value) and a bar for the bulk slot's buttons. Needs an id.
    'selectable' => false,
    // What the row checkboxes submit as: selected[].
    'selectName' => 'selected',
    // Livewire: binds the row checkboxes to this property (wire:model), e.g. "selected", so actions get the ids.
    'selectModel' => null,
    // Livewire, pagination="footer": binds rows per page to this property, e.g. "perPage", and goes back to page 1.
    'perPageModel' => null,
    // Where the bulk form submits the ticked rows.
    'bulkAction' => null,
    // The bulk form's method: GET, POST, PUT, PATCH or DELETE, in any case.
    'bulkMethod' => 'POST',
    // Opt-in: below the sm breakpoint, each row becomes a card of label / value pairs instead of scrolling sideways.
    'stack' => false,
])

@php
    // What other widgets call these: say what this one calls them, rather than let them through as plain attributes
    // that do nothing.
    foreach (['no-results' => 'empty'] as $other => $own) {
        if ($attributes->hasAny([$other, \Illuminate\Support\Str::camel($other)])) {
            throw new \InvalidArgumentException("<x-widget.table> calls it {$own}, not {$other}.");
        }
    }
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if ($pagination === 'load-more') {
        throw new \InvalidArgumentException('<x-widget.table> calls it infinite, not load-more: pagination="infinite" loads more rows as you scroll. (load-more is <x-widget.pagination type="load-more">.)');
    }
    if (! in_array($pagination, ['drawer', 'numbers', 'segmented', 'jump', 'footer', 'summary', 'infinite', false], true)) {
        throw new \InvalidArgumentException("Unknown pagination [{$pagination}] for <x-widget.table>. Use one of: drawer, numbers, segmented, jump, footer, summary, infinite, or false for none.");
    }
    if (! in_array($density, ['comfortable', 'compact'], true)) {
        throw new \InvalidArgumentException("Unknown density [{$density}] for <x-widget.table>. Use one of: comfortable, compact.");
    }
    if (! in_array($whileLoading, ['dim', 'skeleton'], true)) {
        throw new \InvalidArgumentException("Unknown while-loading [{$whileLoading}] for <x-widget.table>. Use one of: dim, skeleton.");
    }
    // paginate() and simplePaginate() give a Paginator; cursorPaginate() (fastest on very large tables) a CursorPaginator.
    $isCursor = $rows instanceof \Illuminate\Contracts\Pagination\CursorPaginator;
    $isPaginator = $isCursor || $rows instanceof \Illuminate\Contracts\Pagination\Paginator;
    // Livewire's paginators use a relative path ("orders"): from /orders, their links would open /orders/orders.
    if ($isPaginator && ! preg_match('#^(/|[a-z][a-z0-9+.-]*:)#i', (string) $rows->path())) {
        $rows->withPath(url()->to((string) $rows->path()));
    }
    // During a Livewire update the request is Livewire's own endpoint, so the page's URL and query come from
    // Livewire and the Referer (the browser's current URL, filters included) instead.
    $livewire = class_exists(\Livewire\Livewire::class) && \Livewire\Livewire::isLivewireRequest();
    $pageUrl = $livewire ? \Livewire\Livewire::originalUrl() : request()->url();
    $pageQuery = request()->query();
    if ($livewire) {
        $referer = (string) request()->headers->get('referer');
        $pageQuery = [];
        if (parse_url($referer, PHP_URL_PATH) === parse_url($pageUrl, PHP_URL_PATH)) {
            parse_str((string) parse_url($referer, PHP_URL_QUERY), $pageQuery);
        }
    }
    $isEmpty = ! $loading && $rows !== null && count($rows) === 0;
    // pagination="infinite": the table scrolls inside itself, showing `visibleRows` at a time; rows load at
    // either end as you scroll and far-off batches are dropped, so the DOM stays small at any data size.
    $infinite = $pagination === 'infinite' && $isPaginator;
    // Blank rows on a short last page keep the table height, so the pagination bar doesn't jump.
    // Not with infinite scroll: the table grows as rows arrive, and blanks would sit between batches.
    $fillers = ($fill && ! $infinite && ! $loading && $isPaginator && $rows->hasPages()) ? max(0, $rows->perPage() - count($rows)) : 0;
    // Rows render before this template, so their markup tells whether any expands; those rows end with a
    // chevron cell (table/row), which needs a header cell of its own.
    $expandable = str_contains((string) $slot, 'data-expandable');

    $columns = collect($columns)->values()->map(fn (mixed $column): array => [
        'label' => (string) (is_array($column) ? ($column['label'] ?? '') : $column),
        'key' => is_array($column) ? ($column['key'] ?? null) : null,
        'sortable' => is_array($column) && ! empty($column['sortable']) && isset($column['key']),
        'align' => is_array($column) && in_array($column['align'] ?? null, ['center', 'end'], true) ? $column['align'] : 'start',
        'hideable' => is_array($column) && ! empty($column['hideable']),
        'hidden' => is_array($column) && ! empty($column['hideable']) && ! empty($column['hidden']),
        'hideLabel' => is_array($column) && ! empty($column['hideLabel']),
    ]);
    $hideable = $columns->contains('hideable', true);
    $colspan = max(1, $columns->count() + ($expandable ? 1 : 0) + ($selectable ? 1 : 0));

    if ($selectable && $id === null) {
        throw new \InvalidArgumentException('<x-widget.table selectable> needs an id: the row checkboxes submit with a form named after it.');
    }
    $hasFilters = isset($filters) && $filters->isNotEmpty();
    if ($hasFilters && $id === null && ! $isPaginator) {
        throw new \InvalidArgumentException('<x-widget.table> with filters needs an id: filtering swaps the table found by it in the fetched page.');
    }
    if ($hideable && $id === null && ! $isPaginator) {
        throw new \InvalidArgumentException('<x-widget.table> with hideable columns needs an id: the Columns menu is named after it.');
    }
    // Filters and the Columns menu sit in a toolbar above the table, which then has a card of its own.
    $hasToolbar = $hasFilters || $hideable;
    $hasFooter = isset($footer) && $footer->isNotEmpty();
    $skeleton = $whileLoading === 'skeleton';
    $cacheFor = is_numeric($cacheFor) && (int) $cacheFor > 0 ? min((int) $cacheFor, 3600) : null;

    // Page links end in #id, so the new page opens scrolled to this table instead of the top.
    // Derived from the page name; ElementIds suffixes it if two tables share one (e.g. both use "page").
    $id = match (true) {
        $id !== null => app(\Bladewell\Support\ElementIds::class)->claim($id, explicit: true),
        $isPaginator => app(\Bladewell\Support\ElementIds::class)->claim('table-'.($isCursor ? $rows->getCursorName() : $rows->getPageName())),
        default => null,
    };
    if ($isPaginator && $id && ! $rows->fragment()) {
        $rows->fragment($id);
    }

    // Sorting: only a key the columns mark sortable counts, whatever the query string says. This only draws the
    // state; your controller must check the key against its own list before it reaches orderBy().
    $sortable = $columns->where('sortable')->pluck('key')->all();
    // The order as [key => direction], highest priority first: one key, or with multi-sort, the comma lists of sort
    // and direction side by side (sort=status,amount&direction=asc,desc). A single sort reads as it always has.
    $sortValue = $sort ?? $pageQuery[$sortName] ?? null;
    $directionValue = $direction ?? $pageQuery[$directionName] ?? null;
    $directions = explode(',', is_string($directionValue) ? $directionValue : '');
    $order = [];
    foreach (explode(',', is_string($sortValue) ? $sortValue : '') as $i => $key) {
        if (in_array($key, $sortable, true) && ! isset($order[$key])) {
            $order[$key] = ($directions[$i] ?? null) === 'desc' ? 'desc' : 'asc';
        }
    }
    $order = $multiSort ? $order : array_slice($order, 0, 1, true);
    $sort = array_key_first($order);
    $direction = $sort !== null ? $order[$sort] : 'asc';
    $orderUrl = function (array $next) use ($sortName, $directionName, $isPaginator, $isCursor, $rows, $id, $pageUrl, $pageQuery): string {
        $query = array_merge($pageQuery, [$sortName => implode(',', array_keys($next)), $directionName => implode(',', $next)]);
        if ($next === []) {
            unset($query[$sortName], $query[$directionName]);
        }
        // A new order starts again at the first page.
        if ($isPaginator) {
            unset($query[$isCursor ? $rows->getCursorName() : $rows->getPageName()]);
        }

        return $pageUrl.($query ? '?'.\Illuminate\Support\Arr::query($query) : '').($id ? '#'.$id : '');
    };
    // A plain click sorts by this column alone, in three steps from its own state: ascending, descending, then no sort
    // at all (back to the order the rows came in).
    $sortNext = fn (string $key): ?string => match ($order[$key] ?? null) {
        null => 'asc',
        'asc' => 'desc',
        default => null,
    };
    $sortUrl = fn (string $key): string => $orderUrl(($next = $sortNext($key)) ? [$key => $next] : []);
    // What that click does, for screen readers: the link text ends with it.
    $sortHint = fn (string $key): string => match ($sortNext($key)) {
        'asc' => 'sort ascending',
        'desc' => 'sort descending',
        default => 'remove sort',
    };
    // Shift+click with multi-sort: add the column last, or flip it, or (after descending) drop it.
    $addSortUrl = function (string $key) use ($order, $orderUrl): string {
        $next = $order;
        if (! isset($next[$key])) {
            $next[$key] = 'asc';
        } elseif ($next[$key] === 'asc') {
            $next[$key] = 'desc';
        } else {
            unset($next[$key]);
        }

        return $orderUrl($next);
    };

    // Per-column alignment, written out in full for Tailwind (positions 1 to 13, which covers 12 columns plus
    // the checkbox column). :where() gives these no weight, so a class on your own <td> always wins.
    $alignAt = [
        'center' => [1 => '[:where(&_tr>:nth-child(1))]:text-center', 2 => '[:where(&_tr>:nth-child(2))]:text-center', 3 => '[:where(&_tr>:nth-child(3))]:text-center', 4 => '[:where(&_tr>:nth-child(4))]:text-center', 5 => '[:where(&_tr>:nth-child(5))]:text-center', 6 => '[:where(&_tr>:nth-child(6))]:text-center', 7 => '[:where(&_tr>:nth-child(7))]:text-center', 8 => '[:where(&_tr>:nth-child(8))]:text-center', 9 => '[:where(&_tr>:nth-child(9))]:text-center', 10 => '[:where(&_tr>:nth-child(10))]:text-center', 11 => '[:where(&_tr>:nth-child(11))]:text-center', 12 => '[:where(&_tr>:nth-child(12))]:text-center', 13 => '[:where(&_tr>:nth-child(13))]:text-center'],
        'end' => [1 => '[:where(&_tr>:nth-child(1))]:text-end', 2 => '[:where(&_tr>:nth-child(2))]:text-end', 3 => '[:where(&_tr>:nth-child(3))]:text-end', 4 => '[:where(&_tr>:nth-child(4))]:text-end', 5 => '[:where(&_tr>:nth-child(5))]:text-end', 6 => '[:where(&_tr>:nth-child(6))]:text-end', 7 => '[:where(&_tr>:nth-child(7))]:text-end', 8 => '[:where(&_tr>:nth-child(8))]:text-end', 9 => '[:where(&_tr>:nth-child(9))]:text-end', 10 => '[:where(&_tr>:nth-child(10))]:text-end', 11 => '[:where(&_tr>:nth-child(11))]:text-end', 12 => '[:where(&_tr>:nth-child(12))]:text-end', 13 => '[:where(&_tr>:nth-child(13))]:text-end'],
    ];
    $offset = $selectable ? 1 : 0;
    $alignment = $columns->map(fn (array $column, int $i): string => $alignAt[$column['align']][$i + 1 + $offset] ?? '')->filter()->implode(' ');

    // Hidden columns: data-hide on the <table> lists positions (c3 c5), and these rules hide those cells in every row
    // but the ones that span the table (details, empty state, fillers). Written out in full for Tailwind, as above.
    $hideRules = $hideable ? implode(' ', [
        '[&[data-hide~=c1]_tr:not(:has(>[colspan]))>:nth-child(1)]:hidden', '[&[data-hide~=c2]_tr:not(:has(>[colspan]))>:nth-child(2)]:hidden',
        '[&[data-hide~=c3]_tr:not(:has(>[colspan]))>:nth-child(3)]:hidden', '[&[data-hide~=c4]_tr:not(:has(>[colspan]))>:nth-child(4)]:hidden',
        '[&[data-hide~=c5]_tr:not(:has(>[colspan]))>:nth-child(5)]:hidden', '[&[data-hide~=c6]_tr:not(:has(>[colspan]))>:nth-child(6)]:hidden',
        '[&[data-hide~=c7]_tr:not(:has(>[colspan]))>:nth-child(7)]:hidden', '[&[data-hide~=c8]_tr:not(:has(>[colspan]))>:nth-child(8)]:hidden',
        '[&[data-hide~=c9]_tr:not(:has(>[colspan]))>:nth-child(9)]:hidden', '[&[data-hide~=c10]_tr:not(:has(>[colspan]))>:nth-child(10)]:hidden',
        '[&[data-hide~=c11]_tr:not(:has(>[colspan]))>:nth-child(11)]:hidden', '[&[data-hide~=c12]_tr:not(:has(>[colspan]))>:nth-child(12)]:hidden',
        '[&[data-hide~=c13]_tr:not(:has(>[colspan]))>:nth-child(13)]:hidden',
    ]) : '';
    // Each hideable column's cell position (the checkbox column counts), for the menu and the initial data-hide.
    $toggles = $columns->map(fn (array $column, int $i): array => [...$column, 'position' => $i + 1 + $offset])->where('hideable', true)->values();
    $hiddenAtFirst = $toggles->where('hidden', true)->map(fn (array $column): string => 'c'.$column['position'])->implode(' ');

    // A CSS length only; anything else is dropped rather than written into a style attribute.
    $maxHeight = is_string($maxHeight) && preg_match('/^\d+(\.\d+)?(px|rem|em|vh|dvh|svh)$/', $maxHeight) ? $maxHeight : null;
    $sticky = $infinite || $maxHeight !== null;
    // The bordered box: the root itself, or with filters, the card below the toolbar.
    $card = 'border-line bg-surface relative w-full overflow-clip rounded-3xl border';
    $rowHeight = 'h-14 group-data-[density=compact]/table:h-11';
    $formId = $selectable ? $id.'-selection' : null;
    if (! in_array(strtoupper((string) $bulkMethod), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        throw new \InvalidArgumentException("Unknown bulk-method [{$bulkMethod}] for <x-widget.table>. Use one of: GET, POST, PUT, PATCH, DELETE.");
    }
    $bulkMethod = strtoupper((string) $bulkMethod);
@endphp

{{-- Cells are plain <th>/<td>; spacing and alignment come from these table-level rules. --}}
{{-- overflow-clip, not overflow-hidden: "hidden" makes this a scroll container and Chrome then ignores scroll-mt on anchor jumps. --}}
{{-- data-page-name / data-sort-name / data-direction-name: in a Livewire component, resources/js/widget/table turns
     page and sort links into calls on the component (gotoPage, and setting these properties) instead of fetching the page. --}}
<div data-table-root @if ($id) id="{{ $id }}" @endif
    @if ($isPaginator) data-page-name="{{ $isCursor ? $rows->getCursorName() : $rows->getPageName() }}" @if ($isCursor) data-page-cursor @endif @endif
    @if ($sortable) data-sort-name="{{ $sortName }}" data-direction-name="{{ $directionName }}" @endif
    @if ($selectable && $selectModel) data-select-model="{{ $selectModel }}" @endif
    @if ($skeleton) data-while-loading="skeleton" @endif
    @if ($cacheFor) data-cache-for="{{ $cacheFor }}" @endif
    {{-- Read out after filtering ("24 results"). --}}
    @if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) data-total="{{ $rows->total() }}" @endif
    @if ($infinite)
        data-infinite
        data-url="{{ $livewire ? $pageUrl.($pageQuery ? '?'.\Illuminate\Support\Arr::query($pageQuery) : '') : request()->fullUrl() }}"
        @if ($rows->nextPageUrl()) data-next="{{ $rows->nextPageUrl() }}" @endif
        @if ($rows->previousPageUrl()) data-prev="{{ $rows->previousPageUrl() }}" @endif
    @endif
    @if ($stack) data-stack @endif
    {{-- With filters, the toolbar sits above the card and the card is a box of its own inside the root. The root stays
         the group (aria-busy, data-stack) and the one element the script swaps; the toolbar must stay its direct child. --}}
    {{ $attributes->class(['group/root relative w-full scroll-mt-6', $card => ! $hasToolbar]) }}>
    @if ($hasToolbar)
        {{-- The toolbar: with filters, a GET form (filters as you type, after a pause, or as you pick); otherwise just a
             row for the Columns menu. A direct child of the root, which resources/js/widget/table keeps in place while
             filtering swaps the rest, so the field you're in keeps focus. Your fields keep their own values from the query
             string; the sort rides along in hidden fields. --}}
        <{{ $hasFilters ? 'form' : 'div' }}
            data-table-toolbar
            @if ($hasFilters) data-table-filters method="GET" action="{{ $pageUrl }}{{ $id ? '#'.$id : '' }}" role="search" aria-label="{{ $caption ? 'Filter '.$caption : 'Filter the table' }}" @endif
            class="mb-4 flex flex-wrap items-stretch gap-3 max-sm:*:w-full"
        >
            @if ($hasFilters)
                @if ($sort)
                    <input type="hidden" name="{{ $sortName }}" value="{{ implode(',', array_keys($order)) }}">
                    <input type="hidden" name="{{ $directionName }}" value="{{ implode(',', $order) }}">
                @endif
                {{ $filters }}
                {{-- Only without JavaScript, when nothing filters as you type. Decided by CSS (the scripting media feature),
                     which the browser knows before it first paints, so it never shows and then vanishes. --}}
                <x-widget.button type="submit" variant="neutral" :submit-guard="false" class="not-noscript:hidden">Apply</x-widget.button>
            @endif
            @if ($hideable)
                {{-- Only with JavaScript, the other way round: without it every column shows and nothing would toggle. At the
                     end of the row, as tall as the fields beside it. The ticks are unnamed, so they never ride along with
                     the filters. --}}
                <div class="ms-auto noscript:hidden">
                    <x-widget.button type="button" variant="neutral" icon-start="eye" popovertarget="{{ $id }}-columns" data-table-columns-toggle class="h-full min-h-12 max-sm:w-full">Columns</x-widget.button>
                    <div id="{{ $id }}-columns" popover data-table-columns data-fixed="{{ $columns->count() - $toggles->count() }}" class="border-line bg-surface text-foreground m-0 w-60 rounded-2xl border p-2 opacity-0 shadow-lg data-placed:opacity-100">
                        <fieldset>
                            <legend class="text-muted px-3 pt-1 pb-2 text-xs font-medium">Show columns</legend>
                            @foreach ($toggles as $column)
                                <label class="hover:bg-field flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-sm has-disabled:cursor-not-allowed has-disabled:opacity-50">
                                    <input type="checkbox" data-table-column="{{ $column['position'] }}" @checked(! $column['hidden']) class="accent-primary size-4">
                                    {{ $column['label'] }}
                                </label>
                            @endforeach
                        </fieldset>
                    </div>
                </div>
            @endif
        </{{ $hasFilters ? 'form' : 'div' }}>
        <div data-table-card class="{{ $card }}">
    @endif

    {{-- Shown while a page change is loading (aria-busy), but only after 300ms so fast responses never flash it. --}}
    @unless ($loading || $skeleton)
        <div data-table-loading aria-hidden="true" class="pointer-events-none absolute inset-0 z-10 grid place-items-center opacity-0 transition-opacity delay-0 group-aria-busy/root:opacity-100 group-aria-busy/root:delay-300 motion-reduce:transition-none">
            <span class="bg-surface grid size-12 place-items-center rounded-full shadow-lg">
                <span class="border-line border-t-primary size-6 animate-spin rounded-full border-2"></span>
            </span>
        </div>
    @endunless

    @if ($selectable)
        {{-- The bulk bar. Row checkboxes live in the table but submit with this form through their form attribute,
             so the pagination forms below never end up nested inside it. resources/js/widget/table hides the bar
             until something is selected; without JS it simply stays visible. --}}
        <div data-table-bulk class="border-line bg-primary/5 flex min-h-14 flex-wrap items-center gap-x-4 gap-y-2 border-b px-4 py-2.5 text-sm sm:px-5">
            <form id="{{ $formId }}" method="{{ $bulkMethod === 'GET' ? 'GET' : 'POST' }}" @if ($bulkAction) action="{{ $bulkAction }}" @endif class="contents">
                @unless ($bulkMethod === 'GET')
                    @csrf
                    @unless ($bulkMethod === 'POST')
                        @method($bulkMethod)
                    @endunless
                @endunless
                <span data-table-selected-count role="status" class="text-foreground font-medium">Select rows to act on them</span>
                <button type="button" data-table-clear class="text-link hover:text-link-hover focus-visible:ring-primary rounded-sm outline-none focus-visible:ring-2">Clear</button>
                @isset($bulk)
                    <div class="ms-auto flex flex-wrap gap-2 max-sm:w-full max-sm:*:flex-1">{{ $bulk }}</div>
                @endisset
            </form>
        </div>
    @endif

    @if ($selectable && $stack)
        {{-- Stacked cards hide the header on phones, and the select-all box with it; this one stands in for it there. --}}
        <label class="border-line flex items-center gap-2 border-b px-4 py-3 text-sm sm:hidden">
            <input type="checkbox" data-table-select-all class="accent-primary size-4 cursor-pointer">
            Select all
        </label>
    @endif

    @if ($stack && $header && $columns->contains(fn (array $column): bool => $column['sortable']))
        {{-- And the header's sort links, which would otherwise be out of reach on phones. --}}
        <nav aria-label="Sort" class="border-line flex flex-wrap items-center gap-x-4 gap-y-2 border-b px-4 py-3 text-sm sm:hidden">
            <span class="text-foreground/70">Sort by</span>
            @foreach ($columns->filter(fn (array $column): bool => $column['sortable']) as $column)
                @php $sorted = $sort === $column['key']; @endphp
                <a
                    href="{{ $sortUrl($column['key']) }}"
                    data-table-sort
                    @if ($sorted) aria-current="true" @endif
                    @class(['focus-visible:ring-primary inline-flex items-center gap-1 rounded-md outline-none focus-visible:ring-2', 'text-foreground font-medium' => $sorted, 'text-link hover:text-link-hover' => ! $sorted])
                >
                    {{ $column['label'] }}
                    @if ($sorted)
                        <x-widget.icon :name="$direction === 'desc' ? 'chevron-down' : 'chevron-up'" class="size-3.5" />
                    @endif
                    <span class="sr-only">, {{ $sortHint($column['key']) }}</span>
                </a>
            @endforeach
        </nav>
    @endif

    {{-- resources/js/widget/table makes this focusable (and labels it) only while it actually scrolls,
         so keyboard users can scroll a wide table without an extra tab stop on narrow ones. --}}
    <div data-table-scroll data-label="{{ $caption ?? 'Table' }}" @class([
        'focus-visible:outline-primary overflow-x-auto outline-none transition-opacity focus-visible:outline-2 focus-visible:-outline-offset-2',
        // Skeleton mode swaps the rows for placeholders instead of fading them.
        'group-aria-busy/root:opacity-50' => ! $skeleton,
        'max-h-[var(--table-window)] overflow-y-auto [overflow-anchor:none]' => $infinite,
        // A table that scrolls inside itself (infinite, or max-height) hides the native scrollbar: it runs down the
        // whole edge, beside the sticky header too. resources/js/widget/table draws thin overlay thumbs instead
        // (data-table-thumb), the vertical one starting below the header.
        '[scrollbar-width:none] [&::-webkit-scrollbar]:hidden' => $sticky,
        // Everywhere else a thin native scrollbar. Never both: the two scrollbar-width values would fight.
        '[scrollbar-width:thin]' => ! $sticky,
        'overflow-y-auto' => $maxHeight !== null,
        // Cards on phones don't scroll sideways.
        'max-sm:overflow-x-visible' => $stack,
        // Classes from the ranges at the end of base.css, not style="", so a strict Content Security Policy allows them.
        '[--table-window:calc(2.5rem+'.max(1, min(50, (int) $visibleRows)).'*4rem)]' => $infinite,
    ])
        @if (! $infinite && $maxHeight) data-max-height="{{ $maxHeight }}" @endif
    >
        <table
            @if ($striped) data-striped @endif
            @if ($hiddenAtFirst) data-hide="{{ $hiddenAtFirst }}" @endif
            data-density="{{ $density }}"
            {{-- On the table rather than the root: the root's aria-busy means "changing page" and dims everything. --}}
            @if ($loading) aria-busy="true" @endif
            @class([
                'group/table text-style-2 text-foreground min-w-full border-collapse tabular-nums',
                // Tighter on phones, where a table that doesn't stack has to fit what it can.
                '[:where(&_th,&_td)]:px-2 sm:[:where(&_th,&_td)]:px-3 [:where(&_th,&_td)]:text-start [:where(&_th,&_td)]:whitespace-nowrap [:where(&_tr>:first-child)]:ps-4 sm:[:where(&_tr>:first-child)]:ps-5 [:where(&_tr>:last-child)]:pe-4 sm:[:where(&_tr>:last-child)]:pe-5',
                '[&_th]:h-10 [&_th]:text-xs [&_th]:font-medium',
                // The last row's line would double up with the pagination bar's top border.
                '[&>tbody>tr:last-child]:border-b-0',
                $alignment,
                $hideRules,
                // Stacked: rows become cards below sm, each cell a "label  value" line (labels come from the header).
                // The header is hidden outright on phones, not sr-only: its sort links and select-all box would stay in the
                // Tab order while invisible. The stand-ins above the table take their place, and each cell keeps its label.
                'max-sm:block max-sm:[&_thead]:hidden max-sm:[&_tbody]:flex max-sm:[&_tbody]:flex-col max-sm:[&_tbody]:gap-3 max-sm:[&_tbody]:p-3 max-sm:[&_tr]:block max-sm:[&_tr]:h-auto! max-sm:[&_tr]:rounded-2xl max-sm:[&_tr]:border max-sm:[&_tr]:border-line max-sm:[&_tr]:py-2 max-sm:[&_td]:flex max-sm:[&_td]:items-center max-sm:[&_td]:justify-between max-sm:[&_td]:gap-4 max-sm:[&_td]:py-1.5 max-sm:[&_td]:ps-4! max-sm:[&_td]:pe-4! max-sm:[&_td]:text-end! max-sm:[&_td]:whitespace-normal max-sm:[&_td]:before:text-foreground/60 max-sm:[&_td]:before:text-start max-sm:[&_td]:before:content-[attr(data-label)] max-sm:[&_tfoot]:block max-sm:[&_tfoot]:px-3 max-sm:[&_tfoot]:pb-3' => $stack,
            ])
        >
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif

            @if ($header && $columns->isNotEmpty())
                <thead class="bg-field text-foreground/70">
                    <tr class="border-line border-b bg-inherit">
                        {{-- Sticky while the table scrolls inside itself, so the columns stay labelled. It needs an opaque
                             background to cover the rows passing under it: inherited from the <thead>. --}}
                        @if ($selectable)
                            <th scope="col" @class(['w-12', 'sticky top-0 z-[1] bg-inherit' => $sticky])>
                                <input type="checkbox" data-table-select-all aria-label="Select all rows on this page" class="accent-primary size-4 cursor-pointer align-middle">
                            </th>
                        @endif
                        @foreach ($columns as $column)
                            @php
                                // In the order at all (multi-sort can hold several), and where: 1 leads.
                                $ordered = $column['sortable'] && isset($order[$column['key']]);
                                $sorted = $ordered && $sort === $column['key'];
                                $columnDirection = $ordered ? $order[$column['key']] : null;
                                $rank = $ordered && count($order) > 1 ? array_search($column['key'], array_keys($order), true) + 1 : null;
                            @endphp
                            <th
                                scope="col"
                                data-label="{{ $column['label'] }}"
                                {{-- aria-sort on the leading column only, as ARIA asks; the others say their place in the link text. --}}
                                @if ($sorted) aria-sort="{{ $direction === 'desc' ? 'descending' : 'ascending' }}" @endif
                                @class(['sticky top-0 z-[1] bg-inherit' => $sticky])
                            >
                                @if ($column['sortable'])
                                    <a
                                        href="{{ $sortUrl($column['key']) }}"
                                        data-table-sort
                                        @if ($multiSort) data-sort-add="{{ $addSortUrl($column['key']) }}" @endif
                                        @class(['hover:text-foreground focus-visible:ring-primary -mx-1 inline-flex items-center gap-1 rounded-md px-1 py-0.5 outline-none focus-visible:ring-2', 'text-foreground' => $ordered])
                                    >
                                        {{ $column['label'] }}
                                        <x-widget.icon :name="$ordered ? ($columnDirection === 'desc' ? 'chevron-down' : 'chevron-up') : 'chevrons-up-down'" :class="$ordered ? 'size-3.5' : 'size-3.5 opacity-50'" />
                                        @if ($rank)
                                            <span aria-hidden="true" class="bg-primary/10 text-primary grid size-4 place-items-center rounded-full text-[0.625rem] font-semibold tabular-nums">{{ $rank }}</span>
                                        @endif
                                        {{-- Where it stands, when it isn't the lead, and what a click will do. --}}
                                        @if ($rank && ! $sorted)
                                            <span class="sr-only">, sorted {{ $columnDirection === 'desc' ? 'descending' : 'ascending' }}, priority {{ $rank }}</span>
                                        @endif
                                        <span class="sr-only">, {{ $sortHint($column['key']) }}{{ $multiSort ? '; Shift to add to the sort' : '' }}</span>
                                    </a>
                                @elseif ($column['hideLabel'])
                                    {{-- Still the column's name for screen readers: an empty header cell is announced as nothing. --}}
                                    <span class="sr-only">{{ $column['label'] }}</span>
                                @else
                                    {{ $column['label'] }}
                                @endif
                            </th>
                        @endforeach
                        @if ($expandable)
                            <th scope="col" @class(['w-12', 'sticky top-0 z-[1] bg-inherit' => $sticky])><span class="sr-only">Details</span></th>
                        @endif
                    </tr>
                </thead>
            @endif

            <tbody>
                @if ($loading)
                    @for ($i = 0; $i < max(1, (int) $skeletonRows); $i++)
                        <tr aria-hidden="true" class="{{ $rowHeight }} border-line border-b last:border-b-0">
                            @for ($c = 0; $c < $colspan; $c++)
                                {{-- Varying widths so the placeholder reads as text, not a grid of bars. --}}
                                <td><span class="{{ ['w-[70%]', 'w-[45%]', 'w-[60%]', 'w-[35%]', 'w-[55%]'][($i + $c) % 5] }} bg-line inline-block h-3 animate-pulse rounded-full align-middle motion-reduce:animate-none"></span></td>
                            @endfor
                        </tr>
                    @endfor
                    <tr class="sr-only"><td colspan="{{ $colspan }}">Loading</td></tr>
                @elseif ($isEmpty)
                    <tr>
                        <td colspan="{{ $colspan }}" @class(['py-24! text-center!', 'max-sm:block!' => $stack])>
                            {{-- In a table wider than the screen, centred on the visible part (width set by the JS), not the whole table. --}}
                            <div class="text-muted sticky start-4 flex w-[calc(var(--table-visible,100%)-2rem)] flex-col sm:start-5 sm:w-[calc(var(--table-visible,100%)-2.5rem)] items-center gap-3">
                                <x-widget.icon name="inbox" class="size-10" />
                                <p>{{ $empty }}</p>
                                @if ($hasFilters)
                                    {{-- Without JS, the page without any query; resources/js/widget/table clears only this table's filters. --}}
                                    <a href="{{ $pageUrl }}{{ $id ? '#'.$id : '' }}" data-table-filters-clear class="text-link hover:text-link-hover focus-visible:ring-primary rounded-sm outline-none focus-visible:ring-2">Clear filters</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @else
                    {{ $slot }}

                    @for ($i = 0; $i < $fillers; $i++)
                        {{-- Same 1px bottom border as real rows (just invisible), or the table shrinks on a short last page. --}}
                        <tr aria-hidden="true" @class([$rowHeight, 'border-b border-transparent', 'max-sm:hidden' => $stack])><td colspan="{{ $colspan }}"></td></tr>
                    @endfor
                @endif
            </tbody>

            @if ($skeleton && ! $loading)
                {{-- Shown by resources/js/widget/table in place of the rows while a change loads (after a moment, so fast
                     responses never flash it). As many rows as a full page, so the table keeps its height. --}}
                <tbody data-table-skeleton hidden aria-hidden="true">
                    @for ($i = 0; $i < max(1, min(50, $isPaginator ? $rows->perPage() : (int) $skeletonRows)); $i++)
                        <tr class="{{ $rowHeight }} border-line border-b last:border-b-0">
                            @for ($c = 0; $c < $colspan; $c++)
                                <td><span class="{{ ['w-[70%]', 'w-[45%]', 'w-[60%]', 'w-[35%]', 'w-[55%]'][($i + $c) % 5] }} bg-line inline-block h-3 animate-pulse rounded-full align-middle motion-reduce:animate-none"></span></td>
                            @endfor
                        </tr>
                    @endfor
                </tbody>
            @endif

            @if ($hasFooter && ! $isEmpty && ! $loading)
                {{-- Your rows (totals, averages) under the data, e.g. <tr><td>Total</td><td>…</td></tr>; they line up with the
                     columns like any row. On phones with stack, a labelled card of its own. --}}
                <tfoot class="bg-field/60 border-line text-foreground border-t font-medium [&>tr]:h-14 group-data-[density=compact]/table:[&>tr]:h-11">
                    {{ $footer }}
                </tfoot>
            @endif
        </table>
    </div>

    @if ($sticky)
        {{-- Overlay scrollbars for a table that scrolls inside itself, placed by the JS: the vertical one over the rows
             (below the header), the horizontal one along the bottom when the table is wider than its box.
             Decorative for assistive tech; wheel, touch and arrow keys scroll natively. Drag them to scroll too. --}}
        <div data-table-thumb aria-hidden="true" hidden class="bg-line-strong hover:bg-muted active:bg-muted absolute end-0 z-[2] w-1.5 cursor-grab touch-none rounded-full transition-colors select-none active:cursor-grabbing"></div>
        <div data-table-thumb-x aria-hidden="true" hidden class="bg-line-strong hover:bg-muted active:bg-muted absolute z-[2] h-1.5 cursor-grab touch-none rounded-full transition-colors select-none active:cursor-grabbing"></div>
    @endif

    @if ($infinite && ! $isEmpty && ! $loading && $rows->hasPages())
        {{-- Windowed scroll footer. resources/js/widget/table fills in the status and hides the fallback links;
             without JS those links are a normal Previous/Next. --}}
        <div data-table-window-footer class="border-line flex min-h-12 items-center justify-center border-t px-5 py-2 text-sm">
            <p data-table-window-status role="status" class="text-muted hidden"></p>
            <div data-table-window-fallback>
                <x-widget.pagination :paginator="$rows" />
            </div>
        </div>
    @elseif ($isPaginator && $pagination && ! $infinite && ! $isEmpty && ! $loading && $rows->hasPages())
        {{-- data-table-pagination: resources/js/widget/table swaps just this table in place when these links are used. --}}
        <div data-table-pagination class="border-line border-t transition-opacity group-aria-busy/root:opacity-50">
            <x-widget.pagination :paginator="$rows" :type="$pagination" :per-page-model="$perPageModel" />
        </div>
    @endif

    @if ($hasToolbar)
        </div>
    @endif
</div>
