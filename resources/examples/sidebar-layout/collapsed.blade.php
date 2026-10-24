{{-- remember keeps the person's choice in a cookie, and collapsed passes it back, so the next page paints with the sidebar as they left it: here, a rail of icons until they open it. With groups="expand", a group's icon opens the sidebar again with that group open, instead of a popover. A link or group without an icon shows its first letter in the rail, like Help. --}}
<div class="border-line h-[30rem] overflow-hidden rounded-2xl border">
    <x-widget.sidebar-layout groups="expand" remember="sidebar" :collapsed="request()->cookie('sidebar', 'collapsed') === 'collapsed'">
        <x-slot:brand>
            <a href="#" class="focus-visible:ring-primary flex items-center gap-3 rounded-lg outline-none focus-visible:ring-2">
                <span aria-hidden="true" class="bg-primary-fill text-on-primary grid size-8 shrink-0 place-items-center rounded-lg text-sm font-bold">A</span>
                <span class="font-semibold">Acme</span>
            </a>
        </x-slot:brand>

        <x-slot:sidebar>
            <x-widget.accordion.menu label="Workspace">
                <x-widget.accordion.menu-item href="#home" icon="home">Home</x-widget.accordion.menu-item>

                <x-widget.accordion variant="menu" title="Billing" icon="credit-card">
                    <x-widget.accordion.menu-item href="#plans">Plans</x-widget.accordion.menu-item>
                    <x-widget.accordion.menu-item href="#invoices" current>Invoices</x-widget.accordion.menu-item>
                </x-widget.accordion>

                <x-widget.accordion.menu-item href="#team" icon="users">Team</x-widget.accordion.menu-item>
                <x-widget.accordion.menu-item href="#help">Help</x-widget.accordion.menu-item>
            </x-widget.accordion.menu>
        </x-slot:sidebar>

        <x-slot:topbar>
            <p class="truncate font-semibold">Invoices</p>
        </x-slot:topbar>

        <div class="border-line text-foreground/70 grid h-[40rem] place-items-center rounded-2xl border-2 border-dashed text-sm">Your page</div>
    </x-widget.sidebar-layout>
</div>
