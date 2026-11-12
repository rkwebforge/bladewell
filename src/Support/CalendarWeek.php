<?php

declare(strict_types=1);

namespace Bladewell\Support;

use DateTimeImmutable;

/**
 * A week, or a single day, of <x-widget.calendar> as hours: all-day events, and timed ones of a day or more, in lanes
 * along the top; the rest placed on each day's 24 hours by their clock times, side by side where they overlap. An
 * event over midnight is cut at it and drawn on both days. Its links carry dates (Y-m-d): the week's first day, or the
 * day.
 *
 * The hours are the clock's: on a daylight-saving day an event sits where its clock times say, so the skipped hour
 * holds nothing and the repeated one holds both; the times written on it say exactly when it is.
 */
final class CalendarWeek extends Calendar
{
    /** Quarter hours in a day: the rows of the grid. */
    public const int ROWS = 96;

    /**
     * The most side-by-side columns overlapping events get: a day has the width for twelve, each of a week's seven
     * days for six. Events past that aren't drawn over the others: they're counted in the day's "more" button, which
     * lists every event of the day.
     */
    public const int COLUMNS = 12;

    public const int WEEK_COLUMNS = 6;

    /** Where the hours open, unless an event starts earlier. */
    private const int OPENS_AT = 7;

    /**
     * @param  non-empty-list<CalendarDay>  $columns  The days: their all-day lanes, every event of theirs (for the "more"
     *                                                list and the agenda), and how many aren't drawn.
     * @param  array<string, list<CalendarBlock>>  $blocks  Each day's timed events, by Y-m-d.
     * @param  int  $opensAt  The hour the grid scrolls to first.
     */
    private function __construct(
        string $view,
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
        public readonly array $columns,
        public readonly array $blocks,
        public readonly int $opensAt,
    ) {
        parent::__construct($view, $date, $title, $timezone, $locale, $today, $previous, $next, $current, $min, $max);
    }

    /**
     * @param  'week'|'day'  $view
     * @param  iterable<mixed>  $events  Arrays, objects or models; see CalendarEvent.
     * @param  int  $perDay  The most all-day lanes a day shows before the rest go behind its "more" button.
     * @param  array<string, string|\Closure>  $keys  Where to read each field; see CalendarEvent::from().
     */
    public static function make(
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
    ): self {
        $zone = self::zone($timezone);
        $locale = LocalDate::locale($locale);
        $weekStart ??= LocalDate::firstDayOfWeek($locale);
        $today = now($zone)->format('Y-m-d');
        $min = self::dateOf($min);
        // A month as max means its last day.
        $max = is_string($max) && preg_match('/^\d{4}-\d{2}$/', $max) === 1 && ($last = self::dateOf($max)) !== null
            ? (new DateTimeImmutable($last))->format('Y-m-t')
            : self::dateOf($max);
        // Without limits of its own, the links stop at the first and last years it can show.
        $min ??= self::FIRST_YEAR.'-01-01';
        $max ??= self::LAST_YEAR.'-12-31';
        $day = self::clamp(self::dateOf($date) ?? $today, $min, $max);

        $length = $view === 'week' ? 7 : 1;
        $first = $view === 'week' ? self::weekOf($day, $weekStart) : $day;
        $days = array_map(static fn (int $i): string => CalendarEvent::addDays($first, $i), range(0, $length - 1));
        $events = self::eventsBetween($events, $zone, $days[0], $days[$length - 1], $keys);

        // A day or more of it goes along the top; shorter ones on the hours.
        $long = static fn (CalendarEvent $event): bool => $event->allDay || $event->end->getTimestamp() - $event->start->getTimestamp() >= 86400;
        $laid = self::layRow($days, array_values(array_filter($events, $long)), null, $today, max(1, $perDay), $locale);

        $blocks = [];
        $columns = [];
        $opensAt = self::OPENS_AT;
        foreach ($laid as $column) {
            $timed = array_values(array_filter($events, static fn (CalendarEvent $event): bool => ! $long($event) && $event->firstDay <= $column->iso && $event->lastDay >= $column->iso));
            [$blocks[$column->iso], $undrawn] = self::stack(self::cut($timed, $column->iso, $zone), $view === 'week' ? self::WEEK_COLUMNS : self::COLUMNS);
            foreach ($blocks[$column->iso] as $block) {
                // The morning end of last night's event doesn't count: the day would open at midnight for it.
                if (! $block->startsBefore) {
                    $opensAt = min($opensAt, intdiv($block->from, 4));
                }
            }

            // Every event of the day, all-day first: what its "more" button and the agenda list.
            $all = array_merge($column->events, $timed);
            usort($all, static fn (CalendarEvent $a, CalendarEvent $b): int => [$b->allDay, $a->start, $a->title] <=> [$a->allDay, $b->start, $b->title]);
            $columns[] = new CalendarDay($column->iso, $column->number, $column->label, $column->weekday, true, $column->today, $column->weekEnd, $column->lanes, $column->hidden + $undrawn, $all);
        }

        $step = $length;
        $previous = CalendarEvent::addDays($first, -$step);
        $next = CalendarEvent::addDays($first, $step);
        $current = self::clamp($today, $min, $max);

        return new self(
            view: $view,
            date: $first,
            title: $view === 'week' ? self::weekTitle($days[0], $days[6], $locale) : $columns[0]->label,
            timezone: $zone->getName(),
            locale: $locale,
            today: $today,
            // A step back is allowed while any of its days is on or after min.
            previous: CalendarEvent::addDays($previous, $step - 1) < $min ? null : $previous,
            next: $next > $max ? null : $next,
            current: $view === 'week' ? self::weekOf($current, $weekStart) : $current,
            min: $min,
            max: $max,
            columns: $columns,
            blocks: $blocks,
            opensAt: $opensAt,
        );
    }

