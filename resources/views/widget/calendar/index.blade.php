@props([
    // Required: names the calendar for screen readers ("Team calendar", "Bookings").
    'label' => null,
    // The calendar's id; scripts can reach it by it, so it must be unique. Made up when left out.
    'id' => null,
    // Required: the timezone timed events are shown in, and named in, e.g. Europe/London or the signed-in person's.
    // All-day events are dates and never move between days, whatever it is.
    'timezone' => null,
    // The events: arrays, objects or models with id, title, start, and optionally end (exclusive), all_day, href,
    // status (text, e.g. "Tentative") and tone (primary, success, warning, error or neutral). Pass every event that
    // overlaps what's shown, including those that began before it: Bladewell\Support\Calendar::range() gives those
    // moments.
    'events' => [],
    // Where to read a field when your events name it differently, so models can be passed as they are: the field =>
    // its key, a dotted path, or a closure given the event. ['title' => 'room.name', 'start' => 'starts_at',
    // 'end' => 'ends_at', 'href' => fn ($booking) => route('bookings.show', $booking)].
    'keys' => [],
    // What's shown: YYYY-MM, YYYY-MM-DD or a date object; the month, week or day holding it. Left out, it's read from
    // the page's query (param), where the links put it, so the links work as they are. Anything that isn't a date, such
    // as a mistyped ?date=, shows today's.
    'date' => null,
    // month; week or day, as hours; agenda, a list of the month's days with events; or auto: the month where there's
    // room, the agenda where there isn't, as on a phone. week, too, is a list of its days where there's no room, and
    // month keeps its grid there, each day showing how many events it has, on a button that lists them.
    'view' => 'auto',
    // The views people can switch between, on buttons beside Today: any of month, week and day, as an array or
    // "month,week,day". The one showing is read from the page's query (view-param), else it's view, else the first.
    'views' => null,
    // The query parameter the view buttons set.
    'viewParam' => 'view',
    // The most events a day shows in the month, or along the top of a week, before the rest go behind its "more" button.
    'perDay' => 3,
    // Shows previous, today and next links. false leaves them out, for a calendar you move yourself.
    'nav' => true,
    // The query parameter the links set: YYYY-MM for a month, YYYY-MM-DD for a week (its first day) or a day. The rest of
    // the page's query is kept.
    'param' => 'date',
    // A link of your own for each period instead, with {date} where the date goes, and with views, {view} where the view
    // goes: "/bookings/{view}/{date}". Use it inside a Livewire component, where the page's own address isn't the
    // request's.
    'url' => null,
    // true: each link loads its whole page, for a page where something besides the calendar changes with the date (a
    // total, a list). Otherwise the script fetches that page and takes only the calendar from it.
    'reload' => false,
    // The earliest date the links go to: YYYY-MM or YYYY-MM-DD, or a date object. Anything earlier shows this.
    'min' => null,
    // The latest date the links go to; a month means its last day. Anything later shows this.
    'max' => null,
    // The first day of the week, 0 (Sunday) to 6 (Saturday); defaults to the locale's.
    'weekStart' => null,
    // The language for month and day names, dates and times (en, fr, ar…); defaults to the app's.
    'locale' => null,
    // Shown in the agenda when there are no events; "No events this month." or "No events this week." by default.
    'empty' => null,
])

