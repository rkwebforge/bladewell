<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

// Demo data for the preview only: 470 rows of nothing, following ?footer_page= and ?per_page= so the controls work.
// per_page is checked against the sizes on offer, as your controller should.
return static function (): array {
    $perPage = in_array(request()->integer('per_page'), [10, 25, 50, 100], true) ? request()->integer('per_page') : 10;
    $page = Paginator::resolveCurrentPage('footer_page');
    // As many rows as that page really has: the paginator counts the last one shown from them (401–470, not 401–500).
    $rows = max(0, min($perPage, 470 - ($page - 1) * $perPage));

    return ['orders' => (new LengthAwarePaginator($rows > 0 ? range(1, $rows) : [], 470, $perPage, $page, [
        'path' => request()->url(),
        'pageName' => 'footer_page',
    ]))->appends(request()->only('per_page'))];
};