    public function days(): array
    {
        return array_map(static fn (CalendarDay $day): string => $day->iso, $this->columns);
    }

    public function agenda(): array
    {
        return array_values(array_filter($this->columns, static fn (CalendarDay $day): bool => $day->events !== []));
    }

    public function periodTitle(string $date): string
    {
        return $this->view === 'week'
            ? self::weekTitle($date, CalendarEvent::addDays($date, 6), $this->locale)
            : self::format($date, 'yMMMMEEEEd', 'l, j F Y', $this->locale);
    }

    /** An hour on the grid's edge: "7 AM" where the locale has AM and PM, "07:00" where it doesn't. */
    public function hour(int $hour): string
    {
        $moment = new DateTimeImmutable(sprintf('2000-01-01 %02d:00', $hour));
        $short = self::format($moment, 'j', 'H:00', $this->locale);

        // A bare "07" doesn't read as a time.
        return preg_match('/^\d+$/u', $short) === 1 ? self::format($moment, 'jm', 'H:i', $this->locale) : $short;
    }

    /** "Nov 9 – Nov 15, 2026", "9 Nov – 15 Nov 2026". */
    private static function weekTitle(string $first, string $last, string $locale): string
    {
        return self::format($first, 'MMMd', 'j M', $locale).' – '.self::format($last, 'yMMMd', 'j M Y', $locale);
    }

    /**
     * Each event's part of the day, in quarter hours of the clock.
     *
     * @param  list<CalendarEvent>  $events
     * @return list<CalendarBlock>
     */
    private static function cut(array $events, string $iso, \DateTimeZone $zone): array
    {
        $dayStart = new DateTimeImmutable($iso, $zone);
        $dayEnd = new DateTimeImmutable(CalendarEvent::addDays($iso, 1), $zone);
        $minutes = static fn (DateTimeImmutable $moment): int => (int) $moment->format('G') * 60 + (int) $moment->format('i');

        $blocks = [];
        foreach ($events as $event) {
            $before = $event->start < $dayStart;
            $after = $event->end > $dayEnd;
            $start = $before ? 0 : $minutes($event->start);
            $end = $event->end >= $dayEnd ? 24 * 60 : max($start, $minutes($event->end));

            // At least two rows, so it can be read and clicked; kept inside the day.
            $from = min(intdiv($start, 15), self::ROWS - 2);
            $to = min(max((int) ceil($end / 15), $from + 2), self::ROWS);
            $blocks[] = new CalendarBlock($event, $from, $to - $from, 0, 1, 1, $to - $from, false, $before, $after);
        }
        usort($blocks, static fn (CalendarBlock $a, CalendarBlock $b): int => [$a->from, $b->length, $a->event->title] <=> [$b->from, $a->length, $b->event->title]);

        return $blocks;
    }