@php
    if (! is_string($label) || trim($label) === '') {
        throw new \InvalidArgumentException('<x-widget.calendar> needs a label, e.g. label="Team calendar": it names the calendar for screen readers.');
    }
    // Asked for, not guessed: the server's timezone is rarely the viewer's, and a wrong one moves every time shown.
    if (! is_string($timezone) || $timezone === '') {
        throw new \InvalidArgumentException('<x-widget.calendar> needs a timezone to show times in, e.g. timezone="Europe/London" or :timezone="$user->timezone".');
    }
    if (! in_array($view, ['auto', 'month', 'agenda', 'week', 'day'], true)) {
        throw new \InvalidArgumentException("Unknown view [{$view}] for <x-widget.calendar>. Use one of: auto, month, agenda, week, day.");
    }
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id ?? 'calendar', explicit: $id !== null);
    $ownUrl = is_string($url) && $url !== '';
    $url = $ownUrl ? str_replace(['%7Bdate%7D', '%7bdate%7d', '%7Bview%7D', '%7bview%7d'], ['{date}', '{date}', '{view}', '{view}'], $url) : null;

    // The views on offer, and which is showing: the one the view buttons put in the address, when it's one of them;
    // otherwise view; otherwise the first. auto and agenda count as the month.
    $offered = array_values(array_filter(array_map('trim', is_string($views) ? explode(',', $views) : (is_array($views) ? $views : []))));
    foreach ($offered as $one) {
        if (! in_array($one, ['month', 'week', 'day'], true)) {
            throw new \InvalidArgumentException("Unknown view [{$one}] in views for <x-widget.calendar>. Use any of: month, week, day.");
        }
    }
    if ($offered !== []) {
        if ($ownUrl && ! str_contains($url, '{view}')) {
            throw new \InvalidArgumentException('<x-widget.calendar> with views and a url of its own needs {view} in the url, e.g. url="/bookings/{view}/{date}": it is where each view\'s button leads.');
        }
        $asked = $ownUrl ? null : request()->query($viewParam);
        $own = in_array($view, ['week', 'day'], true) ? $view : 'month';
        $showing = is_string($asked) && in_array($asked, $offered, true) ? $asked : (in_array($own, $offered, true) ? $own : $offered[0]);
        // Left as it is when it's already that view: auto and agenda stay, as kinds of month.
        $view = $showing === $own ? $view : $showing;
    }
    $period = in_array($view, ['week', 'day'], true) ? $view : 'month';
    // The links set the parameter, so without a date of its own the calendar reads it back: otherwise they'd change the
    // address and nothing else.
    $date ??= $nav && is_string($param) && $param !== '' ? request()->query($param) : null;
    $calendar = \Bladewell\Support\Calendar::for(
        view: $period,
        events: $events ?? [],
        timezone: $timezone,
        date: $date,
        locale: $locale,
        weekStart: $weekStart === null ? null : (int) $weekStart,
        perDay: (int) $perDay,
        min: $min,
        max: $max,
        keys: is_array($keys) ? $keys : [],
    );
    $empty ??= $period === 'week' ? 'No events this week.' : 'No events this month.';

    // Each period's link: the page's own address with the parameter set, or url with {date} (and {view}) filled in.
    // route() writes {date} as %7Bdate%7D, so both are read.
    $template = $ownUrl
        ? str_replace('{view}', $period, $url)
        : str_replace(['%7Bdate%7D', '%7bdate%7d'], '{date}', request()->fullUrlWithQuery([$param => '{date}']));
    $link = static fn (string $date): string => str_replace('{date}', $date, $template);
    // Each view's link, to the same place in time: today's week or day when today is showing, else the first day shown.
    $shown = $calendar->days();
    $anchor = $period === 'month'
        ? (str_starts_with($calendar->today, $calendar->date) ? $calendar->today : "{$calendar->date}-01")
        : (in_array($calendar->today, $shown, true) ? $calendar->today : $shown[0]);
    $viewLinks = [];
    foreach ($offered as $one) {
        $to = $one === 'month' ? substr($anchor, 0, 7) : $anchor;
        $viewLinks[$one] = [
            'date' => $to,
            'href' => $ownUrl ? str_replace(['{view}', '{date}'], [$one, $to], $url) : request()->fullUrlWithQuery([$viewParam => $one, $param => $to]),
        ];
    }
    $focus = 'outline-none focus-visible:ring-2 focus-visible:ring-primary';
@endphp

