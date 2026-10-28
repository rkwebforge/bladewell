<?php

declare(strict_types=1);

// Demo data for the preview only. A real app passes its stages and Order::query()->get()->groupBy('stage').
return static fn (): array => [
    'stages' => ['new' => 'New', 'packed' => 'Packed', 'shipped' => 'Shipped', 'delivered' => 'Delivered'],
    'orders' => collect([
        ['id' => 1042, 'customer' => 'Amara Okafor', 'total' => '$84.00', 'stage' => 'new'],
        ['id' => 1043, 'customer' => 'Lukas Weber', 'total' => '$129.50', 'stage' => 'new'],
        ['id' => 1044, 'customer' => 'Priya Nair', 'total' => '$46.20', 'stage' => 'new'],
        ['id' => 1039, 'customer' => 'Sofia Rossi', 'total' => '$212.00', 'stage' => 'packed'],
        ['id' => 1036, 'customer' => 'Kenji Watanabe', 'total' => '$58.75', 'stage' => 'shipped'],
        ['id' => 1031, 'customer' => 'Chloé Martin', 'total' => '$99.00', 'stage' => 'delivered'],
    ])->map(fn (array $order): object => (object) $order)->groupBy('stage'),
];
