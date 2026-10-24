{{-- The panel slot: a chat (or notifications, or help) one click away. Its button sits at the end of the top bar; on a large screen the panel opens beside the page, which narrows and can still be used, and on a phone or tablet it slides over the page like the menu. Esc or its close button shuts it. It's as wide as the sidebar, and compact on the chat sizes its text to match. panel-label and panel-icon name and draw its button. --}}
<div class="border-line h-[36rem] overflow-hidden rounded-2xl border">
    <x-widget.sidebar-layout panel-label="Chat" panel-icon="message-circle">
        <x-slot:brand>
            <a href="#" class="focus-visible:ring-primary flex items-center gap-3 rounded-lg outline-none focus-visible:ring-2">
                <span aria-hidden="true" class="bg-primary-fill text-on-primary grid size-8 shrink-0 place-items-center rounded-lg text-sm font-bold">A</span>
                <span class="font-semibold">Acme</span>
            </a>
        </x-slot:brand>

        <x-slot:sidebar>
            <x-widget.accordion.menu label="Main">
                <x-widget.accordion.menu-item href="#overview" icon="layout-dashboard" current>Dashboard</x-widget.accordion.menu-item>
                <x-widget.accordion.menu-item href="#orders" icon="shopping-bag">Orders</x-widget.accordion.menu-item>
                <x-widget.accordion.menu-item href="#customers" icon="users">Customers</x-widget.accordion.menu-item>
                <x-widget.accordion.menu-item href="#settings" icon="settings">Settings</x-widget.accordion.menu-item>
            </x-widget.accordion.menu>
        </x-slot:sidebar>

        <x-slot:topbar>
            <p class="truncate font-semibold">Dashboard</p>
            <button type="button" aria-label="Notifications" class="focus-visible:ring-primary text-foreground/70 hover:bg-field hover:text-foreground ms-auto grid size-9 shrink-0 place-items-center rounded-lg outline-none focus-visible:ring-2">
                <x-widget.icon name="bell" class="size-5" />
            </button>
        </x-slot:topbar>

        <x-slot:panel>
            <x-widget.chat variant="messages" label="Chat with Acme Support" compact class="min-h-0 flex-1">
                <x-widget.chat.divider>Today</x-widget.chat.divider>
                <x-widget.chat.message name="Sam from Support" :time="today()->setTime(9, 12)">Hi! Anything I can help you with today?</x-widget.chat.message>
                <x-widget.chat.message mine :time="today()->setTime(9, 14)" status="read">Where do I change our billing email?</x-widget.chat.message>
                <x-widget.chat.message name="Sam from Support" :time="today()->setTime(9, 15)">
                    Under Settings, then Billing. It updates on your next invoice. Here's a short guide:
                    <x-slot:attachments>
                        <x-widget.chat.attachment href="#billing-guide" name="billing-settings.pdf" :size="84_000" />
                    </x-slot:attachments>
                </x-widget.chat.message>
                <x-widget.chat.typing name="Sam" />

                <x-slot:composer>
                    <x-widget.chat.composer async :action="route('conversations.messages.store', 1)" maxlength="2000" />
                </x-slot:composer>
            </x-widget.chat>
        </x-slot:panel>

        <div class="border-line text-foreground/70 grid h-[40rem] place-items-center rounded-2xl border-2 border-dashed text-sm">Your page</div>
    </x-widget.sidebar-layout>
</div>
