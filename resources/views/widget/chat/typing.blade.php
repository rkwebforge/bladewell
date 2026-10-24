@aware([
    'compact' => false,
])

@props([
    // Who's typing, read out by screen readers ("Sam is typing").
    'name' => null,
])

{{-- Three dots that rise in turn, while the other side writes. Read out once, as it appears; put it after the last
     message, and take it away when their message arrives. --}}
<div data-chat-typing role="status" {{ $attributes->class(['flex items-center gap-3']) }}>
    <span aria-hidden="true" @class(['bg-field flex items-center gap-1 rounded-2xl rounded-ss-md', $compact ? 'px-3 py-2.5' : 'px-3.5 py-3'])>
        @foreach (['[animation-delay:-0.3s]', '[animation-delay:-0.15s]', ''] as $delay)
            <span class="bg-foreground/50 size-1.5 animate-bounce rounded-full motion-reduce:animate-none {{ $delay }}"></span>
        @endforeach
    </span>
    <span class="sr-only">{{ $name ? $name.' is typing' : 'Typing' }}</span>
</div>
