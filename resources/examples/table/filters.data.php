<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

// Demo data for the preview only. Every filter and sort key is checked against a fixed list before it's used, as
// a controller must; the Usage shows the same with Eloquent.
return static function (): array {
    $statuses = ['paid' => 'Paid', 'pending' => 'Pending', 'refunded' => 'Refunded', 'failed' => 'Failed'];
    $payments = ['card' => 'Card', 'bank' => 'Bank transfer', 'paypal' => 'PayPal'];
    $periods = ['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'];
    $customers = ['Ana Silva', 'Ben Carter', 'Chen Wei', 'Dara Okafor', 'Eli Novak', 'Farah Aziz', 'Gus Holm', 'Hana Sato'];

    $today = now()->startOfDay();
    $all = collect(range(1, 64))->map(fn (int $n): array => [
        'number' => 'ORD-'.(1000 + $n),
        'customer' => $customers[($n * 5) % count($customers)],
        'status' => array_keys($statuses)[($n * 7) % 11 % 4],
        'payment' => array_keys($payments)[($n + intdiv($n, 4)) % 3],
        'date' => $today->copy()->subDays(($n * 13) % 120),
        'value' => round(18 + fmod($n * 71.3, 640), 2),
    ]);

    $query = request()->query();
    $filters = [
        'q' => is_string($query['order_q'] ?? null) ? mb_substr(trim($query['order_q']), 0, 100) : '',
        'status' => array_key_exists($query['order_status'] ?? '', $statuses) ? $query['order_status'] : null,
        'payment' => array_key_exists($query['order_payment'] ?? '', $payments) ? $query['order_payment'] : null,
        'period' => array_key_exists($query['order_period'] ?? '', $periods) ? (string) $query['order_period'] : null,
    ];

    // Every filter but $except: an option's count is how many orders picking it would show, given the others.
    $matching = fn (?string $except = null): Collection => $all
        ->when($filters['q'] !== '', fn ($orders) => $orders->filter(fn (array $order): bool => str_contains(mb_strtolower($order['number'].' '.$order['customer']), mb_strtolower($filters['q']))))
        ->when($except !== 'status' && $filters['status'], fn ($orders) => $orders->where('status', $filters['status']))
        ->when($except !== 'payment' && $filters['payment'], fn ($orders) => $orders->where('payment', $filters['payment']))
        ->when($except !== 'period' && $filters['period'], fn ($orders) => $orders->filter(fn (array $order): bool => $order['date']->gte($today->copy()->subDays((int) $filters['period']))));
    $counted = fn (array $labels, string $facet, callable $matches): array => collect($labels)
        ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label, 'meta' => (string) $matching($facet)->filter(fn (array $order): bool => $matches($order, $value))->count()])
        ->values()->all();

    // Multi-sort: sort=status,amount&direction=asc,desc. Unknown keys are dropped, each key counts once.
    $directions = explode(',', is_string($query['order_dir'] ?? null) ? $query['order_dir'] : '');
    $order = [];
    foreach (explode(',', is_string($query['order_sort'] ?? null) ? $query['order_sort'] : '') as $i => $key) {
        if (in_array($key, ['number', 'status', 'date', 'amount'], true) && !isset($order[$key])) {
            $order[$key] = ($directions[$i] ?? null) === 'desc' ? 'desc' : 'asc';
        }
    }
    $order = $order ?: ['date' => 'desc'];

    $matches = $matching()
        ->sortBy(array_map(fn (string $key, string $direction): array => [$key === 'amount' ? 'value' : $key, $direction], array_keys($order), $order))
        ->values()
        ->map(fn (array $order): array => [
            ...$order,
            'status_label' => $statuses[$order['status']],
            'payment_label' => $payments[$order['payment']],
            'date_label' => $order['date']->toFormattedDateString(),
            'amount' => '$'.number_format($order['value'], 2),
        ]);
    $page = Paginator::resolveCurrentPage('order_page');

    return [
        // Its own filters and sort only, where an app would use withQueryString(): see sortable.data.php.
        'orders' => (new LengthAwarePaginator($matches->forPage($page, 8)->values(), $matches->count(), 8, $page, [
            'path' => request()->url(),
            'pageName' => 'order_page',
        ]))->appends(Arr::only(request()->query(), ['order_q', 'order_status', 'order_payment', 'order_period', 'order_sort', 'order_dir'])),
        'filters' => $filters,
        'statuses' => $counted($statuses, 'status', fn (array $order, string $value): bool => $order['status'] === $value),
        'payments' => $counted($payments, 'payment', fn (array $order, string $value): bool => $order['payment'] === $value),
        'periods' => $counted($periods, 'period', fn (array $order, string $value): bool => $order['date']->gte($today->copy()->subDays((int) $value))),
        // Across every matching order, not just this page.
        'totals' => ['count' => $matches->count(), 'amount' => '$'.number_format($matches->sum('value'), 2)],
    ];
};
