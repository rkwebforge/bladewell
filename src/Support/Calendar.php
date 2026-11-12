<?php

declare(strict_types=1);

namespace Bladewell\Support;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use IntlTimeZone;
use InvalidArgumentException;
use NumberFormatter;

/**
 * What <x-widget.calendar> shows: a month (CalendarMonth), or a week or a day of hours (CalendarWeek). Both lay out
 * CalendarEvents in the calendar's timezone and write dates and times the locale's way.
 *
 * The date comes from a URL, so anything that isn't one (a typo, ?date=abc) shows today's period rather than failing,
 * and a date outside min and max shows the nearest one inside.
 *
 * range() gives the moments a view covers, for loading only the events it shows:
 *
 *     [$from, $to] = Calendar::range($request->query('date'), $timezone, 'week');
 *     Booking::where('starts_at', '<', $to)->where('ends_at', '>=', $from)->get();
 *
 * That loads every event overlapping it, including one that began before it.
 */
abstract class Calendar
{
    public const array VIEWS = ['month', 'week', 'day'];

    /** The years the links reach: dates are compared as Y-m-d, which needs four digits. */
    public const int FIRST_YEAR = 1000;

    public const int LAST_YEAR = 9998;

    /** @var array<string, IntlDateFormatter|null> */
    private static array $formatters = [];

    /**
     * @param  string  $view  month, week or day.
     * @param  string  $date  What the links carry for this period: Y-m for a month, Y-m-d for a week (any day of it)
     *                        or a day.
     * @param  string  $today  Y-m-d, in the calendar's timezone, as the server saw it.
     * @param  string|null  $previous  The period before, as $date is written, or null when min stops it.
     * @param  string|null  $next  The period after, or null when max stops it.
     * @param  string  $current  Today's period, kept within min and max.
     * @param  string|null  $min  The earliest the links go to, written as $date is.
     * @param  string|null  $max  The latest.
     */
    protected function __construct(
        public readonly string $view,
        public readonly string $date,
        public readonly string $title,
        public readonly string $timezone,
        public readonly string $locale,
        public readonly string $today,
        public readonly ?string $previous,
        public readonly ?string $next,
        public readonly string $current,
        public readonly ?string $min,
        public readonly ?string $max,
    ) {}

    /**
     * @param  iterable<mixed>  $events  Arrays, objects or models; see CalendarEvent.
     * @param  int  $perDay  The most all-day lanes a day shows before the rest go behind its "more" button.
     * @param  array<string, string|Closure>  $keys  Where to read each field; see CalendarEvent::from().
     */
    public static function for(
        string $view,
        iterable $events,
        string $timezone,
        mixed $date = null,
        ?string $locale = null,
        ?int $weekStart = null,
        int $perDay = 3,
        mixed $min = null,
        mixed $max = null,
        array $keys = [],
    ): CalendarMonth|CalendarWeek {
        return match ($view) {
            'month' => CalendarMonth::make($events, $timezone, $date, $locale, $weekStart, $perDay, $min, $max, $keys),
            'week', 'day' => CalendarWeek::make($view, $events, $timezone, $date, $locale, $weekStart, $perDay, $min, $max, $keys),
            default => throw new InvalidArgumentException("Unknown calendar view [{$view}]. Use one of: ".implode(', ', self::VIEWS).'.'),
        };
    }

    /**
     * The moments a view shows, in the calendar's timezone: from the start of its first day up to (not including) the
     * start of the day after its last. Load the events that overlap it.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    public static function range(mixed $date, string $timezone, string $view = 'month', ?string $locale = null, ?int $weekStart = null, mixed $min = null, mixed $max = null): array
    {
        $calendar = self::for($view, [], $timezone, $date, $locale, $weekStart, min: $min, max: $max);
        $days = $calendar->days();
        $zone = new DateTimeZone($calendar->timezone);

        return [new DateTimeImmutable($days[0], $zone), new DateTimeImmutable(CalendarEvent::addDays($days[array_key_last($days)], 1), $zone)];
    }

    /** @return non-empty-list<string> Y-m-d of every day it shows, in order. */
    abstract public function days(): array;

    /** @return list<CalendarDay> The days with events, with all their events, for the agenda. */
    abstract public function agenda(): array;

    /** A period the links go to, named for people: "October 2026", "Nov 2 – Nov 8, 2026", "Monday, 2 November 2026". */
    abstract public function periodTitle(string $date): string;

