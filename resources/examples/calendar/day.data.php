<?php

declare(strict_types=1);

// Demo data for the preview only: today's appointments.
return static function (): array {
    $today = now('America/New_York')->startOfDay()->toImmutable();
    $at = fn (string $time) => $today->setTimeFromTimeString($time);

    return ['events' => [
        ['id' => 1, 'title' => 'Room 2: Physiotherapy', 'start' => $at('08:00'), 'end' => $at('09:00')],
        ['id' => 2, 'title' => 'Room 1: Check-up', 'start' => $at('08:30'), 'end' => $at('09:00'), 'tone' => 'success'],
        ['id' => 3, 'title' => 'Room 1: Vaccination', 'start' => $at('09:00'), 'end' => $at('09:15'), 'status' => 'Walk-in', 'tone' => 'neutral'],
        ['id' => 4, 'title' => 'Lunch', 'start' => $at('12:30'), 'end' => $at('13:15'), 'tone' => 'neutral'],
        ['id' => 5, 'title' => 'Room 2: Follow-up', 'start' => $at('14:00'), 'end' => $at('14:45'), 'status' => 'Cancelled', 'tone' => 'error'],
        ['id' => 6, 'title' => 'Clinic closed for training', 'start' => $today->toDateString(), 'tone' => 'warning'],
    ]];
};
