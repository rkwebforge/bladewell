{{-- In a table's filters slot, the table filters in place as rows are completed, and the URL keeps them, so a reload or a shared link shows the same orders. Pick a field, then a condition its type offers: contains for text, more than or between for amounts, before or in the last N days for dates, any of for a status. Filters::read() checks the request against the same list of fields and says why it turned anything down; the Usage shows it on an Eloquent query. --}}
<x-widget.table
    id="filtered-orders"
    caption="Orders"
    :rows="$orders"
    pagination="numbers"
    empty="No orders match these filters"
    :columns="[
        ['label' => 'Order'],
        ['label' => 'Customer'],
        ['label' => 'Status'],
        ['label' => 'Placed'],
        ['label' => 'Amount', 'align' => 'end'],
    ]"
>
    <x-slot:filters>
        <x-widget.filter-builder :state="$state" name="order_filters" label="Filter orders" />
    </x-slot:filters>

    @foreach ($orders as $order)
        <x-widget.table.row>
            <td>{{ $order['number'] }}</td>
            <td>{{ $order['customer'] }}</td>
            <td>{{ $order['status_label'] }}</td>
            <td>{{ $order['placed_label'] }}</td>
            <td>{{ $order['amount_label'] }}</td>
        </x-widget.table.row>
    @endforeach
</x-widget.table>
