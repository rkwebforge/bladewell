{{-- layout="grid" lays the items out as tiles that wrap, for a product's photos. They read in rows, so a drag places a tile among them by row, the arrow keys move it along and between rows, and its buttons move it earlier or later. Anything else in a tile is yours, like this remove button: the list keeps up as tiles come and go, and the empty slot shows once none are left. --}}
<x-widget.sortable label="Product photos" name="photos" layout="grid" info="The first photo is the one shown in the shop.">
    @foreach ($photos as $photo)
        <x-widget.sortable.item :id="$photo->id" :label="$photo->alt">
            <div class="relative">
                <img src="{{ $photo->url }}" alt="{{ $photo->alt }}" class="block aspect-square w-full rounded-lg object-cover">
                <div class="absolute end-2 top-2">
                    <x-widget.button variant="neutral" size="sm" icon="trash" :label="'Remove '.$photo->alt" class="shadow-xs" data-photo-remove />
                </div>
            </div>
        </x-widget.sortable.item>
    @endforeach
    <x-slot:empty>No photos yet. The shop shows a placeholder until you add one.</x-slot:empty>
</x-widget.sortable>
