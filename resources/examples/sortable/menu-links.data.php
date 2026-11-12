<?php

declare(strict_types=1);

// Demo data for the preview only. A real app passes the menu and $menu->links()->orderBy('sort')->get().
return static fn (): array => [
    'menu' => 'main',
    'links' => collect([
        ['id' => 11, 'title' => 'Home', 'url' => '/'],
        ['id' => 12, 'title' => 'Shop', 'url' => '/shop'],
        ['id' => 13, 'title' => 'Journal', 'url' => '/journal'],
        ['id' => 14, 'title' => 'About us', 'url' => '/about'],
    ])->map(fn (array $link): object => (object) $link),
];
