{{-- Its view: wire:model.live binds the builder's one field to the property, so each completed row re-renders the table. The rows themselves are the browser's (wire:ignore), so a render never resets what's being typed. --}}
<x-widget.filter-builder :state="$state" wire:model.live="filters" label="Filter orders" />

<x-widget.table caption="Orders" :rows="$orders" :columns="[['label' => 'Order'], ['label' => 'Status'], ['label' => 'Amount', 'align' => 'end']]">
    @foreach ($orders as $order)
        <x-widget.table.row>
            <td>{{ $order->number }}</td>
            <td>{{ $order->status }}</td>
            <td>{{ $order->total }}</td>
        </x-widget.table.row>
    @endforeach
</x-widget.table>
