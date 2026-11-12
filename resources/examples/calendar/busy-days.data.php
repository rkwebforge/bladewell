<?php

declare(strict_types=1);

// Demo data for the preview only: one busy day, and an ordinary one.
return static function (): array {
    $day = now('Europe/London')->startOfMonth()->addDays(9)->toImmutable();
    $slot = fn (int $hour, int $minutes = 30) => ['start' => $day->setTime($hour, 0), 'end' => $day->setTime($hour, $minutes)];

    return ['events' => [
        ['id' => 101, 'title' => 'Haircut: Lukas', ...$slot(9)],
        ['id' => 102, 'title' => 'Colour: Priya', ...$slot(10, 90), 'tone' => 'success'],
        ['id' => 103, 'title' => 'Haircut: Sofia', ...$slot(12)],
        ['id' => 104, 'title' => 'Beard trim: Kenji', ...$slot(13, 20), 'status' => 'Tentative', 'tone' => 'neutral'],
        ['id' => 105, 'title' => 'Haircut: Chloé', ...$slot(15)],
        ['id' => 106, 'title' => 'Blow-dry: Amara', ...$slot(16, 45), 'status' => 'Cancelled', 'tone' => 'error'],
        ['id' => 107, 'title' => 'Haircut: Lukas', 'start' => $day->addDays(2)->setTime(11, 0), 'end' => $day->addDays(2)->setTime(11, 30)],
    ]];
};
