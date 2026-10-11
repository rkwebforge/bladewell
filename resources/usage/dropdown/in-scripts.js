// window.dropdown is available once the dropdown script has loaded.
// <x-widget.dropdown id="order-1042-actions" label="Actions for order #1042">…</x-widget.dropdown>
dropdown.open('order-1042-actions');
dropdown.close('order-1042-actions');
dropdown.toggle('order-1042-actions');

// Or the trigger element. A shared menu (x-widget.dropdown.menu) opens for a row, so give that row's trigger.
dropdown.open(document.querySelector('[data-order="1042"] [data-dropdown-trigger]'));
