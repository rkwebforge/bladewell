{{-- Inside a Livewire component, an item takes wire:click like any button. confirm="…" asks first and only then runs the action, and a disabled item never runs it. A render while the menu is open leaves it open and in place, with its items updated. Choosing an item closes the menu and puts focus back on the trigger. A shared dropdown.menu fills its :name placeholders into links, form actions and data-*, never into wire:click, so keep a menu per row, as here, for items that call the component. --}}
@foreach ($orders as $order)
    <div wire:key="order-{{ $order->id }}" class="flex items-center justify-between">
        <span>#{{ $order->number }}</span>
        <x-widget.dropdown :label="'Actions for order #'.$order->number" align="end">
            <x-widget.dropdown.item icon="copy" wire:click="duplicate({{ $order->id }})">Duplicate</x-widget.dropdown.item>
            <x-widget.dropdown.item icon="lock" wire:click="lock({{ $order->id }})" :disabled="$order->locked">Lock</x-widget.dropdown.item>
            <x-widget.dropdown.divider />
            <x-widget.dropdown.item icon="trash" danger wire:click="delete({{ $order->id }})" :confirm="'Delete order #'.$order->number.'?'">Delete</x-widget.dropdown.item>
        </x-widget.dropdown>
    </div>
@endforeach
