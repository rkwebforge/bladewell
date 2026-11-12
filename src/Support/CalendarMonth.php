<?php

declare(strict_types=1);

namespace Bladewell\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A month of <x-widget.calendar>: always six weeks, starting on the locale's first day of the week, with each event
 * placed in a lane that stays the same across its week, so a bar covering several days reads as one. Its links carry
 * months (Y-m).
 */
final class CalendarMonth extends Calendar
{
    /**
     * @param  list<array{short: string, long: string}>  $weekdays  In the order the columns show them.
     * @param  list<non-empty-list<CalendarDay>>  $weeks  Six of seven days.
     */
    private function __construct(
        string $date,
        string $title,
        string $timezone,
        string $locale,
        string $today,
        ?string $previous,
        ?string $next,
        string $current,
        ?string $min,
        ?string $max,
        public readonly array $weekdays,
        public readonly array $weeks,
    ) {
        parent::__construct('month', $date, $title, $timezone, $locale, $today, $previous, $next, $current, $min, $max);
    }

    /**
     * @param  iterable<mixed>  $events  Arrays, objects or models; see CalendarEvent.
     * @param  int  $perDay  The most lanes a day shows before the rest go behind its "more" button.
     * @param  array<string, string|\Closure>  $keys  Where to read each field; see CalendarEvent::from().
     */
    public static function make(
        iterable $events,
        string $timezone,
        mixed $date = null,
        ?string $locale = null,
        ?int $weekStart = null,
        int $perDay = 3,
        mixed $min = null,
        mixed $max = null,
        array $keys = [],
    ): self {
        $zone = self::zone($timezone);
        $locale = LocalDate::locale($locale);
        $weekStart ??= LocalDate::firstDayOfWeek($locale);
        $perDay = max(1, $perDay);
        $today = now($zone)->format('Y-m-d');
        $monthOf = static fn (mixed $value): ?string => ($day = self::dateOf($value)) === null ? null : substr($day, 0, 7);
        // Without limits of its own, the links stop at the first and last years it can show.
        [$min, $max] = [$monthOf($min) ?? self::FIRST_YEAR.'-01', $monthOf($max) ?? self::LAST_YEAR.'-12'];
        $month = self::clamp($monthOf($date) ?? substr($today, 0, 7), $min, $max);

        $first = self::weekOf("{$month}-01", $weekStart);
        $events = self::eventsBetween($events, $zone, $first, CalendarEvent::addDays($first, 41), $keys);

        $weeks = [];
        for ($week = 0; $week < 6; $week++) {
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $days[] = CalendarEvent::addDays($first, $week * 7 + $i);
            }
            $weeks[] = self::layRow($days, $events, $month, $today, $perDay, $locale);
        }

        $previous = self::shift($month, -1);
        $next = self::shift($month, 1);

        return new self(
            date: $month,
            title: self::format("{$month}-01", 'yMMMM', 'F Y', $locale),
            timezone: $zone->getName(),
            locale: $locale,
            today: $today,
            previous: $previous < $min ? null : $previous,
            next: $next > $max ? null : $next,
            current: self::clamp(substr($today, 0, 7), $min, $max),
            min: $min,
            max: $max,
            weekdays: array_map(static fn (CalendarDay $day): array => [
                'short' => self::format($day->iso, 'EEE', 'D', $locale),
                'long' => self::format($day->iso, 'EEEE', 'l', $locale),
            ], $weeks[0]),
            weeks: $weeks,
        );
    }

    public function days(): array
    {
        return array_map(static fn (CalendarDay $day): string => $day->iso, array_merge(...$this->weeks));
    }

    public function agenda(): array
    {
        return array_values(array_filter(array_merge(...$this->weeks), static fn (CalendarDay $day): bool => $day->inMonth && $day->events !== []));
    }

    public function periodTitle(string $date): string
    {
        return self::format(substr($date, 0, 7).'-01', 'yMMMM', 'F Y', $this->locale);
    }

    /** From the 1st, so 31 January plus a month is February, never March. */
    private static function shift(string $month, int $by): string
    {
        return (new DateTimeImmutable("{$month}-01", new DateTimeZone('UTC')))->modify("{$by} month")->format('Y-m');
    }
}
