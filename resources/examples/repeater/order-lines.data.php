<?php

declare(strict_types=1);

// Demo data for the preview only: an order's lines keyed by their ids, as $order->lines->keyBy('id')->toArray() gives.
return ['lines' => [
    14 => ['product' => 'Oak desk', 'quantity' => 1],
    15 => ['product' => 'Desk lamp', 'quantity' => 2],
]];
