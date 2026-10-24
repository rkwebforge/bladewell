{{-- The layout usually sits in your app's layout file, around Livewire's page components. Inside a component, a render updates the slots and leaves the sidebar collapsed or open as the person left it. With wire:navigate each page brings the layout again, so pass collapsed from the remember cookie, as here, for the next page to keep the sidebar as it was, and mark the current link from the route. --}}
<x-widget.sidebar-layout remember="sidebar" :collapsed="request()->cookie('sidebar') === 'collapsed'">
    <x-slot:sidebar>
        <x-widget.accordion.menu label="Main">
            <x-widget.accordion.menu-item href="{{ route('dashboard') }}" icon="layout-dashboard" wire:navigate :current="request()->routeIs('dashboard')">Dashboard</x-widget.accordion.menu-item>
            <x-widget.accordion.menu-item href="{{ route('orders.index') }}" icon="shopping-bag" wire:navigate :current="request()->routeIs('orders.*')">Orders</x-widget.accordion.menu-item>
        </x-widget.accordion.menu>
    </x-slot:sidebar>

    {{ $slot }}
</x-widget.sidebar-layout>
