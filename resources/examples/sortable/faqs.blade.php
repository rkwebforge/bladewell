{{-- Questions in the order your help page shows them. Drag one by its handle, pick it up with Space and place it with the arrow keys, or use its Move buttons: the list moves at once and sends each move to your route, which keeps it or sends it back with a reason. Here the route keeps the welcome question first. --}}
<x-widget.sortable label="FAQs" :move-url="route('faqs.reorder')">
    @foreach ($faqs as $faq)
        <x-widget.sortable.item :id="$faq->id" :label="$faq->question">
            <p class="font-medium">{{ $faq->question }}</p>
            <p class="text-muted mt-0.5 truncate">{{ $faq->answer }}</p>
        </x-widget.sortable.item>
    @endforeach
</x-widget.sortable>
