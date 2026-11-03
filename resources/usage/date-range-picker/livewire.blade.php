{{-- In a Livewire component, bind with wire:model to an array with start and end keys, the shape name="period" submits: public array $period = ['start' => null, 'end' => null]. Both ends update together (wire:model.live sends them in one request), the field shows the property after every render, and a range set or cleared in PHP shows too. Errors are read under period.start and period.end, as the rules below report them. --}}
<div>
    <x-widget.date-range-picker label="Report period" wire:model.live="period" :max="false" />

    {{-- In the component, in the Y-m-d the field sends (a request can send any two dates), the end not before the start.
         For a range in the past, add new \App\Rules\NotAfterToday to both:
         $this->validate(['period.start' => ['required', 'date_format:Y-m-d'], 'period.end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period.start']]); --}}
</div>
