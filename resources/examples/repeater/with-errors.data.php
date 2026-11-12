<?php

declare(strict_types=1);

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

// Demo data for the preview only: the lines and errors a failed validation would leave. In your app, Laravel shares
// the errors, and old('lines') brings back what was sent.
return static function (): array {
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
        'lines' => ['An order can have at most 3 lines.'],
        'lines.15.quantity' => ['Only 4 desk lamps are in stock.'],
        'lines.new-k3f9qx.product' => ['Enter a product.'],
    ])));

    return ['lines' => [
        14 => ['product' => 'Oak desk', 'quantity' => 1],
        15 => ['product' => 'Desk lamp', 'quantity' => 6],
        'new-k3f9qx' => ['product' => '', 'quantity' => 1],
        'new-p2m8wd' => ['product' => 'Cable tray', 'quantity' => 1],
    ]];
};
