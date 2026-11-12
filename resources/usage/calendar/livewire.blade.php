{{-- In a Livewire component, keep the date in a property (#[Url] public string $date = '';, so it stays in the address) and listen for the links with wire:calendar-navigate: the calendar leaves the page where it is, and the render that follows draws the new period. url gives the links a real address too, for opening one in a new tab. Load the events for $date with Calendar::range(), as without Livewire. The today mark stays on the viewer's today through every render. --}}
<x-widget.calendar
    label="Bookings"
    :timezone="$timezone"
    :events="$this->bookings"
    :date="$date"
    :url="route('bookings.index', ['date' => '{date}'])"
    wire:calendar-navigate="$set('date', $event.detail.date)"
/>
