{{-- In a table, give each row's trigger a label that names the row, so a screen reader's list of buttons isn't twenty "Actions". Hiding an item with @can is only for looks: the controller must still authorize the request itself. In a long table, render the menu once instead and give each row only its trigger: see the shared menu example, where only-if takes the place of @can. --}}
@foreach ($orders as $order)
    <tr>
        <td>#{{ $order->number }}</td>
        <td>{{ $order->customer }}</td>
        <td class="text-end">
            <x-widget.dropdown :label="'Actions for order #'.$order->number" align="end">
                <x-widget.dropdown.item :href="route('orders.edit', $order)" icon="pencil">Edit</x-widget.dropdown.item>
                @can('delete', $order)
                    <x-widget.dropdown.divider />
                    <x-widget.dropdown.item :action="route('orders.destroy', $order)" method="delete" icon="trash" danger :confirm="'Delete order #'.$order->number.'?'">Delete</x-widget.dropdown.item>
                @endcan
            </x-widget.dropdown>
        </td>
    </tr>
@endforeach
