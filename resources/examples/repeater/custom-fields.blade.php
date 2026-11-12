{{-- Pairs of a name and a value: product details, metadata, custom fields. Compact rows keep a long list short. On the server, distinct on the names stops the same one being sent twice. With every row removed, the empty slot says what the list is for. --}}
<form class="max-w-2xl">
    <x-widget.repeater name="details" label="Product details" :max="30" add-label="Add detail" row-label="Detail" compact>
        @foreach ((old() ? old('details', []) : $details) + ['__row__' => []] as $key => $detail)
            <x-widget.repeater.row :key="$key">
                <x-widget.text-input name="details[{{ $key }}][name]" label="Name" placeholder="Material" :value="$detail['name'] ?? null" required />
                <x-widget.text-input name="details[{{ $key }}][value]" label="Value" placeholder="Solid oak" :value="$detail['value'] ?? null" required />
            </x-widget.repeater.row>
        @endforeach
        <x-slot:empty>No details yet. Add material, size or anything else a buyer asks about.</x-slot:empty>
    </x-widget.repeater>
</form>
