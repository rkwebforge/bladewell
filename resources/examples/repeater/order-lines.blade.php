{{-- Each row is keyed, by the record's id or by the key a new row gets in the browser, never by its position. Its fields are named lines[{key}][…], so old input and errors stay with the row after adding, removing or moving. After a failed submit the rows come from the old input, even none: with every row removed, the form sends no lines, and old('lines', $lines) would bring the saved ones back. The last pass of the loop, under the placeholder key, is the template new rows are copied from. Resetting the form puts its values back but keeps the rows added or removed since. --}}
<form class="max-w-2xl">
    <x-widget.repeater name="lines" label="Order lines" :min="1" :max="10" add-label="Add line" row-label="Line">
        @foreach ((old() ? old('lines', []) : $lines) + ['__row__' => []] as $key => $line)
            <x-widget.repeater.row :key="$key">
                <x-widget.text-input name="lines[{{ $key }}][product]" label="Product" :value="$line['product'] ?? null" required />
                <x-widget.number name="lines[{{ $key }}][quantity]" label="Quantity" :decimals="0" :min="1" :value="$line['quantity'] ?? 1" stepper />
            </x-widget.repeater.row>
        @endforeach
    </x-widget.repeater>
</form>
