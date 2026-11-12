<?php

declare(strict_types=1);

// Demo data for the preview only: drawings on this site. In your app, the product's images in their order, with URLs.
return static fn (): array => [
    'photos' => collect([
        ['id' => 31, 'alt' => 'Mug, front', 'file' => 'front'],
        ['id' => 32, 'alt' => 'Mug, side', 'file' => 'side'],
        ['id' => 33, 'alt' => 'Mug, from above', 'file' => 'top'],
        ['id' => 34, 'alt' => 'Mug, back', 'file' => 'back'],
        ['id' => 35, 'alt' => 'Mug on a desk', 'file' => 'in-use'],
    ])->map(fn (array $photo): object => (object) [...$photo, 'url' => "/images/sortable/{$photo['file']}.svg"]),
];
