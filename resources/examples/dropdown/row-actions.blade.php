{{-- Actions for one record. With no trigger it's an icon-only button, so it needs a label naming the record. Edit is a link, Copy is a plain button for your own script (beside this), and Delete is a form that sends DELETE with its CSRF token once confirm has been answered. Uses orders.edit and orders.destroy routes from your app. --}}
<div class="border-line flex max-w-md items-center justify-between gap-4 rounded-2xl border p-4">
    <div>
        <p class="font-medium">Order #1042</p>
        <p class="text-foreground/70 text-sm">Ana Silva · $129.00</p>
    </div>
    <x-widget.dropdown label="Actions for order #1042" align="end">
        <x-widget.dropdown.item :href="route('orders.edit', 1042)" icon="pencil">Edit</x-widget.dropdown.item>
        <x-widget.dropdown.item icon="copy" data-clipboard="1042">Copy order number</x-widget.dropdown.item>
        <x-widget.dropdown.divider />
        <x-widget.dropdown.item :action="route('orders.destroy', 1042)" method="delete" icon="trash" danger confirm="Delete order #1042?" confirm-message="The order and its invoice are removed for good.">Delete</x-widget.dropdown.item>
    </x-widget.dropdown>
</div>
