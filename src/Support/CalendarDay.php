<?php

declare(strict_types=1);

namespace Bladewell\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * One day of <x-widget.calendar>'s month: its date, and the events on it, laid out in lanes.
 */
final readonly class CalendarDay
{
    /**
     * @param  string  $iso  Y-m-d.
     * @param  string  $number  The day of the month, in the locale's digits.
     * @param  string  $label  The full date, e.g. "Thursday, November 12, 2026".
     * @param  string  $weekday  Its weekday, short: "Thu".
     * @param  string  $weekEnd  Y-m-d of the last day of its week (its row).
     * @param  list<CalendarEvent|null>  $lanes  What it draws, top to bottom: an event starting in this cell (on this
     *                                           day, or continuing into this week), or null where a bar from a cell
     *                                           before it passes through.
     * @param  int  $hidden  How many of its events didn't fit in the lanes it shows.
     * @param  list<CalendarEvent>  $events  All its events, in order: for its "more" list and the agenda.
     */
    public function __construct(
        public string $iso,
        public string $number,
        public string $label,
        public string $weekday,
        public bool $inMonth,
        public bool $today,
        public string $weekEnd,
        public array $lanes,
        public int $hidden,
        public array $events,
    ) {}

    /** How many days, from this one to the end of its week, the event's bar covers. */
    public function span(CalendarEvent $event): int
    {
        $last = min($event->lastDay, $this->weekEnd);
        $utc = new DateTimeZone('UTC');

        return (int) (new DateTimeImmutable($this->iso, $utc))->diff(new DateTimeImmutable($last, $utc))->days + 1;
    }

    /** Whether the event began before this day (its bar carries on from the week before). */
    public function continuesBefore(CalendarEvent $event): bool
    {
        return $event->firstDay < $this->iso;
    }

    /** Whether the event goes on past the end of this week. */
    public function continuesAfter(CalendarEvent $event): bool
    {
        return $event->lastDay > $this->weekEnd;
    }
}
