{{-- In a Livewire component, bind with wire:model.live (add .debounce.300ms to wait for a pause in typing); no name is needed. The box shows the property after every render, so clearing it in PHP empties it too, and the × button empties the property as if the text had been deleted. Filter in render(), with % and _ matching themselves and case ignored on every database: ->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->search).'%']). --}}
<div class="space-y-4">
    <x-widget.search placeholder="Search customers" shortcut="/" wire:model.live.debounce.300ms="search" />

    <ul>
        @foreach ($customers as $customer)
            <li wire:key="{{ $customer->id }}">{{ $customer->name }}</li>
        @endforeach
    </ul>
</div>
