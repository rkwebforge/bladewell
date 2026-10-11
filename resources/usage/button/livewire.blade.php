{{-- In a wire:submit form, Livewire sends the form itself and holds the submit button until the answer comes back: the button shows its loading state meanwhile, keeps its colour, and gets focus back afterwards if it had it. A wire:click button shows it with wire:loading.attr="aria-busy" (and wire:target, so other requests don't set it off); a quick toggle is better without. :loading set from a property works too. confirm="…" asks before wire:click runs, which then only ever sees a confirmed click. --}}
<form wire:submit="save" class="space-y-5">
    {{-- your fields --}}
    <x-widget.button type="submit">Save changes</x-widget.button>
</form>

<x-widget.button variant="neutral" wire:click="export" wire:loading.attr="aria-busy" wire:target="export">Export CSV</x-widget.button>

<x-widget.button variant="danger" :loading="$deleting" wire:click="delete">Delete</x-widget.button>
