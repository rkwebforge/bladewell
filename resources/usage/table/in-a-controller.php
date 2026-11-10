// Pass a paginator (or any collection) to the view. The table renders its own pagination links.
public function index(): View
{
    return view('transactions.index', [
        'transactions' => Transaction::query()->latest()->paginate(5),
    ]);
}

// For pagination="infinite", a cursor paginator keeps every page equally fast, however deep.
Transaction::query()->latest('id')->cursorPaginate(10, cursorName: 'feed_cursor');

// Sortable columns: only ever pass orderBy() a column from your own list, never the raw query string.
$sort = in_array($request->query('sort'), ['reference', 'date', 'amount'], true) ? $request->query('sort') : 'date';
$direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
Payment::query()->orderBy($sort, $direction)->paginate(10)->withQueryString();

// Filters (the filters slot): each field arrives as a query parameter. Check every one against your own list
// before it reaches the query, bind values (never interpolate them into whereRaw), and keep the query string so
// page and sort links carry the filters.
$status = in_array($request->query('status'), ['paid', 'pending', 'refunded', 'failed'], true) ? $request->query('status') : null;
// is_string(): ?q[]=x sends an array, which would otherwise end in an error page.
$search = is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 100) : '';
Order::query()
    // % and _ typed match themselves, the same on every database (ESCAPE '!'), and LOWER() ignores case.
    ->when($search !== '', fn ($query) => $query->whereRaw("LOWER(number) LIKE LOWER(?) ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']))
    ->when($status, fn ($query) => $query->where('status', $status))
    ->latest()
    ->paginate(10)
    ->withQueryString();

// multi-sort: sort and direction arrive as comma lists in priority order (sort=status,amount&direction=asc,desc).
// Keep only keys from your list, each once.
$directions = explode(',', (string) $request->query('direction'));
$query = Order::query();
foreach (array_unique(explode(',', (string) $request->query('sort'))) as $i => $key) {
    if (in_array($key, ['status', 'date', 'amount'], true)) {
        $query->orderBy($key, ($directions[$i] ?? '') === 'desc' ? 'desc' : 'asc');
    }
}

// Option counts (faceted filters): each option's meta is how many rows picking it would give, counting every
// other filter but its own. The table updates them in the toolbar after each change.
$statusCounts = Order::query()->when($search !== '', fn ($query) => $query->whereRaw("LOWER(number) LIKE LOWER(?) ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%']))
    ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
$statusOptions = collect(['paid' => 'Paid', 'pending' => 'Pending'])
    ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label, 'meta' => (string) ($statusCounts[$value] ?? 0)])
    ->values();

// Bulk actions: the ticked rows arrive as selected[]. Authorize and scope them like any other input.
$invoices = $request->user()->invoices()->whereKey($request->validated('selected'))->get();
