{{-- The trigger slot takes your own markup, such as an avatar and a name. Other content can sit between the items, like who is signed in, but screen readers only move between items inside a menu, so say it in the trigger's label too. Sign out is a POST form. Uses profile.edit and logout routes from your app. --}}
<x-widget.dropdown align="end" label="Account: Ana Silva, ana@example.com">
    <x-slot:trigger class="hover:bg-field py-1.5 ps-1.5 pe-3">
        <span aria-hidden="true" class="bg-primary/10 text-primary grid size-8 place-items-center rounded-full text-xs font-semibold">AS</span>
        <span class="text-sm font-medium">Ana Silva</span>
        <x-widget.icon name="chevron-down" class="size-4 opacity-60" />
    </x-slot:trigger>

    <div aria-hidden="true" class="px-3 pt-1.5 pb-2">
        <p class="text-foreground/70 text-xs">Signed in as</p>
        <p class="truncate font-medium">ana@example.com</p>
    </div>
    <x-widget.dropdown.divider />
    <x-widget.dropdown.item :href="route('profile.edit')" icon="user">Profile</x-widget.dropdown.item>
    <x-widget.dropdown.item :action="route('logout')" icon="log-out">Sign out</x-widget.dropdown.item>
</x-widget.dropdown>
