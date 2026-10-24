{{-- A line between days, or before what's unread: the slot is what it says ("Today", "New"). --}}
<div {{ $attributes->class(['text-foreground/70 flex items-center gap-3 text-xs font-medium']) }}>
    <span aria-hidden="true" class="bg-line h-px flex-1"></span>
    <span>{{ $slot }}</span>
    <span aria-hidden="true" class="bg-line h-px flex-1"></span>
</div>
