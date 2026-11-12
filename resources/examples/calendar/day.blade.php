{{-- One day as hours, which fits a phone: an appointment book, a room's bookings. Previous and Next move a day at a time. --}}
<x-widget.calendar
    label="Appointments"
    timezone="America/New_York"
    view="day"
    :events="$events"
    param="day"
    :min="now()->subWeek()"
    :max="now()->addWeeks(2)"
/>
