@props([
    // One day's bars and dots, drawn by the calendar widget in a month's day and along the top of a week or a day. The day.
    'day',
    // The calendar it's part of, which writes the times the locale's way.
    'calendar',
    // The calendar's id, for the day's "more" list.
    'calendarId',
    // Where there's no room for bars and titles, as on a phone, shows a count of the day's events instead, on a button
    // that lists them.
    'compact' => false,
])

@php
    // A bar covering several days is drawn in its first day's cell, as wide as the days it covers (classes written out
    // in full so Tailwind finds them).
    $spans = [1 => 'w-full', 2 => 'w-[200%]', 3 => 'w-[300%]', 4 => 'w-[400%]', 5 => 'w-[500%]', 6 => 'w-[600%]', 7 => 'w-[700%]'];
    // Colour is never the only sign: an event's status is text, and shows in the lists.
    $bars = ['primary' => 'bg-primary/15', 'success' => 'bg-success/15', 'warning' => 'bg-warning/25', 'error' => 'bg-error/15', 'neutral' => 'bg-field'];
    $dots = ['primary' => 'bg-primary', 'success' => 'bg-success', 'warning' => 'bg-warning', 'error' => 'bg-error', 'neutral' => 'bg-muted'];
    $focus = 'outline-none focus-visible:ring-2 focus-visible:ring-primary';
@endphp

@if ($day->lanes !== [])
    <ul @class(['mt-0.5 flex-col gap-0.5', $compact ? 'hidden @2xl:flex' : 'flex'])>
        @foreach ($day->lanes as $event)
            @if ($event === null)
                {{-- Room for a bar from a day before this one. --}}
                <li aria-hidden="true" class="h-6"></li>
            @elseif ($event->allDay || $event->spansDays())
                @php
                    $before = $day->continuesBefore($event);
                    $after = $day->continuesAfter($event);
                    $tag = $event->href ? 'a' : 'span';
                @endphp
                <li class="relative h-6">
                    <div @class(['absolute inset-y-0 start-0 z-10', $spans[$day->span($event)], 'ps-1' => ! $before, 'pe-1' => ! $after])>
                        <{{ $tag }}
                            @if ($event->href) href="{{ $event->href }}" @endif
                            title="{{ $event->title }}"
                            @class([
                                'text-foreground flex h-full min-w-0 items-center gap-1 rounded-md px-1.5 text-xs font-medium',
                                $bars[$event->tone],
                                'rounded-s-none' => $before,
                                'rounded-e-none' => $after,
                                "hover:underline {$focus}" => $event->href,
                            ])
                        >
                            @if ($before)
                                <x-widget.icon name="chevron-left" class="size-3 shrink-0 rtl:rotate-180" />
                            @endif
                            <span class="min-w-0 flex-1 truncate">{{ $event->title }}</span>
                            <span class="sr-only">, {{ $calendar->when($event) }}@if ($event->status), {{ $event->status }}@endif</span>
                            @if ($after)
                                <x-widget.icon name="chevron-right" class="size-3 shrink-0 rtl:rotate-180" />
                            @endif
                        </{{ $tag }}>
                    </div>
                </li>
            @else
                @php $tag = $event->href ? 'a' : 'span'; @endphp
                <li class="h-6 px-1">
                    <{{ $tag }}
                        @if ($event->href) href="{{ $event->href }}" @endif
                        title="{{ $event->title }}"
                        @class([
                            'text-foreground flex h-full min-w-0 items-center gap-1 rounded-md px-1 text-xs',
                            "hover:bg-field transition-colors motion-reduce:transition-none {$focus}" => $event->href,
                        ])
                    >
                        <span aria-hidden="true" class="size-2 shrink-0 rounded-full {{ $dots[$event->tone] }}"></span>
                        <span aria-hidden="true" class="text-muted hidden shrink-0 tabular-nums @4xl:inline">{{ $calendar->time($event->start) }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $event->title }}</span>
                        <span class="sr-only">, {{ $calendar->when($event) }}@if ($event->status), {{ $event->status }}@endif</span>
                    </{{ $tag }}>
                </li>
            @endif
        @endforeach
    </ul>
@endif

@if ($day->hidden > 0 || ($compact && $day->events !== []))
    {{-- Every event of the day, in full, not only those that didn't fit. Compact: the only way to them on a narrow
         page, so every day with events has one there, and the hit area grows to a fingertip's. --}}
    <x-widget.popover :id="$calendarId.'-'.$day->iso" :title="$day->label" width="sm" @class(['mt-0.5 px-1', '@2xl:hidden' => $day->hidden === 0])>
        <x-slot:trigger @class(['h-6 rounded-md text-xs font-medium', '@max-2xl:tap-target @max-2xl:relative' => $compact])>
            @if ($compact)
                <span class="bg-primary/15 text-foreground grid h-6 min-w-6 place-items-center rounded-full px-1.5 @2xl:hidden">{{ $calendar->number(count($day->events)) }}<span class="sr-only"> events on {{ $day->label }}</span></span>
            @endif
            @if ($day->hidden > 0)
                <span @class(['text-muted hover:text-foreground hover:bg-field h-6 items-center rounded-md px-1.5 transition-colors motion-reduce:transition-none', $compact ? 'hidden @2xl:flex' : 'flex'])>{{ $calendar->number($day->hidden) }} more<span class="sr-only"> events on {{ $day->label }}</span></span>
            @endif
        </x-slot:trigger>
        <ul class="flex flex-col gap-3">
            @foreach ($day->events as $event)
                <x-widget.calendar.entry :event="$event" :day="$day" :calendar="$calendar" />
            @endforeach
        </ul>
    </x-widget.popover>
@endif
