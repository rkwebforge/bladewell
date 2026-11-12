// Load what's shown overlaps, not only what starts in it: a booking from the 28th to the 3rd belongs on both months.
// range() gives the same days the calendar draws, from the same ?date=, view, min and max. Only the person's
// own events, from the query: everything passed to the calendar is in the page.
Route::get('/bookings', function (Request $request) {
    $timezone = $request->user()->timezone ?? config('app.timezone');
    // With views on the calendar, the view is in the address too; anything else is the month, as the calendar has it.
    $view = in_array($request->query('view'), ['month', 'week', 'day'], true) ? $request->query('view') : 'month';
    [$from, $to] = Calendar::range($request->query('date'), $timezone, $view, min: '2026-01', max: now()->addYear());

    $bookings = $request->user()->bookings()
        ->where('starts_at', '<', $to)
        ->where('ends_at', '>=', $from)
        ->with('room')
        ->get();

    return view('bookings.index', ['bookings' => $bookings, 'timezone' => $timezone]);
})->name('bookings.index');

// bookings/index.blade.php: the same min and max as above. The calendar reads ?view= and ?date= itself, as its links set
// it. keys says where each field is on a Booking, so the models go in as they are: a name, a dotted path, or a closure.
// <x-widget.calendar
//     label="Bookings"
//     :timezone="$timezone"
//     views="month,week,day"
//     :events="$bookings"
//     :keys="[
//         'title' => 'room.name',
//         'start' => 'starts_at',
//         'end' => 'ends_at',
//         'href' => fn ($booking) => route('bookings.show', $booking),
//         'status' => fn ($booking) => $booking->confirmed ? null : 'Awaiting confirmation',
//     ]"
//     min="2026-01"
//     :max="now()->addYear()"
// />

// All-day events are dates: keep them in date columns and pass Y-m-d strings (or the date casts), with the end the day
// after the last day. One stored as a moment has to be turned into its date where it happens first
// ($event->starts_at->setTimezone($timezone)->toDateString()): the calendar turns down an all-day date with a time
// rather than guess its day. Overlap is the same test on dates:
// ->where('starts_on', '<', $to->toDateString())->where('ends_on', '>', $from->toDateString())
