{{-- A submit button guards against double submits: once its form submits, it shows its loading state until the next page arrives. --}}
<form method="POST" action="{{ route('profile.update') }}">
    @csrf
    {{-- your fields --}}
    <x-widget.button type="submit">Save changes</x-widget.button>

    {{-- Opt out where the page stays open after submitting, such as a file download. --}}
    <x-widget.button type="submit" formaction="{{ route('profile.export') }}" :submit-guard="false" variant="neutral">Export CSV</x-widget.button>
</form>

{{-- confirm asks first, and only a yes submits. With the modal widget installed it asks in the modal's dialog, in red for a danger button; without it, in the browser's own. --}}
<form method="POST" action="{{ route('orders.destroy', $order) }}">
    @csrf
    @method('DELETE')
    <x-widget.button type="submit" variant="danger" :confirm="'Delete order #'.$order->number.'?'" confirm-message="The order and its invoice are removed for good.">Delete order</x-widget.button>
</form>
