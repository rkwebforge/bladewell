{{-- Its view: the actions name the component's methods, and each row's fields bind with wire:model under its key, no name needed. Rows come from the property, with no placeholder row: the component adds them. The script keeps focus on the row that moved, or where a removed one was, after each render, and reads the change out. --}}
<form wire:submit="save">
    <x-widget.repeater name="lines" label="Order lines" :min="1" :max="10" add-label="Add line" row-label="Line" add-action="addLine" remove-action="removeLine" move-action="moveLine">
        @foreach ($lines as $key => $line)
            <x-widget.repeater.row :key="$key">
                <x-widget.text-input wire:model="lines.{{ $key }}.product" label="Product" required />
                <x-widget.number wire:model="lines.{{ $key }}.quantity" label="Quantity" :decimals="0" :min="1" stepper />
            </x-widget.repeater.row>
        @endforeach
    </x-widget.repeater>

    <x-widget.button type="submit" class="mt-6">Save order</x-widget.button>
</form>
