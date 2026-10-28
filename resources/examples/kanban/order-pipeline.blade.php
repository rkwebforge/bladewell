{{-- Orders by stage. Move one with its menu: the board shows it at once and sends it to your route, which keeps it or sends it back with a reason. Delivered is readonly: only the courier's confirmation puts an order there. --}}
<x-widget.kanban label="Orders" :move-url="route('orders.move')">
    @foreach ($stages as $stage => $title)
        <x-widget.kanban.column :id="$stage" :title="$title" empty="No orders" :readonly="$stage === 'delivered'">
            @foreach ($orders[$stage] ?? [] as $order)
                <x-widget.kanban.card :id="$order->id" :label="'Order #'.$order->id">
                    <p class="font-medium">Order #{{ $order->id }}</p>
                    <p class="text-muted mt-1 flex justify-between gap-2">
                        <span class="truncate">{{ $order->customer }}</span>
                        <span class="tabular-nums">{{ $order->total }}</span>
                    </p>
                </x-widget.kanban.card>
            @endforeach
        </x-widget.kanban.column>
    @endforeach
</x-widget.kanban>
