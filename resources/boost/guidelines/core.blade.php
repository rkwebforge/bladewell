@verbatim
# Bladewell

Bladewell widgets are Blade components, each with a small vanilla JS module, copied into this app by `php artisan bladewell:add`. The copied files belong to the app.

- Build UI from `<x-widget.*>` components before writing markup of your own. Find one with `php artisan bladewell:list`, or the `bladewell` MCP server's `list_components` and `get_component` tools, which give a widget's props, examples and usage notes (such as the endpoint a search or tree calls).
- Add a widget with `php artisan bladewell:add {name}`: it also copies what the widget needs and records it in `bladewell.lock`. Never copy widget files by hand. `php artisan bladewell:add --installed` updates them, but leaves alone any file that was edited, so prefer props, slots and classes over editing a widget, and don't reformat its files.
- Widget JavaScript hooks onto `data-*` attributes and works under a strict Content Security Policy: don't add Alpine, jQuery, inline `<script>`, `onclick=""` or `style=""`.
- Form controls take `name` (or `wire:model`), `label` and `info`, and show the validation error for their name themselves: don't add `@error` markup. Use `readonly`, not `disabled`, for a value that is shown and submitted but can't be changed; a disabled control isn't submitted.
- The URLs a widget calls (`suggest-url`, `search-url`, `children-url`, `upload-url`) are named routes in `routes/web.php`, not `routes/api.php`: they share the page's sign-in and CSRF protection, so they can check who is asking. Throttle the ones that run as people type, and authorize as for any page.
- For "Are you sure?", put `confirm="…"` on `<x-widget.button>` or `<x-widget.dropdown.item>` rather than writing a script.
- Toasts: `return back()->with('success', 'Saved.')` from a controller, `$this->dispatch('toast', type: 'success', message: 'Saved.')` from Livewire, `data-toast="success" data-toast-message="Saved."` on a clickable element, or `toast.success('Saved.')` in a script. The layout needs `<x-widget.toast />` once.
- In a long table, render the row menu once, `<x-widget.dropdown.menu id="order-actions">`, and give each row only its trigger, `<x-widget.dropdown menu="order-actions" :values="['id' => $order->id]" />`, with `:id` placeholders in the menu's links: `route('orders.edit', ':id')`.
- Scripts reach widgets through `window.modal`, `window.toast`, `window.dropdown`, `window.popover`, `window.tabs` and the others, by id or element.
- The date picker submits `Y-m-d` and stops at today unless given `:max="false"`. Validate past dates with `new \App\Rules\NotAfterToday`, not `before_or_equal:today`, which uses the server's UTC date.
@endverbatim
