<?php

declare(strict_types=1);

// Demo data for the preview only. In your app, can_delete would come from a policy: $user->can('delete', $order).
return static fn (): array => ['orders' => [
    ['id' => 1042, 'number' => '1042', 'customer' => 'Ana Silva', 'total' => '$129.00', 'can_delete' => true],
    ['id' => 1043, 'number' => '1043', 'customer' => 'Ben Okafor', 'total' => '$48.50', 'can_delete' => false],
    ['id' => 1044, 'number' => '1044', 'customer' => 'Chen Wei', 'total' => '$310.00', 'can_delete' => true],
]];
