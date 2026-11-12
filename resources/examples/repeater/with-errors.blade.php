{{-- After a failed submit, each error is filed under its row's key (lines.15.quantity), so it shows on that row wherever the row has moved. An error about the rows as a whole (lines: max) shows under them and goes once a row is added or removed. --}}
<form class="max-w-2xl">
    <x-widget.repeater name="lines" label="Order lines" :min="1" :max="3" add-label="Add line" row-label="Line">
        @foreach ((old() ? old('lines', []) : $lines) + ['__row__' => []] as $key => $line)
            <x-widget.repeater.row :key="$key">
                <x-widget.text-input name="lines[{{ $key }}][product]" label="Product" :value="$line['product'] ?? null" required />
                <x-widget.number name="lines[{{ $key }}][quantity]" label="Quantity" :decimals="0" :min="1" :value="$line['quantity'] ?? 1" stepper />
            </x-widget.repeater.row>
        @endforeach
    </x-widget.repeater>
</form>
