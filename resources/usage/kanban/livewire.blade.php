{{-- In a Livewire component, leave out move-url and listen for each move with wire:kanban-move: public function move(string $card, string $to, int $position) saves it (ids arrive as strings), and the render that follows draws the board as it now is. To refuse a move, don't make it: that render puts the card back, and the board says so. Each card brings its own wire:key. A card being picked up or dragged stays in hand through a render, and Undo works across one. --}}
<div>
    <x-widget.kanban label="Orders" wire:kanban-move="move($event.detail.card, $event.detail.to, $event.detail.position)">
        @foreach ($stages as $stage)
            <x-widget.kanban.column :id="$stage->value" :title="$stage->label()" :readonly="$stage === Stage::Delivered">
                @foreach ($orders->where('stage', $stage)->sortBy('sort') as $order)
                    <x-widget.kanban.card :id="$order->id" :label="'Order #'.$order->number">
                        <p class="font-medium">Order #{{ $order->number }}</p>
                        <p class="text-muted mt-1">{{ $order->customer_name }}</p>
                    </x-widget.kanban.card>
                @endforeach
            </x-widget.kanban.column>
        @endforeach
    </x-widget.kanban>
</div>
