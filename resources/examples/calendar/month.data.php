<?php

declare(strict_types=1);

// Demo data for the preview only, around this month. A real app loads the events overlapping the six weeks shown:
// [$from, $to] = CalendarMonth::range($month, $timezone); Booking::where('starts_at', '<', $to)->where('ends_at', '>=', $from)->get().
return static function (): array {
    $month = now('Europe/London')->startOfMonth()->toImmutable();
    $at = fn (int $day, string $time) => $month->addDays($day - 1)->setTimeFromTimeString($time);

    return ['events' => [
        ['id' => 1, 'title' => 'Sprint planning', 'start' => $at(2, '09:30'), 'end' => $at(2, '11:00')],
        ['id' => 2, 'title' => 'Design review', 'start' => $at(4, '14:00'), 'end' => $at(4, '15:00'), 'tone' => 'success'],
        ['id' => 3, 'title' => 'Order #1042 delivery', 'start' => $at(9, '08:00'), 'end' => $at(9, '10:00'), 'href' => route('orders.edit', 1042), 'status' => 'Confirmed', 'tone' => 'success'],
        ['id' => 4, 'title' => 'Company offsite', 'start' => $month->addDays(10)->toDateString(), 'end' => $month->addDays(13)->toDateString(), 'tone' => 'warning'],
        ['id' => 5, 'title' => 'Customer call: Weber GmbH', 'start' => $at(15, '16:00'), 'end' => $at(15, '16:30'), 'status' => 'Tentative', 'tone' => 'neutral'],
        ['id' => 6, 'title' => 'Release', 'start' => $at(18, '12:00'), 'tone' => 'error'],
        ['id' => 7, 'title' => 'Server maintenance', 'start' => $at(21, '23:00'), 'end' => $at(22, '02:00'), 'status' => 'Planned'],
        ['id' => 8, 'title' => 'Retro', 'start' => $at(25, '15:00'), 'end' => $at(25, '16:00')],
    ]];
};
