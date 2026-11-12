{{-- Any field works in a row, including those with their own script and popups: a select and a date picker here. A copied row gets its own ids, so every label, list and calendar points at the row it's in. --}}
<form class="max-w-2xl">
    <x-widget.repeater name="travellers" label="Travellers" :min="1" :max="6" add-label="Add traveller" row-label="Traveller">
        @foreach ((old() ? old('travellers', []) : $travellers) + ['__row__' => []] as $key => $traveller)
            <x-widget.repeater.row :key="$key">
                <x-widget.text-input name="travellers[{{ $key }}][name]" label="Full name" :value="$traveller['name'] ?? null" required />
                <x-widget.datepicker name="travellers[{{ $key }}][born_on]" label="Date of birth" :value="$traveller['born_on'] ?? null" birthday />
                <x-widget.select name="travellers[{{ $key }}][meal]" label="Meal" :options="['standard' => 'Standard', 'vegetarian' => 'Vegetarian', 'vegan' => 'Vegan', 'gluten_free' => 'Gluten free']" :value="$traveller['meal'] ?? 'standard'" />
            </x-widget.repeater.row>
        @endforeach
    </x-widget.repeater>
</form>
