<?php

declare(strict_types=1);

namespace Bladewell\Support;

/**
 * A timed event's part of one day, in <x-widget.calendar>'s week and day views: where it sits on the day's 96
 * quarter-hour rows, and which of the side-by-side columns it takes among the events it overlaps. An event is only
 * ever drawn over one that started at least half an hour before it, so every event keeps its first lines showing and
 * can be seen and clicked.
 */
final readonly class CalendarBlock
{
    /**
     * @param  int  $from  The quarter hour it starts in, 0 (00:00) to 94.
     * @param  int  $length  Quarter hours, at least 2, so even a five-minute event can be read and clicked; its text
     *                       says how long it really is.
     * @param  int  $column  0 to 11, from the start edge.
     * @param  int  $columns  How many columns its group of overlapping events shares the day's width between, 1 to 12.
     * @param  int  $span  How many of those columns it's as wide as: its own, any beside it that nothing else uses while
     *                     it lasts, and any whose events start at least half an hour after it. Those are drawn over it
     *                     from where they start, which leaves its first lines clear across its whole width.
     * @param  int  $clear  How many quarter hours from its start nothing is drawn over it: where its text goes. Its
     *                      whole length when nothing is.
     * @param  bool  $nested  Whether every event it overlaps in the columns before its own started at least half an
     *                        hour earlier. Then it's drawn over them, set in by a small step for each column instead
     *                        of by its share of the width, so it has nearly the whole day's width for its text, and
     *                        each of theirs keeps its first lines and an edge showing.
     * @param  bool  $startsBefore  Whether it began on an earlier day.
     * @param  bool  $endsAfter  Whether it goes on into a later day.
     */
    public function __construct(
        public CalendarEvent $event,
        public int $from,
        public int $length,
        public int $column,
        public int $columns,
        public int $span,
        public int $clear,
        public bool $nested,
        public bool $startsBefore,
        public bool $endsAfter,
    ) {}
}
