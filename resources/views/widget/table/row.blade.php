@props([
    // Makes the whole row a link to this URL: a click anywhere on it goes there (Ctrl/Cmd or middle click opens a new
    // tab), and its first cell gets a real link for keyboards and screen readers. Not with a details slot.
    'href' => null,
    // With a selectable table: the value this row's checkbox submits, e.g. its id.
    'value' => null,
    // Screen reader name for the checkbox, e.g. "Select TX-00042".
    'selectLabel' => 'Select row',
    // With href: the name of the row's link, e.g. "TX-00042". Defaults to the first cell's text.
    'linkLabel' => null,
])

{{-- The table's own props, so a row knows whether it needs a checkbox and which form that submits with. --}}
@aware(['id' => null, 'selectable' => false, 'selectName' => 'selected', 'selectModel' => null])

@php
    $hasDetails = isset($details) && $details->isNotEmpty();
    // A click can't both follow a link and expand the row; the details only would open, middle-click included.
    if ($href && $hasDetails) {
        throw new \InvalidArgumentException('<x-widget.table.row> takes href or a details slot, not both: a click can only do one. Put the link inside the details instead.');
    }
    $detailsId = $hasDetails ? app(\Bladewell\Support\ElementIds::class)->claim('row-details') : null;
    $interactive = $href || $hasDetails;
@endphp

{{-- Clicking the row follows `href`, or toggles the `details` slot. Links, buttons and checkboxes inside the row keep working on their own. --}}
<tr
    data-table-row
    @if ($href) data-href="{{ $href }}" @if ($linkLabel) data-link-label="{{ $linkLabel }}" @endif @endif
    @if ($hasDetails) data-expandable @endif
    {{-- A row's Tab stop is a real control inside it, so screen readers hear what it is: the link resources/js/widget/table
         puts in a linked row's first cell, or the Details button of an expandable one (aria-expanded isn't allowed on a
         table row itself). --}}
    {{ $attributes->class([
        'group/row border-line h-14 border-b transition-colors group-data-[density=compact]/table:h-11',
        // "odd of [data-table-row]" skips the hidden details rows, so stripes stay even.
        'group-data-striped/table:nth-[odd_of_[data-table-row]]:bg-field/60',
        // A light wash to follow the row under the pointer; stronger, with a pointer cursor, when the row does something.
        'hover:bg-field/50' => ! $interactive,
        // `!`: the stripe rule is equally specific and emitted later, so hover would lose on tinted rows.
        'hover:bg-field! focus-visible:outline-primary cursor-pointer focus-visible:outline-2 focus-visible:-outline-offset-2' => $interactive,
        // The ring goes on the whole row when its link or Details button has keyboard focus.
        'has-[[data-row-link]:focus-visible]:outline-primary has-[[data-row-link]:focus-visible]:outline-2 has-[[data-row-link]:focus-visible]:-outline-offset-2' => $href,
        'has-[[data-row-toggle]:focus-visible]:outline-primary has-[[data-row-toggle]:focus-visible]:outline-2 has-[[data-row-toggle]:focus-visible]:-outline-offset-2' => $hasDetails,
        'has-[[data-table-select]:checked]:bg-primary/5!' => $selectable,
        'data-expanded:[&>td:first-child]:shadow-[inset_4px_0_0_var(--color-primary)]' => $hasDetails,
    ]) }}
>
    @if ($selectable)
        {{-- data-no-row-click: a slightly missed click on the checkbox shouldn't open the row. --}}
        <td data-no-row-click class="w-12 max-sm:group-data-stack/root:justify-start!">
            @if ($value !== null)
                <input type="checkbox" data-table-select form="{{ $id }}-selection" name="{{ $selectName }}[]" value="{{ $value }}" @if ($selectModel) wire:model="{{ $selectModel }}" @endif aria-label="{{ $selectLabel }}" class="accent-primary size-4 cursor-pointer align-middle">
            @endif
        </td>
    @endif
    {{ $slot }}
    @if ($hasDetails)
        {{-- The table adds a matching header cell when any row expands (see table/index). --}}
        <td class="w-12 text-end!">
            <button type="button" data-row-toggle aria-expanded="false" aria-controls="{{ $detailsId }}" aria-label="Details" class="inline-grid cursor-pointer place-items-center rounded-md align-middle outline-none">
                <x-widget.icon name="chevron-down" class="text-foreground/70 size-5 transition-transform duration-300 group-data-expanded/row:rotate-180 motion-reduce:transition-none" />
            </button>
        </td>
    @endif
</tr>

@if ($hasDetails)
    {{-- hidden while collapsed: even at zero height a table row still takes half a border (0.5px) in a collapsed
         table, which made a short last page shorter than a full one. The JS un-hides it just before expanding. --}}
    <tr id="{{ $detailsId }}" data-table-details inert hidden class="group/details bg-field/60">
        {{-- colspan larger than any real table: browsers clamp it to the actual column count. --}}
        <td colspan="100" class="p-0! text-start! whitespace-normal! max-sm:group-data-stack/root:block!">
            <div class="grid grid-rows-[0fr] transition-[grid-template-rows] duration-300 ease-out group-data-open/details:grid-rows-[1fr] motion-reduce:transition-none">
                <div class="overflow-hidden opacity-0 transition-opacity duration-300 group-data-open/details:opacity-100">
                    {{-- In a table wider than the screen, the text wraps to the visible width (set by the JS) instead of running off it. --}}
                    <div class="max-w-(--table-visible) px-5 py-4">{{ $details }}</div>
                </div>
            </div>
        </td>
    </tr>
@endif
