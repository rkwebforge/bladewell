{{-- Your own script can follow the rows: here a total that keeps up as quantities and prices change, and as lines go, through the repeater:remove event. It's for the person filling the form in. The server works the total out again from the lines it gets, never from the page. --}}
@php($rows = old() ? old('items', []) : $items)
<form class="max-w-2xl" data-invoice>
    <x-widget.repeater name="items" label="Invoice items" :min="1" :max="50" add-label="Add item" row-label="Item">
        @foreach ($rows + ['__row__' => []] as $key => $item)
            <x-widget.repeater.row :key="$key">
                <x-widget.text-input name="items[{{ $key }}][description]" label="Description" :value="$item['description'] ?? null" required class="sm:col-span-2" />
                <x-widget.number name="items[{{ $key }}][quantity]" label="Quantity" :decimals="0" :min="1" :value="$item['quantity'] ?? 1" stepper />
                <x-widget.number name="items[{{ $key }}][price]" label="Unit price" prefix="$" :min="0" :value="$item['price'] ?? null" />
            </x-widget.repeater.row>
        @endforeach
    </x-widget.repeater>

    <p class="text-foreground border-line mt-6 flex justify-between border-t pt-4 text-base">
        <span>Total</span>
        <output data-invoice-total class="font-medium tabular-nums">${{ number_format(collect($rows)->sum(fn ($item) => (float) ($item['quantity'] ?? 0) * (float) ($item['price'] ?? 0)), 2) }}</output>
    </p>
</form>
