{{-- The month as a list of its days with events, each with its times, status and, for one over several days, its whole span. view="auto", the default, shows this instead of the month where there's no room for it, as on a phone. --}}
<x-widget.calendar label="My plans" timezone="America/New_York" view="agenda" :events="$events" :nav="false" />
