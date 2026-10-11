{{-- Separates groups of items, e.g. everyday actions from a destructive one. --}}
@props([
    // In a shared menu (x-widget.dropdown.menu): hidden with the items it separates, for rows where this value is false.
    'onlyIf' => null,
])

<div role="separator" @if ($onlyIf) data-dropdown-only-if="{{ $onlyIf }}" @endif {{ $attributes->class(['bg-line -mx-1.5 my-1.5 h-px shrink-0']) }}></div>