    /**
     * Puts overlapping blocks side by side: each group of blocks that overlap one another, directly or through
     * others, shares the day's width between as many columns as it needs at its busiest, each block in the first
     * column free when it starts. Drawn in time order, so the page reads in the order the day happens. A block with no
     * column free among the most allowed isn't drawn: it's counted, for the day's "more" button.
     *
     * @param  list<CalendarBlock>  $blocks  Sorted by start.
     * @return array{list<CalendarBlock>, int} The blocks drawn, and how many weren't.
     */
    private static function stack(array $blocks, int $most): array
    {
        $placed = [];
        $group = [];    // [block, column] of the group being placed
        $ends = [];     // column => the row its last block ends on
        $groupEnd = 0;
        $undrawn = 0;

        foreach ($blocks as $block) {
            if ($group !== [] && $block->from >= $groupEnd) {
                array_push($placed, ...self::widths($group, count($ends)));
                [$group, $ends, $groupEnd] = [[], [], 0];
            }
            $column = null;
            foreach ($ends as $i => $end) {
                if ($end <= $block->from) {
                    $column = $i;
                    break;
                }
            }
            if ($column === null && count($ends) >= $most) {
                $undrawn++;

                continue;
            }
            $column ??= count($ends);
            $ends[$column] = $block->from + $block->length;
            $group[] = [$block, $column];
            $groupEnd = max($groupEnd, $block->from + $block->length);
        }
        if ($group !== []) {
            array_push($placed, ...self::widths($group, count($ends)));
        }

        return [$placed, $undrawn];
    }

    /**
     * The group's blocks with their share of the day's width, laid out the way calendars people know do it.
     *
     * Events starting together (less than half an hour apart) sit side by side, each in its column. One that starts
     * half an hour or more after everything it overlaps in the columns before it is nested: drawn over those, set in
     * by a small step per column, so it has nearly the whole width for its text. Either kind is as wide as its own
     * column and the ones straight after it that are free while it lasts, or whose events start half an hour or more
     * later and so are drawn over it from there. $clear is how long its full width stays uncovered: where its text
     * goes.
     *
     * @param  list<array{CalendarBlock, int}>  $group  Each block and its column.
     * @return list<CalendarBlock>
     */
    private static function widths(array $group, int $columns): array
    {
        $overlaps = static fn (CalendarBlock $a, CalendarBlock $b): bool => $a->from < $b->from + $b->length && $b->from < $a->from + $a->length;

        // Nested: over everything before it. Not when an event in an earlier column starts with it or after it: those
        // two go side by side.
        $nested = [];
        foreach ($group as $i => [$block, $column]) {
            $nested[$i] = $column > 0;
            foreach ($group as [$other, $its]) {
                if ($its < $column && $overlaps($block, $other) && $block->from - $other->from < 2) {
                    $nested[$i] = false;
                    break;
                }
            }
        }

        $placed = [];
        foreach ($group as $i => [$block, $column]) {
            $span = 1;
            $clear = $block->length;
            for ($beside = $column + 1; $beside < $columns; $beside++) {
                $firstOver = null;
                foreach ($group as [$other, $its]) {
                    if ($its === $beside && $overlaps($block, $other)) {
                        $firstOver = min($firstOver ?? $other->from, $other->from);
                    }
                }
                if ($firstOver !== null) {
                    // Two rows are a line of text: less than that above the event drawn over it, and it stops here.
                    if ($firstOver - $block->from < 2) {
                        break;
                    }
                    $clear = min($clear, $firstOver - $block->from);
                }
                $span++;
            }
            // A nested event in a later column covers it from its own start, wherever that event's column is.
            foreach ($group as $j => [$other, $its]) {
                if ($nested[$j] && $its > $column && $overlaps($block, $other)) {
                    $clear = min($clear, $other->from - $block->from);
                }
            }
            $placed[] = new CalendarBlock($block->event, $block->from, $block->length, $column, $columns, $span, max(2, $clear), $nested[$i], $block->startsBefore, $block->endsAfter);
        }

        return $placed;
    }
}
