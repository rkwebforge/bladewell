{{-- A week as hours, opening at 7:00, or earlier when an event starts earlier; the rest of the day scrolls. All-day events, and those of a day or more, run along the top. Events that start together sit side by side; one that starts during another is drawn over the rest of it, set in a little, so both titles can be read. One over midnight is drawn on both days, and a line marks the current time. Where there's no room for seven columns, as on a phone, it's a list of the week's days. --}}
<x-widget.calendar
    label="My week"
    timezone="Europe/London"
    view="week"
    week-start="1"
    :events="$events"
    param="week"
    :min="now()->subWeeks(2)"
    :max="now()->addWeeks(4)"
/>
