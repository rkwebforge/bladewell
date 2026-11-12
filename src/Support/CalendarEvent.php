<?php

declare(strict_types=1);

namespace Bladewell\Support;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * One event on <x-widget.calendar>, read from an array, an object or a model with id, title, start and, optionally,
 * end, all_day, href, status and tone.
 *
 * Ends are exclusive, for every event: one ending at 00:00 never reaches into the next day, and a one-day all-day
 * event on the 12th ends on the 13th (or leaves end out).
 *
 * All-day events are calendar dates: a date string or object is read as the date it names, never converted to another
 * timezone, so it can't slide to the day before or after. Timed events are moments: a date object keeps its own
 * timezone, a string without an offset is read in the app's timezone (as Eloquent stores them), and both are shown in
 * the calendar's timezone.
 */
final readonly class CalendarEvent
{
    public const array TONES = ['primary', 'success', 'warning', 'error', 'neutral'];

    /** What an event is read from; the names the calendar's keys can map. */
    public const array FIELDS = ['id', 'title', 'start', 'end', 'all_day', 'href', 'status', 'tone'];

    /**
     * @param  string  $firstDay  Y-m-d, in the calendar's timezone.
     * @param  string  $lastDay  Y-m-d, the last day it covers (inclusive).
     */
    private function __construct(
        public string $id,
        public string $title,
        public bool $allDay,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public string $firstDay,
        public string $lastDay,
        public ?string $href,
        public ?string $status,
        public string $tone,
    ) {}

    /**
     * @param  array<string, string|Closure>  $keys  Where to read a field when the event names it differently: the
     *                                               field (id, title, start…) => its key, a dotted path ("room.name"),
     *                                               or a closure given the event.
     */
    public static function from(mixed $event, DateTimeZone $timezone, array $keys = []): self
    {
        $read = static function (string $field) use ($event, $keys): mixed {
            $from = $keys[$field] ?? $field;

            return $from instanceof Closure ? $from($event) : data_get($event, $from);
        };
        $id = $read('id');
        if (! is_string($id) && ! is_int($id) || (string) $id === '') {
            throw new InvalidArgumentException('Every calendar event needs an id (a string or a number), so it can be told apart from the others.');
        }
        $id = (string) $id;

        $title = $read('title');
        if (! is_string($title) || trim($title) === '') {
            throw new InvalidArgumentException("Calendar event [{$id}] needs a title.");
        }

        $start = $read('start');
        $end = $read('end');
        $allDay = $read('all_day');
        // A bare date with no all_day is an all-day event: there's no time to show.
        $allDay = $allDay === null ? is_string($start) && self::isDate($start) : (bool) $allDay;

        $tone = $read('tone') ?? 'primary';
        if (! is_string($tone) || ! in_array($tone, self::TONES, true)) {
            $shown = is_scalar($tone) ? (string) $tone : get_debug_type($tone);
            throw new InvalidArgumentException("Unknown tone [{$shown}] for calendar event [{$id}]. Use one of: ".implode(', ', self::TONES).'.');
        }

        $href = $read('href');
        $status = $read('status');
        $href = is_string($href) ? self::safeHref($href) : null;
        $status = is_string($status) && trim($status) !== '' ? $status : null;

        return $allDay
            ? self::allDay($id, $title, $start, $end, $timezone, $href, $status, $tone)
            : self::timed($id, $title, $start, $end, $timezone, $href, $status, $tone);
    }

    private static function allDay(string $id, string $title, mixed $start, mixed $end, DateTimeZone $timezone, ?string $href, ?string $status, string $tone): self
    {
        $first = self::dateOf($start, $id, 'a start');
        // Without an end it's that one day. Worked out apart from a given end, so a last day of 9999-12-31 never has to
        // pass through the five-digit year after it.
        $last = $first;
        if ($end !== null) {
            $endsOn = self::dateOf($end, $id, 'an end');
            if ($endsOn <= $first) {
                throw new InvalidArgumentException("Calendar event [{$id}] ends on {$endsOn}, before it starts on {$first}. An all-day end is the day after the last one: a one-day event on {$first} ends on ".self::addDays($first, 1).', or leaves end out.');
            }
            $last = self::addDays($endsOn, -1);
        }
        $midnight = new DateTimeImmutable($first, $timezone);

        return new self(
            $id, $title, true,
            $midnight,
            $midnight->modify('+'.((int) (new DateTimeImmutable($first))->diff(new DateTimeImmutable($last))->days + 1).' day'),
            $first,
            $last,
            $href, $status, $tone,
        );
    }

    private static function timed(string $id, string $title, mixed $start, mixed $end, DateTimeZone $timezone, ?string $href, ?string $status, string $tone): self
    {
        $startsAt = self::momentOf($start, $id, 'a start', $timezone);
        // Without an end it's a moment, shown at its start time.
        $endsAt = $end === null ? $startsAt : self::momentOf($end, $id, 'an end', $timezone);
        if ($endsAt < $startsAt) {
            throw new InvalidArgumentException("Calendar event [{$id}] ends at {$endsAt->format('Y-m-d H:i')}, before it starts at {$startsAt->format('Y-m-d H:i')}.");
        }

        $first = $startsAt->format('Y-m-d');
        $last = $endsAt->format('Y-m-d');
        // The end is exclusive: ending at midnight, it stops on the day before.
        if ($endsAt > $startsAt && $endsAt->format('H:i:s.u') === '00:00:00.000000') {
            $last = self::addDays($last, -1);
        }

        return new self($id, $title, false, $startsAt, $endsAt, $first, $last, $href, $status, $tone);
    }

    /** Whether it covers more than one day. */
    public function spansDays(): bool
    {
        return $this->lastDay > $this->firstDay;
    }

    /** Whether it lasts no time at all: a deadline, a reminder. */
    public function isMoment(): bool
    {
        // By their moments, not as objects: two dates for the same instant are still a moment.
        return ! $this->allDay && $this->end->format('U.u') === $this->start->format('U.u');
    }

    public static function isDate(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    public static function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->modify("{$days} day")->format('Y-m-d');
    }

    /**
     * The calendar date a value names, read as written: no timezone conversion. One with a time of day is turned
     * down: it's a moment, and which day a moment falls on depends on the timezone, so reading its date as written
     * would quietly put some events on the day before or after.
     */
    private static function dateOf(mixed $value, string $id, string $key): string
    {
        $date = match (true) {
            $value instanceof DateTimeInterface => DateTimeImmutable::createFromInterface($value),
            is_string($value) => self::parse($value, new DateTimeZone('UTC')),
            default => null,
        };
        if ($date === null) {
            throw new InvalidArgumentException("Calendar event [{$id}] has {$key} that isn't a date. Use Y-m-d or a date object.");
        }
        if ($date->format('H:i:s') !== '00:00:00') {
            throw new InvalidArgumentException("Calendar event [{$id}] is all day, but has {$key} with a time ({$date->format('H:i')}). All-day dates are read as written, never moved between timezones, so pass the date itself: a Y-m-d string, or for a stored moment, its date where the event happens, e.g. \$event->starts_at->setTimezone(\$timezone)->toDateString().");
        }

        return $date->format('Y-m-d');
    }

    /**
     * A date or a date and time as written, or null when it isn't one. PHP reads 30 February as 2 March; here a date
     * that doesn't exist is not a date.
     */
    private static function parse(string $value, DateTimeZone $zone): ?DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }
        try {
            $parsed = new DateTimeImmutable($value, $zone);
        } catch (Exception) {
            return null;
        }
        $problems = DateTimeImmutable::getLastErrors();

        return $problems !== false && ($problems['warning_count'] > 0 || $problems['error_count'] > 0) ? null : $parsed;
    }

    /**
     * The link, if it's one a page may follow: a path, or an http, https, mailto or tel address. Anything else, such
     * as javascript: or data:, would run as script when clicked, so the event is shown without a link. Event data
     * often comes from the people using the app, so this is checked here, not left to each caller.
     */
    private static function safeHref(string $href): ?string
    {
        $href = trim($href);
        // Browsers ignore tabs, newlines and control characters inside a scheme: "java\tscript:" still runs.
        $compact = (string) preg_replace('/[\x00-\x20]+/', '', $href);
        $hasScheme = preg_match('/^[a-z][a-z0-9+.\-]*:/i', $compact) === 1;

        return $href === '' || ($hasScheme && preg_match('/^(https?|mailto|tel):/i', $compact) !== 1) ? null : $href;
    }

    private static function momentOf(mixed $value, string $id, string $key, DateTimeZone $timezone): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone($timezone);
        }
        if (is_string($value) && ($moment = self::parse($value, new DateTimeZone((string) config('app.timezone', 'UTC')))) !== null) {
            return $moment->setTimezone($timezone);
        }

        throw new InvalidArgumentException("Calendar event [{$id}] has {$key} that isn't a date and time. Use a date object or a string like 2026-11-12T09:00:00+00:00.");
    }
}
