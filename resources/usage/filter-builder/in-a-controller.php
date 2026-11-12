// Define the fields once and use the same Filters for the page and the query: only these can be filtered, by the
// conditions each type offers, with every value checked and bound. A field's key is what the URL carries; column()
// names what it filters, and using() filters a relation or a computed value yourself.
use App\View\Widget\FilterField;
use App\View\Widget\FilterOperator;
use App\View\Widget\Filters;

$filters = Filters::make([
    FilterField::text('number', 'Order number'),
    FilterField::choice('status', 'Status', ['paid' => 'Paid', 'pending' => 'Pending', 'refunded' => 'Refunded']),
    FilterField::dateTime('placed', 'Placed')->column('created_at'),
    FilterField::number('amount', 'Amount')->column('total'),
    // Every condition a text field offers, on the customer's name. The negative ones (doesn't contain, is not, is empty)
    // keep orders with no such customer, as a field of the order's own keeps rows with nothing in it.
    FilterField::text('customer', 'Customer')->using(function ($query, FilterOperator $operator, ?string $value) {
        $text = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $value);
        $like = "LOWER(name) LIKE LOWER(?) ESCAPE '!'";
        [$sql, $binding] = match ($operator) {
            FilterOperator::Contains, FilterOperator::NotContains => [$like, "%{$text}%"],
            FilterOperator::StartsWith => [$like, "{$text}%"],
            FilterOperator::EndsWith => [$like, "%{$text}"],
            FilterOperator::Is, FilterOperator::IsNot => ['LOWER(name) = LOWER(?)', (string) $value],
            // Is empty and isn't empty: whether there's a customer with a name.
            default => ["COALESCE(name, '') <> ?", ''],
        };
        $has = ! in_array($operator, [FilterOperator::NotContains, FilterOperator::IsNot, FilterOperator::Empty], true);

        return $query->{$has ? 'whereHas' : 'whereDoesntHave'}('customer', fn ($customer) => $customer->whereRaw($sql, [$binding]));
    }),
]);

// In the person's time zone, so "placed on 11 November" is their 11 November.
$state = $filters->read($request->query('filters'), timezone: $request->user()->timezone);

// On a query already limited to what they may see: the filters only ever narrow it.
$orders = $filters->apply($request->user()->orders()->getQuery(), $state)
    ->latest()
    ->paginate(20)
    ->withQueryString();

// The view needs only the state: <x-widget.filter-builder :state="$state" />. It brings the fields with it.
return view('orders.index', ['orders' => $orders, 'state' => $state]);
