{{-- An admin frame around a blank page. On a large screen, the button at the start of the top bar collapses the sidebar to its icons; hover or focus one for its name. A group's icon shows its links in a popover beside the rail, scrolling once they pass 18rem, like Reports; Esc or a click elsewhere closes it. On a phone or tablet, the menu button slides it in; Esc, the close button, the dimmed page or a link closes it. In your layout, leave out the box around it: it fills the window. --}}
<div class="border-line h-[36rem] overflow-hidden rounded-2xl border">
    <x-widget.sidebar-layout>
        <x-slot:brand>
            <a href="#" class="focus-visible:ring-primary flex items-center gap-3 rounded-lg outline-none focus-visible:ring-2">
                <span aria-hidden="true" class="bg-primary-fill text-on-primary grid size-8 shrink-0 place-items-center rounded-lg text-sm font-bold">A</span>
                <span class="font-semibold">Acme</span>
            </a>
        </x-slot:brand>

        <x-slot:sidebar>
            <x-widget.accordion.menu label="Main">
                <x-widget.accordion.menu-item href="#dashboard" icon="layout-dashboard" current>Dashboard</x-widget.accordion.menu-item>
                <x-widget.accordion.menu-item href="#inbox" icon="inbox">Inbox</x-widget.accordion.menu-item>

                <x-widget.accordion variant="menu" title="Orders" icon="shopping-bag">
                    <x-widget.accordion.menu-item href="#orders">All orders</x-widget.accordion.menu-item>
                    <x-widget.accordion.menu-item href="#drafts">Drafts</x-widget.accordion.menu-item>
                    <x-widget.accordion.menu-item href="#returns">Returns</x-widget.accordion.menu-item>
                </x-widget.accordion>

                <x-widget.accordion variant="menu" title="Products" icon="package" :open="false">
                    <x-widget.accordion.menu-item href="#catalogue">Catalogue</x-widget.accordion.menu-item>
                    <x-widget.accordion.menu-item href="#inventory">Inventory</x-widget.accordion.menu-item>
                </x-widget.accordion>

                <x-widget.accordion.menu-item href="#customers" icon="users">Customers</x-widget.accordion.menu-item>
                <x-widget.accordion variant="menu" title="Reports" icon="chart-bar" :open="false">
                    @foreach (['Sales', 'Revenue', 'Refunds', 'Payouts', 'Taxes', 'Traffic', 'Conversion', 'Retention', 'Stock', 'Discounts'] as $report)
                        <x-widget.accordion.menu-item :href="'#'.strtolower($report)">{{ $report }}</x-widget.accordion.menu-item>
                    @endforeach
                </x-widget.accordion>

                <x-widget.accordion.menu-item href="#settings" icon="settings">Settings</x-widget.accordion.menu-item>
            </x-widget.accordion.menu>
        </x-slot:sidebar>

        <x-slot:footer>
            <div class="flex items-center gap-3">
                <span aria-hidden="true" class="bg-field grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold">JD</span>
                <span class="flex min-w-0 flex-col text-sm leading-tight">
                    <span class="truncate font-medium">Jane Doe</span>
                    <span class="text-foreground/70 truncate text-xs">jane@acme.test</span>
                </span>
            </div>
        </x-slot:footer>

        <x-slot:topbar>
            <p class="truncate font-semibold">Dashboard</p>
            <button type="button" aria-label="Notifications" class="focus-visible:ring-primary text-foreground/70 hover:bg-field hover:text-foreground ms-auto grid size-9 shrink-0 place-items-center rounded-lg outline-none focus-visible:ring-2">
                <x-widget.icon name="bell" class="size-5" />
            </button>
        </x-slot:topbar>

        <div class="border-line text-foreground/70 grid h-[48rem] place-items-center rounded-2xl border-2 border-dashed text-sm">Your page</div>
    </x-widget.sidebar-layout>
</div>
