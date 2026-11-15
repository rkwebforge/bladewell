@props([
    // Where it goes. Defaults to the previous page.
    'href' => null,
    // Text beside the arrow. Without it, just the arrow shows, named "Back" for screen readers.
    'label' => null,
])

<a
    href="{{ $href ?? url()->previous() }}"
    {{ $attributes->class(['text-foreground focus-visible:ring-primary inline-flex max-w-fit items-center gap-1.5 rounded-md text-sm outline-none hover:opacity-80 transition-opacity duration-200 ease-out motion-reduce:transition-none focus-visible:ring-2']) }}
>
    {{-- Points back, which is right in right-to-left pages. --}}
    <x-widget.icon name="arrow-left" class="size-4 rtl:-scale-x-100" />

    @if ($label)
        <span>{{ $label }}</span>
    @else
        <span class="sr-only">Back</span>
    @endif
</a><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
