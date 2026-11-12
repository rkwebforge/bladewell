<?php

declare(strict_types=1);

// Demo data for the preview only, around this week. A real app loads the events overlapping the week shown:
// [$from, $to] = Calendar::range($date, $timezone, 'week'); Booking::where('starts_at', '<', $to)->where('ends_at', '>=', $from)->get().
return static function (): array {
    $week = now('Europe/London')->startOfWeek()->toImmutable();
    $at = fn (int $day, string $time) => $week->addDays($day)->setTimeFromTimeString($time);

    return ['events' => [
        ['id' => 1, 'title' => 'Stakeholder meeting', 'start' => $at(0, '10:00'), 'end' => $at(0, '11:30')],
        // Starts an hour into the meeting above: drawn over the rest of it, set in, with the meeting's title still clear.
        ['id' => 11, 'title' => 'Online conference', 'start' => $at(0, '11:00'), 'end' => $at(0, '11:15'), 'tone' => 'success'],
        ['id' => 2, 'title' => 'Quick call with Martin', 'start' => $at(1, '11:00'), 'end' => $at(1, '12:15'), 'tone' => 'error'],
        // Three at once on Wednesday: side by side, each still readable and clickable.
        ['id' => 3, 'title' => 'Product team', 'start' => $at(2, '09:00'), 'end' => $at(2, '10:30'), 'tone' => 'warning'],
        ['id' => 4, 'title' => 'Interview: Priya', 'start' => $at(2, '09:30'), 'end' => $at(2, '10:15'), 'href' => route('orders.edit', 1044), 'status' => 'Confirmed', 'tone' => 'success'],
        ['id' => 5, 'title' => 'Coffee', 'start' => $at(2, '10:00'), 'end' => $at(2, '10:20'), 'tone' => 'neutral'],
        // Five minutes: drawn half an hour tall so it can be read, its text saying how long it is.
        ['id' => 6, 'title' => 'Stand-up', 'start' => $at(3, '09:00'), 'end' => $at(3, '09:05')],
        ['id' => 7, 'title' => 'Deploy window', 'start' => $at(3, '22:00'), 'end' => $at(4, '01:30'), 'status' => 'Planned', 'tone' => 'error'],
        ['id' => 8, 'title' => 'Early flight', 'start' => $at(4, '05:45'), 'end' => $at(4, '07:10'), 'tone' => 'neutral'],
        ['id' => 9, 'title' => 'Team offsite', 'start' => $week->addDays(3)->toDateString(), 'end' => $week->addDays(5)->toDateString(), 'tone' => 'warning'],
        ['id' => 10, 'title' => 'Friends visiting', 'start' => $week->addDays(5)->toDateString(), 'end' => $week->addDays(9)->toDateString(), 'tone' => 'success'],
    ]];
};