{{--
    Events, drawn on the server: a month as a table of weeks; a week or a day as hours; or a list of the days with
    events (the agenda). The links to other periods are plain links, so it works without JavaScript and the page stays
    cacheable; with it, a link's page is fetched and only the calendar taken from it, so the page isn't loaded again. resources/js/widget/calendar moves the "today" mark and the line at the current time to the viewer's own
    clock in this timezone (a cached page can be days old), opens the hours at the start of the day's events, and fires
    calendar:navigate on the links, for Livewire.
--}}
<section
    id="{{ $id }}"
    aria-label="{{ $label }}"
    data-calendar
    data-view="{{ $period }}"
    data-timezone="{{ $calendar->timezone }}"
    data-date="{{ $calendar->date }}"
    @if ($nav)
        data-url="{{ $template }}"
        @unless ($ownUrl) data-param="{{ $param }}" @endunless
        @if ($offered !== [] && ! $ownUrl) data-view-param="{{ $viewParam }}" @endif
        @if ($reload) data-reload @endif
        @if ($calendar->min) data-min="{{ $calendar->min }}" @endif
        @if ($calendar->max) data-max="{{ $calendar->max }}" @endif
    @endif
    {{-- Dimmed while the script fetches another period. --}}
    {{ $attributes->class(['@container flex min-w-0 flex-col gap-3 transition-opacity aria-busy:opacity-60 motion-reduce:transition-none']) }}
