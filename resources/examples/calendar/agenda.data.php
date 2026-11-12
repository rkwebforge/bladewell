<?php

declare(strict_types=1);

// Demo data for the preview only.
return static function (): array {
    $month = now('America/New_York')->startOfMonth()->toImmutable();
    $at = fn (int $day, int $hour, int $minute = 0) => $month->addDays($day - 1)->setTime($hour, $minute);

    return ['events' => [
        ['id' => 'a1', 'title' => 'Dentist', 'start' => $at(3, 8, 30), 'end' => $at(3, 9, 15)],
        ['id' => 'a2', 'title' => 'Flight to Lisbon', 'start' => $at(7, 18, 40), 'end' => $at(8, 7, 5), 'status' => 'Booked', 'tone' => 'success'],
        ['id' => 'a3', 'title' => 'Lisbon trip', 'start' => $month->addDays(7)->toDateString(), 'end' => $month->addDays(11)->toDateString(), 'tone' => 'warning'],
        ['id' => 'a4', 'title' => 'Rent due', 'start' => $at(15, 9), 'tone' => 'error'],
        ['id' => 'a5', 'title' => 'Book club', 'start' => $at(20, 19), 'end' => $at(20, 21), 'status' => 'Tentative', 'tone' => 'neutral'],
    ]];
};
