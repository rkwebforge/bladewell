@props([
    // One row of a day's list, in the agenda and in a day's "more" list, drawn by the calendar widget. The event.
    'event',
    // The day the row is listed under: an event over several days shows its time on that day.
    'day',
    // The calendar's month, which writes the times the locale's way.
    'calendar',
])

@php
    $dots = ['primary' => 'bg-primary', 'success' => 'bg-success', 'warning' => 'bg-warning', 'error' => 'bg-error', 'neutral' => 'bg-muted'];
@endphp

<li class="flex min-w-0 items-start gap-2.5 text-sm">
    <span aria-hidden="true" class="mt-1.5 size-2 shrink-0 rounded-full {{ $dots[$event->tone] }}"></span>
    <div class="min-w-0 flex-1">
        <p class="text-muted text-xs tabular-nums">{{ $calendar->timeOn($event, $day) }}</p>
        <p class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
            @if ($event->href)
                <a href="{{ $event->href }}" class="text-foreground tap-target-y relative inline-block py-0.5 font-medium break-words underline-offset-2 outline-none hover:underline focus-visible:ring-2 focus-visible:ring-primary">{{ $event->title }}</a>
            @else
                <span class="text-foreground font-medium break-words">{{ $event->title }}</span>
            @endif
            @if ($event->status)
                <span class="border-line text-foreground rounded-full border px-2 text-xs">{{ $event->status }}</span>
            @endif
        </p>
        {{-- Over several days: the whole span, since the time above is only this day's part of it. --}}
        @if ($event->spansDays())
            <p class="text-muted text-xs">{{ $calendar->when($event) }}</p>
        @endif
    </div>
</li>
