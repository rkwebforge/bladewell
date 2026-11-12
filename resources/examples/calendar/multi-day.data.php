<?php

declare(strict_types=1);

// Demo data for the preview only. Ends are exclusive: an all-day event's end is the day after its last.
return static function (): array {
    $month = now('Europe/London')->startOfMonth()->toImmutable();
    $date = fn (int $day) => $month->addDays($day - 1)->toDateString();

    return ['events' => [
        // Began last month: still shown from the first day of the grid, with an arrow where it carries on.
        ['id' => 'leave-amara', 'title' => 'Amara on leave', 'start' => $month->subDays(3)->toDateString(), 'end' => $date(4), 'tone' => 'neutral'],
        ['id' => 'conference', 'title' => 'Laracon EU', 'start' => $date(6), 'end' => $date(9), 'tone' => 'success'],
        // Wraps from one week into the next.
        ['id' => 'stock-take', 'title' => 'Stock-take', 'start' => $date(12), 'end' => $date(20), 'tone' => 'warning'],
        // 22:00 to 06:00: on two days, without its length changing.
        ['id' => 'night-shift', 'title' => 'Night shift', 'start' => $month->addDays(13)->setTime(22, 0), 'end' => $month->addDays(14)->setTime(6, 0)],
        // Ends at midnight, so it stays on its own day.
        ['id' => 'late-show', 'title' => 'Late show', 'start' => $month->addDays(16)->setTime(21, 0), 'end' => $month->addDays(17)->startOfDay(), 'tone' => 'error'],
        ['id' => 'holiday', 'title' => 'Bank holiday', 'start' => $date(24), 'tone' => 'neutral'],
    ]];
};