>
    {{-- The period moved to, read out when the script changes it without a page load. In the accessibility tree while
         empty, so the first one is read too. --}}
    <p data-calendar-status role="status" class="sr-only"></p>

    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <div class="min-w-0">
            <p id="{{ $id }}-title" class="text-foreground text-lg font-semibold">{{ $calendar->title }}</p>
            <p class="text-muted text-xs">Times in {{ $calendar->timezoneName() }}</p>
        </div>

        @if ($nav)
            {{-- The button widget's buttons, as links: they work without the script, and look and ease like every other. --}}
            <div class="flex flex-wrap items-center gap-2">
                @if (count($viewLinks) > 1)
                    {{-- A track of the quiet buttons' own colour, so only the view showing stands out of it. --}}
                    <nav aria-label="{{ $label }}: view" class="bg-field inline-flex gap-0.5 rounded-xl p-0.5">
                        @foreach ($viewLinks as $one => $to)
                            <x-widget.button
                                :href="$to['href']"
                                size="sm"
                                :variant="$one === $period ? 'primary' : 'neutral'"
                                data-calendar-nav="view"
                                data-view="{{ $one }}"
                                data-date="{{ $to['date'] }}"
                                :aria-current="$one === $period ? 'true' : false"
                            >{{ ucfirst($one) }}</x-widget.button>
                        @endforeach
                    </nav>
                @endif

                <nav aria-label="{{ $label }}: {{ $period }}s" class="flex items-center gap-2">
                    {{-- Today's period as the server sees it; the script corrects it for the viewer. --}}
                    <x-widget.button :href="$link($calendar->current)" size="sm" variant="neutral" data-calendar-nav="today" data-date="{{ $calendar->current }}">Today</x-widget.button>
                    @foreach ([['previous', "Previous {$period}", 'chevron-left'], ['next', "Next {$period}", 'chevron-right']] as [$which, $name, $icon])
                        {{-- Past min or max there's nowhere to go: the button widget draws a link that's disabled as a disabled
                             button, and its name says why. The arrow turns round where the page reads right to left. --}}
                        <x-widget.button
                            :href="$calendar->{$which} === null ? null : $link($calendar->{$which})"
                            :disabled="$calendar->{$which} === null"
                            size="sm"
                            variant="neutral"
                            :icon="$icon"
                            :label="$calendar->{$which} === null ? $name.', not available' : $name.', '.$calendar->periodTitle($calendar->{$which})"
                            data-calendar-nav="{{ $which }}"
                            :data-date="$calendar->{$which} ?? false"
                            class="rtl:[&_svg]:rotate-180"
                        />
                    @endforeach
                </nav>
            </div>
        @endif
    </div>

    @if ($calendar instanceof \Bladewell\Support\CalendarMonth && $view !== 'agenda')
        {{-- Rows of weeks and a column per weekday, so a screen reader names each day's weekday as it moves along. --}}
        <div @class(['hidden @2xl:block' => $view === 'auto'])>
            <table aria-labelledby="{{ $id }}-title" class="w-full table-fixed border-collapse">
                <thead>
                    <tr>
                        @foreach ($calendar->weekdays as $weekday)
                            {{-- As wide as the day number below it, so the two line up. --}}
                            <th scope="col" abbr="{{ $weekday['long'] }}" class="text-muted pb-2 ps-1 text-start text-xs font-medium"><span class="inline-block min-w-7 text-center">{{ $weekday['short'] }}</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($calendar->weeks as $week)
                        <tr>
                            @foreach ($week as $day)
                                {{-- No lines between days, so a bar running across them reads as one. --}}
                                <td
                                    data-calendar-day="{{ $day->iso }}"
                                    @if ($day->today) data-today aria-current="date" @endif
                                    @class(['border-line border-t p-0 pb-1 align-top', $view === 'month' ? 'h-16 @2xl:h-28' : 'h-28', 'bg-field/40' => ! $day->inMonth])
                                >
                                    <div class="px-1 pt-1">
                                        <time datetime="{{ $day->iso }}" @class([
                                            'in-data-today:bg-primary-fill in-data-today:text-on-primary in-data-today:font-semibold grid size-7 place-items-center rounded-full text-xs',
                                            $day->inMonth ? 'text-foreground' : 'text-muted',
                                        ])>{{ $day->number }}</time>
                                    </div>
                                    {{-- With auto, a narrow page shows the agenda instead; a month asked for outright stays a grid,
                                         with a count per day. --}}
                                    <x-widget.calendar.lanes :day="$day" :calendar="$calendar" :calendar-id="$id" :compact="$view === 'month'" />
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($calendar instanceof \Bladewell\Support\CalendarWeek)
        @php
            $grid = $period === 'week' ? 'grid-cols-[3.5rem_repeat(7,minmax(0,1fr))]' : 'grid-cols-[3.5rem_minmax(0,1fr)]';
            $blockTones = [
                'primary' => 'bg-primary/15 border-primary',
                'success' => 'bg-success/15 border-success',
                'warning' => 'bg-warning/25 border-warning',
                'error' => 'bg-error/15 border-error',
                'neutral' => 'bg-field border-muted',
            ];
        @endphp
        {{-- The hours scroll inside a box of their own, which the keyboard reaches (tabindex); the days and all-day events
             stay in view above them. Never taller than three quarters of the screen, and scrolling past its ends moves the
             page: on a phone, a box as tall as the screen that kept the scroll to itself would trap it. --}}
        <div @class(['hidden @2xl:block' => $period === 'week'])>
            <div
                role="region"
                aria-labelledby="{{ $id }}-title"
                tabindex="0"
                data-calendar-scroll="{{ $calendar->opensAt }}"
                {{-- scroll-pt: an event the keyboard reaches is scrolled clear of the day headings, not under them. The script
                     sets it to their real height, which grows with the all-day events. --}}
                class="border-line relative max-h-[min(40rem,75dvh)] scroll-pt-24 overflow-y-auto rounded-lg border {{ $focus }}"
            >
                <div data-calendar-head class="bg-surface border-line sticky top-0 z-30 border-b">
                    <table class="w-full table-fixed border-collapse">
                        <thead>
                            <tr>
                                <td class="w-14"></td>
                                @foreach ($calendar->columns as $day)
                                    <th scope="col" id="{{ $id }}-day-{{ $day->iso }}" data-calendar-day="{{ $day->iso }}" @if ($day->today) data-today aria-current="date" @endif class="py-2 font-normal">
                                        <span aria-hidden="true" class="flex flex-col items-center gap-0.5">
                                            <span class="text-muted text-xs">{{ $day->weekday }}</span>
                                            <span class="in-data-today:bg-primary-fill in-data-today:text-on-primary text-foreground grid size-8 place-items-center rounded-full text-sm font-semibold">{{ $day->number }}</span>
                                        </span>
                                        <span class="sr-only">{{ $day->label }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th scope="row" class="text-muted pe-2 pb-1 text-end align-top text-[0.6875rem] font-normal">All day</th>
                                @foreach ($calendar->columns as $day)
                                    <td class="border-line border-s p-0 pb-1 align-top">
                                        <x-widget.calendar.lanes :day="$day" :calendar="$calendar" :calendar-id="$id" />
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- 24 hours of 3rem: a quarter hour is a 0.75rem row. --}}
                <div data-calendar-hours class="relative grid {{ $grid }}">
                    <div aria-hidden="true">
                        @for ($hour = 0; $hour < 24; $hour++)
                            <div class="relative h-12">
                                @if ($hour > 0)
                                    <span class="text-muted absolute -top-2 end-2 text-[0.6875rem] tabular-nums">{{ $calendar->hour($hour) }}</span>
                                @endif
                            </div>
                        @endfor
                    </div>

                    @foreach ($calendar->columns as $day)
                        <div data-calendar-column="{{ $day->iso }}" class="border-line relative h-[72rem] border-s bg-[repeating-linear-gradient(to_bottom,var(--color-line)_0_1px,transparent_1px_3rem)]">
                            @if ($calendar->blocks[$day->iso] !== [])
                                {{-- One list per day, named by it, in time order. --}}
                                <ol aria-labelledby="{{ $id }}-day-{{ $day->iso }}">
                                    @foreach ($calendar->blocks[$day->iso] as $block)
                                        @php
                                            $event = $block->event;
                                            $tag = $event->href ? 'a' : 'span';
                                            // How many lines of text fit above anything drawn over it: rows of 12px, less the
                                            // padding, in lines of 15px.
                                            $lines = max(1, intdiv($block->clear * 12 - 4, 15));
                                            $oneLine = $lines < 2;
                                            // Its share of the day's width. Side by side with enough others that a time wouldn't
                                            // fit under a title, the title gets every line: where the block sits says when it is,
                                            // and its tooltip and screen-reader text say it in full.
                                            $share = $block->columns / ($block->nested ? $block->column + $block->span : $block->span);
                                            $narrow = $share >= ($period === 'week' ? 3 : 5);
                                            // Whole lines only: the text's box is exactly as tall as the lines that fit, so none is
                                            // ever cut through the middle.
                                            $fits = [1 => 'max-h-[1.25em]', 2 => 'max-h-[2.5em]', 3 => 'max-h-[3.75em]', 4 => 'max-h-[5em]', 5 => 'max-h-[6.25em]', 6 => 'max-h-[7.5em]', 7 => 'max-h-[8.75em]', 8 => 'max-h-[10em]'][min(8, $lines)];
                                            $clamp = [1 => 'line-clamp-1', 2 => 'line-clamp-2', 3 => 'line-clamp-3', 4 => 'line-clamp-4', 5 => 'line-clamp-5', 6 => 'line-clamp-6'][min(6, $lines)];
                                            $time = $calendar->timeOn($event, $day).($event->status ? " · {$event->status}" : '');
                                            // A week's tiles carry the title alone: a seventh of the width has room for a time on
                                            // some and not on others, which reads as if some had none. A day's have the width.
                                            $withTime = $period === 'day' && ! $narrow;
                                            // The whole of it, for the tooltip: the title a tile may cut short, when it is, and its
                                            // status.
                                            $tip = $event->title.' · '.$calendar->when($event).($event->status ? " · {$event->status}" : '');
                                            // Beside the others, by its share of the width; or nested over the ones that started
                                            // before it, set in a step for each, with the rest of the width to itself.
                                            $place = $block->nested
                                                ? 'start-[calc(var(--calendar-column)*0.625rem)] w-[calc((var(--calendar-column)+var(--calendar-span))*100%/var(--calendar-columns)-var(--calendar-column)*0.625rem)]'
                                                : 'start-[calc(var(--calendar-column)*100%/var(--calendar-columns))] w-[calc(var(--calendar-span)*100%/var(--calendar-columns))]';
                                        @endphp
                                        {{-- Placed by its quarter hours and columns (custom properties from the ranges in base.css), never style="".
                                             In time order, so an event that starts later is drawn over the ones before it. --}}
                                        <li class="bg-surface absolute top-[calc(var(--calendar-from)*0.75rem)] h-[calc(var(--calendar-length)*0.75rem)] {{ $place }} rounded-md [--calendar-from:{{ $block->from }}] [--calendar-length:{{ $block->length }}] [--calendar-column:{{ $block->column }}] [--calendar-columns:{{ $block->columns }}] [--calendar-span:{{ $block->span }}]">
                                            {{-- Its title in full, its time and its status follow the pointer over it, sit under it for
                                                 the keyboard (an event that isn't a link takes focus for that) and show on a tap. --}}
                                            <x-widget.tooltip-cursor :text="$tip" @class(['h-full w-full rounded-md', $focus => ! $event->href])>
                                                <{{ $tag }}
                                                    @if ($event->href) href="{{ $event->href }}" @endif
                                                    @class([
                                                        'text-foreground ring-surface block h-full min-w-0 flex-1 overflow-hidden rounded-md border-s-2 pt-0.5 leading-tight ring-1',
                                                        // A little smaller and tighter where it's narrow: a letter or two more of the title.
                                                        $narrow ? 'px-1 text-[0.6875rem]' : 'px-1.5 text-xs',
                                                        $blockTones[$event->tone],
                                                        'rounded-t-none' => $block->startsBefore,
                                                        'rounded-b-none' => $block->endsAfter,
                                                        "hover:underline {$focus}" => $event->href,
                                                    ])
                                                >
                                                    @if ($oneLine)
                                                        {{-- The title first, in full if it fits. In a day the time follows, if all of it fits
                                                             too: otherwise it wraps to a second line, which this one line's height hides. --}}
                                                        <span class="flex h-[1.25em] min-w-0 flex-wrap gap-x-1.5 overflow-hidden">
                                                            <span class="max-w-full truncate font-medium">{{ $event->title }}</span>
                                                            @if ($withTime)
                                                                <span aria-hidden="true" class="whitespace-nowrap tabular-nums">{{ $time }}</span>
                                                            @endif
                                                        </span>
                                                    @else
                                                        {{-- The title over the lines there are, breaking inside a long word rather than cut to
                                                             one. In a day, then the time, wrapped over the lines left: all of it or none.
                                                             Where its lines don't all fit it moves to a second column, which is out of
                                                             sight, so half a time ("11:00 AM –") never shows. --}}
                                                        <span class="flex flex-col flex-wrap content-start overflow-hidden {{ $fits }}">
                                                            <span class="w-full font-medium [overflow-wrap:anywhere] {{ $clamp }}">{{ $event->title }}</span>
                                                            @if ($withTime)
                                                                <span aria-hidden="true" class="w-full tabular-nums">{{ $time }}</span>
                                                            @endif
                                                        </span>
                                                    @endif
                                                    {{-- A link is described by its tooltip. Text that isn't one may not be, so it says when itself. --}}
                                                    @unless ($event->href)
                                                        <span class="sr-only">, {{ $calendar->when($event) }}@if ($event->status), {{ $event->status }}@endif</span>
                                                    @endunless
                                                </{{ $tag }}>
                                            </x-widget.tooltip-cursor>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    @endforeach

                    {{-- The current time: the script puts it in today's column and keeps it there. --}}
                    <div data-calendar-now hidden aria-hidden="true" class="border-error pointer-events-none absolute inset-x-0 z-20 border-t-2">
                        <span class="bg-error absolute -top-[5px] -start-[5px] size-2 rounded-full"></span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($view === 'agenda' || $view === 'auto' || $view === 'week')
        <div @class(['@2xl:hidden' => $view !== 'agenda'])>
            @php $agenda = $calendar->agenda(); @endphp
            @if ($agenda === [])
                <p class="text-muted border-line border-t py-6 text-center text-sm">{{ $empty }}</p>
            @else
                <ol aria-labelledby="{{ $id }}-title" class="divide-line border-line divide-y border-t">
                    @foreach ($agenda as $day)
                        <li data-calendar-day="{{ $day->iso }}" @if ($day->today) data-today aria-current="date" @endif class="flex gap-4 py-3">
                            {{-- The day, big for the eye; the full date for screen readers. --}}
                            <div aria-hidden="true" class="flex w-12 shrink-0 flex-col items-center">
                                <span class="text-muted text-xs">{{ $day->weekday }}</span>
                                <span class="in-data-today:bg-primary-fill in-data-today:text-on-primary text-foreground grid size-9 place-items-center rounded-full text-lg font-semibold">{{ $day->number }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="sr-only">{{ $day->label }}</p>
                                <ul class="flex flex-col gap-3">
                                    @foreach ($day->events as $event)
                                        <x-widget.calendar.entry :event="$event" :day="$day" :calendar="$calendar" />
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    @endif
</section>
