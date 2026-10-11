@props([
    // Unique on the page: each row's dropdown opens the menu by it, as its menu="…".
    'id' => null,
    // Which edge of the trigger that opened it the menu lines up with: start (left in left-to-right pages) or end.
    'align' => 'start',
])

@php
    if (! is_string($id) || $id === '') {
        throw new \InvalidArgumentException('<x-widget.dropdown.menu> needs an id: each row\'s <x-widget.dropdown menu="…"> opens it by that.');
    }
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id, explicit: true);
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($align, ['start', 'end'], true)) {
        throw new \InvalidArgumentException("Unknown align [{$align}] for <x-widget.dropdown.menu>. Use one of: start, end.");
    }
@endphp

{{--
    One menu for many triggers, such as a row menu in a long table: each row renders only its trigger
    (<x-widget.dropdown menu="…" :values="…">). As it opens, resources/js/widget/dropdown fills the items'
    :name placeholders (in href, action, title, aria-label, data-* and text) from the trigger's values,
    hides items whose only-if value is false, and names the menu after that trigger. It looks and behaves
    like the menu inside <x-widget.dropdown>; keep the two in step.
--}}
<div
    id="{{ $id }}"
    popover
    role="menu"
    {{-- The script positions it and names it after the trigger that opened it; a Livewire render would wipe that. --}}
    wire:ignore.self
    data-dropdown-menu
    data-dropdown-shared
    data-align="{{ $align }}"
    {{ $attributes->class([
        // hidden until open: a display utility on a popover beats the browser's own rule that hides it while closed, and
        // the closed menu would sit there invisible, catching clicks meant for what's under it.
        'border-line bg-surface text-foreground fixed inset-auto m-0 hidden open:flex max-h-[min(24rem,calc(100dvh-2rem))] min-w-48 max-w-[calc(100vw-1rem)] flex-col overflow-y-auto overscroll-contain rounded-2xl border p-1.5 text-sm shadow-lg',
        // Fades and grows in from the trigger's side (data-side, set by the script when it flips above).
        'origin-top opacity-0 scale-95 transition-[opacity,scale,display,overlay] transition-discrete duration-150 open:opacity-100 open:scale-100 starting:open:opacity-0 starting:open:scale-95 data-[side=top]:origin-bottom motion-reduce:transition-none',
    ]) }}
>
    {{ $slot }}
</div><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>