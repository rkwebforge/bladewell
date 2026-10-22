{{-- For the same menu in every row of a long list or table: render it once as dropdown.menu, and in each row only its trigger, with menu="…" and that row's values. As the menu opens, its :name placeholders (in links, form actions, confirm questions, data-* and text) take the row's values, so route('orders.edit', ':id') becomes that order's link. only-if hides an item for rows where that value is false; it only hides it, so the controller must still authorize. Uses orders.edit and orders.destroy routes from your app. --}}
<ul class="border-line divide-line max-w-md divide-y rounded-2xl border">
    @foreach ($orders as $order)
        <li class="flex items-center justify-between gap-4 p-4">
            <div>
                <p class="font-medium">Order #{{ $order['number'] }}</p>
                <p class="text-foreground/70 text-sm">{{ $order['customer'] }} · {{ $order['total'] }}</p>
            </div>
            <x-widget.dropdown menu="order-actions" :label="'Actions for order #'.$order['number']" :values="['id' => $order['id'], 'number' => $order['number'], 'can_delete' => $order['can_delete']]" />
        </li>
    @endforeach
</ul>

<x-widget.dropdown.menu id="order-actions" align="end">
    <x-widget.dropdown.item :href="route('orders.edit', ':id')" icon="pencil">Edit order #:number</x-widget.dropdown.item>
    <x-widget.dropdown.divider only-if="can_delete" />
    <x-widget.dropdown.item :action="route('orders.destroy', ':id')" method="delete" icon="trash" danger only-if="can_delete" confirm="Delete order #:number?" confirm-message="The order and its invoice are removed for good.">Delete</x-widget.dropdown.item>
</x-widget.dropdown.menu>
