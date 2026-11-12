{{-- In a Livewire component, bind the order with wire:model to an array property, public array $order = [], filled with the ids in mount(). Each move sets it, and the list is drawn from it, so a render keeps the person's order. To save each move as it happens, listen with wire:sortable-move instead: public function reorder(array $order) saves it, and the render that follows draws the list as it now is; to refuse a move, don't make it, and that render puts the item back, saying so. Each item brings its own wire:key, and focus stays on the handle or button that moved it. --}}
<div>
    <x-widget.sortable label="FAQs" wire:sortable-move="reorder($event.detail.order)">
        @foreach ($faqs as $faq)
            <x-widget.sortable.item :id="$faq->id" :label="$faq->question">{{ $faq->question }}</x-widget.sortable.item>
        @endforeach
    </x-widget.sortable>
</div>