    /** "Y-m-d" from a Y-m (its 1st) or Y-m-d string or a date object (its own date, unconverted); null for anything else. */
    public static function dateOf(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})(?:-(\d{2}))?$/', $value, $parts) !== 1) {
            return null;
        }
        [$year, $month, $day] = [(int) $parts[1], (int) $parts[2], (int) ($parts[3] ?? 1)];
        // Four-digit years, with room for the days a grid shows around them.
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR || ! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /** The timezone's name for people: "Los Angeles Time" rather than America/Los_Angeles. */
    public function timezoneName(): string
    {
        // ICU writes UTC as "GMT+00:00".
        if (! class_exists(IntlTimeZone::class) || in_array($this->timezone, ['UTC', 'Etc/UTC', 'GMT', 'Etc/GMT'], true)) {
            return $this->timezone;
        }
        $name = IntlTimeZone::createTimeZone($this->timezone)->getDisplayName(false, IntlTimeZone::DISPLAY_GENERIC_LOCATION, $this->locale);

        return is_string($name) && $name !== '' ? $name : $this->timezone;
    }

    /** A number in the locale's digits. */
    public function number(int $number): string
    {
        return class_exists(NumberFormatter::class)
            ? ((new NumberFormatter($this->locale, NumberFormatter::DECIMAL))->format($number) ?: (string) $number)
            : (string) $number;
    }

    /** A time of day the locale's way: "9:00 AM", "09:00". */
    public function time(DateTimeImmutable $moment): string
    {
        return self::format($moment, 'jm', 'H:i', $this->locale);
    }

    /**
     * When an event happens, in full, for screen readers and the lists: "All day", "9:00 AM to 10:30 AM",
     * "Thu, Nov 12, 10:00 PM to Fri, Nov 13, 2:00 AM".
     */
    public function when(CalendarEvent $event): string
    {
        $date = fn (string $iso): string => self::format($iso, 'MMMEd', 'D j M', $this->locale);
        $at = fn (DateTimeImmutable $moment): string => $date($moment->format('Y-m-d')).', '.$this->time($moment);

        return match (true) {
            $event->allDay && $event->spansDays() => "All day, {$date($event->firstDay)} to {$date($event->lastDay)}",
            $event->allDay => 'All day',
            $event->isMoment() => "At {$this->time($event->start)}",
            $event->start->format('Y-m-d') !== $event->end->format('Y-m-d') => "{$at($event->start)} to {$at($event->end)}",
            default => "{$this->time($event->start)} to {$this->time($event->end)}",
        };
    }

    /** Its time on one of its days, for the lists: "9:00 AM – 10:30 AM", "From 10:00 PM", "Until 2:00 AM", "All day". */
    public function timeOn(CalendarEvent $event, CalendarDay $day): string
    {
        if ($event->allDay) {
            return 'All day';
        }
        if ($event->isMoment()) {
            return $this->time($event->start);
        }
        $from = $event->start->format('Y-m-d') === $day->iso ? $this->time($event->start) : null;
        $until = $event->end->format('Y-m-d') === $day->iso ? $this->time($event->end) : null;

        return match (true) {
            $from !== null && $until !== null => "{$from} – {$until}",
            $from !== null => "From {$from}",
            $until !== null => "Until {$until}",
            default => 'All day',
        };
    }

    /**
     * @param  iterable<mixed>  $events
     * @param  array<array-key, mixed>  $keys
     * @return list<CalendarEvent> Those touching first…last, earliest first; on the same day the longer bars go on
     *                             top, so they stay lined up across the days they cover, then all-day before timed,
     *                             then by time.
     */
    protected static function eventsBetween(iterable $events, DateTimeZone $zone, string $first, string $last, array $keys = []): array
    {
        foreach ($keys as $field => $from) {
            if (! in_array($field, CalendarEvent::FIELDS, true)) {
                throw new InvalidArgumentException("Unknown field [{$field}] in the calendar's keys. Use any of: ".implode(', ', CalendarEvent::FIELDS).'.');
            }
            if (! is_string($from) && ! $from instanceof Closure) {
                throw new InvalidArgumentException("The calendar's key for [{$field}] must be the name of the event's own field, or a closure given the event.");
            }
        }

        $kept = [];
        foreach ($events as $event) {
            $event = CalendarEvent::from($event, $zone, $keys);
            if ($event->lastDay >= $first && $event->firstDay <= $last) {
                $kept[] = $event;
            }
        }
        usort($kept, static fn (CalendarEvent $a, CalendarEvent $b): int => [$a->firstDay, $b->lastDay, $b->allDay, $a->start, $a->title, $a->id]
            <=> [$b->firstDay, $a->lastDay, $a->allDay, $b->start, $b->title, $b->id]);

        return $kept;
    }

    /**
     * One row of days: each event gets the lowest lane free on every day it covers in the row, and is drawn in the
     * first of those days; the days after it hold a gap (null) where its bar passes.
     *
     * @param  non-empty-list<string>  $days
     * @param  list<CalendarEvent>  $events  Sorted, as eventsBetween() sorts them.
     * @param  string|null  $month  Y-m: days outside it are marked so. Null for a week or a day.
     * @return list<CalendarDay>
     */
    protected static function layRow(array $days, array $events, ?string $month, string $today, int $perDay, string $locale): array
    {
        [$rowFirst, $rowEnd] = [$days[0], $days[array_key_last($days)]];
        $taken = [];   // lane => [column => true]
        $starts = [];  // column => [lane => event]
        $covered = []; // column => [lane => event]

        foreach ($events as $event) {
            if ($event->lastDay < $rowFirst || $event->firstDay > $rowEnd) {
                continue;
            }
            $from = (int) array_search(max($event->firstDay, $rowFirst), $days, true);
            $to = (int) array_search(min($event->lastDay, $rowEnd), $days, true);

            $lane = 0;
            while (array_intersect_key($taken[$lane] ?? [], array_flip(range($from, $to))) !== []) {
                $lane++;
            }
            for ($column = $from; $column <= $to; $column++) {
                $taken[$lane][$column] = true;
                $covered[$column][$lane] = $event;
            }
            $starts[$from][$lane] = $event;
        }

        $row = [];
        foreach ($days as $column => $iso) {
            $here = $covered[$column] ?? [];
            ksort($here);
            $lanes = [];
            $lastShown = -1;
            // Only as far as the day's own events go, however many lanes are allowed.
            $shown = $here === [] ? 0 : min($perDay, max(array_keys($here)) + 1);
            for ($lane = 0; $lane < $shown; $lane++) {
                $lanes[] = $starts[$column][$lane] ?? null;
                if (isset($here[$lane])) {
                    $lastShown = $lane;
                }
            }

            $row[] = new CalendarDay(
                iso: $iso,
                number: self::format($iso, 'd', 'j', $locale),
                label: self::format($iso, 'yMMMMEEEEd', 'l, j F Y', $locale),
                weekday: self::format($iso, 'EEE', 'D', $locale),
                inMonth: $month === null || str_starts_with($iso, $month),
                today: $iso === $today,
                weekEnd: $rowEnd,
                lanes: array_slice($lanes, 0, $lastShown + 1),
                hidden: count(array_filter(array_keys($here), static fn (int $lane): bool => $lane >= $perDay)),
                events: array_values($here),
            );
        }

        return $row;
    }

    protected static function zone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Exception) {
            throw new InvalidArgumentException("Unknown timezone [{$timezone}] for <x-widget.calendar>. Use a name like Europe/London or America/New_York.");
        }
    }

    /** Y-m-d of the first day of the week holding $date. */
    protected static function weekOf(string $date, int $weekStart): string
    {
        $day = new DateTimeImmutable($date, new DateTimeZone('UTC'));

        return CalendarEvent::addDays($date, -(((int) $day->format('w') - $weekStart + 7) % 7));
    }

    protected static function clamp(string $value, ?string $min, ?string $max): string
    {
        return match (true) {
            $min !== null && $value < $min => $min,
            $max !== null && $value > $max => $max,
            default => $value,
        };
    }

    /**
     * A date (Y-m-d, read as that calendar day) or a moment (in its own timezone) the locale's way, from an ICU
     * skeleton; always the Gregorian calendar, as the grid is. Plain PHP formats without the intl extension.
     */
    protected static function format(DateTimeImmutable|string $value, string $skeleton, string $fallback, string $locale): string
    {
        $moment = is_string($value) ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : $value;
        $zone = $moment->getTimezone()->getName();
        $key = "{$locale}|{$skeleton}|{$zone}";

        if (! array_key_exists($key, self::$formatters)) {
            self::$formatters[$key] = null;
            if (class_exists(IntlDatePatternGenerator::class)) {
                $pattern = (new IntlDatePatternGenerator($locale))->getBestPattern($skeleton);
                self::$formatters[$key] = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $zone, IntlDateFormatter::GREGORIAN, $pattern ?: null);
            }
        }

        $formatted = self::$formatters[$key]?->format($moment);

        return is_string($formatted) ? $formatted : $moment->format($fallback);
    }
}
