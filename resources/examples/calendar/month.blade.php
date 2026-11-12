{{-- A team's calendar, with buttons for month, week and day. They, Today and the arrows are links that set ?view= and ?date=, which the calendar reads back; the script follows them without loading the page again. Kept between last month and two months ahead; the server loads what's shown. In the month, a timed event shows its time, a dot in its colour and its title; one that runs past midnight, or all day, is a bar across its days. An event can link to its page. --}}
<x-widget.calendar
    label="Team calendar"
    timezone="Europe/London"
    views="month,week,day"
    :events="$events"
    :min="now()->subMonth()"
    :max="now()->addMonths(2)"
/>
