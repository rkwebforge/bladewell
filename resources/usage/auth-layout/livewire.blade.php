{{-- The layout has no script, so inside a Livewire component it renders like any other markup: bind the fields with wire:model and submit with wire:submit. Errors from $this->validate() show under each field. --}}
<x-widget.auth-layout title="Welcome back" subtitle="Sign in to your account.">
    <form wire:submit="login" class="flex flex-col gap-5">
        <x-widget.text-input wire:model="email" type="email" label="Email" autocomplete="username" required />
        <x-widget.password wire:model="password" label="Password" required />
        <x-widget.checkbox wire:model="remember" label="Remember me" />
        <x-widget.button type="submit" class="w-full">Sign in</x-widget.button>
    </form>
</x-widget.auth-layout>
