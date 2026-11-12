{{-- With a name, the order is submitted with its form, as links[]: the ids, first to last. Nothing is saved until Save, so there's no move-url. Fields inside the items move with them, keeping what's typed. If the form comes back with an error (clear a label and save), the list keeps the order that was sent. --}}
<form method="POST" action="{{ route('menus.update', $menu) }}" class="grid max-w-xl gap-4">
    @csrf
    @method('PUT')
    <x-widget.sortable label="Main menu" name="links" info="The menu shows the links in this order.">
        @foreach ($links as $link)
            <x-widget.sortable.item :id="$link->id" :label="$link->title">
                <x-widget.text-input :name="'titles['.$link->id.']'" label="Label" :value="$link->title" :info="'Links to '.$link->url" required />
            </x-widget.sortable.item>
        @endforeach
    </x-widget.sortable>
    <div>
        <x-widget.button type="submit">Save menu</x-widget.button>
    </div>
</form>
