{{-- Around anything larger: a card, an image, a region of a map. It follows the pointer anywhere over it, and keeps to the window, moving to the other side of the pointer near its right and bottom edges. Around a link or a button, keyboard focus shows it under the element. --}}
<x-widget.tooltip-cursor text="Click to see the full-size photo">
    <a href="#photo" class="bg-field text-foreground/70 focus-visible:ring-primary grid h-40 w-72 place-items-center rounded-2xl outline-none focus-visible:ring-2">
        <x-widget.icon name="image" class="size-8" />
        <span class="sr-only">Photo</span>
    </a>
</x-widget.tooltip-cursor>
