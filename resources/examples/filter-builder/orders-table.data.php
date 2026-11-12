<?php

declare(strict_types=1);

use Bladewell\Support\FilterField;
use Bladewell\Support\Filters;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

// Demo data for the preview only: orders in memory, filtered with Filters::filter(), which keeps the rows apply()
// would on a query. Your controller defines the same Filters and applies them to Eloquent: see Usage.
return static function (): array {
    $statuses = ['paid' => 'Paid', 'pending' => 'Pending', 'refunded' => 'Refunded', 'failed' => 'Failed'];
    $customers = ['Ana Silva', 'Ben Carter', 'Chen Wei', 'Dara Okafor', 'Eli Novak', 'Farah Aziz', 'Gus Holm', 'Hana Sato'];
    $now = now();
    $orders = collect(range(1, 48))->map(fn (int $n): array => [
        'number' => 'ORD-'.(1000 + $n),
        'customer' => $customers[($n * 5) % count($customers)],
        'status' => array_keys($statuses)[($n * 7) % 11 % 4],
        'placed_at' => $now->copy()->subHours($n * 37)->toDateTimeString(),
        'amount' => round(18 + fmod($n * 71.3, 640), 2),
        'gift' => $n % 5 === 0,
    ]);

    $filters = Filters::make([
        FilterField::text('number', 'Order number'),
        FilterField::text('customer', 'Customer'),
        FilterField::choice('status', 'Status', $statuses),
        FilterField::dateTime('placed', 'Placed')->column('placed_at'),
        FilterField::number('amount', 'Amount'),
        FilterField::boolean('gift', 'Gift wrapped'),
    ]);
    $state = $filters->read(request()->query('order_filters'));
    $matches = $filters->filter($orders, $state)->sortByDesc('placed_at')->values()->map(fn (array $order): array => [
        ...$order,
        'status_label' => $statuses[$order['status']],
        'placed_label' => Carbon::parse($order['placed_at'])->toFormattedDateString(),
        'amount_label' => '$'.number_format($order['amount'], 2),
    ]);
    $page = Paginator::resolveCurrentPage('page');

    return [
        'orders' => (new LengthAwarePaginator($matches->forPage($page, 8)->values(), $matches->count(), 8, $page, [
            'path' => request()->url(),
        ]))->appends(Arr::only(request()->query(), ['order_filters'])),
        'state' => $state,
    ];
};
