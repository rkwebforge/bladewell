<?php

declare(strict_types=1);

use Bladewell\Support\FilterField;
use Bladewell\Support\Filters;

// Demo data for the preview only: customers in memory, and a condition already sent that the server turns down
// (a number that isn't one), to show its reason. Your controller reads the request: see Usage.
return static function (): array {
    $customers = collect([
        ['name' => 'Ana Silva', 'country' => 'pt', 'orders' => 12, 'joined' => '2024-03-02', 'newsletter' => true],
        ['name' => 'Ben Carter', 'country' => 'gb', 'orders' => 3, 'joined' => '2025-07-19', 'newsletter' => false],
        ['name' => 'Chen Wei', 'country' => 'sg', 'orders' => 27, 'joined' => '2023-11-30', 'newsletter' => true],
        ['name' => 'Dara Okafor', 'country' => 'ng', 'orders' => 0, 'joined' => '2026-01-08', 'newsletter' => true],
        ['name' => 'Eli Novak', 'country' => 'cz', 'orders' => 8, 'joined' => '2025-02-14', 'newsletter' => false],
    ]);
    $filters = Filters::make([
        FilterField::text('name', 'Name'),
        FilterField::choice('country', 'Country', ['cz' => 'Czechia', 'gb' => 'United Kingdom', 'ng' => 'Nigeria', 'pt' => 'Portugal', 'sg' => 'Singapore']),
        // A count: whole numbers only.
        FilterField::number('orders', 'Orders')->whole(),
        FilterField::date('joined', 'Joined'),
        FilterField::boolean('newsletter', 'Gets the newsletter'),
    ]);
    $sent = request()->query('report_filters', '{"match":"all","rules":[{"field":"orders","op":"gte","value":"5"},{"field":"orders","op":"lt","value":"lots"}]}');
    $state = $filters->read($sent);

    return [
        'state' => $state,
        'matching' => $filters->filter($customers, $state)->count(),
        'total' => $customers->count(),
    ];
};
