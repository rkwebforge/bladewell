@props([
    // What it submits as; its error and old input are found under it. Optional with wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Beside the box, and its name for screen readers. Or use the slot for markup (a link to the terms).
    'label' => null,
    // Smaller text under the label.
    'description' => null,
    // What it submits when ticked.
    'value' => '1',
    // Ticked to start with. Old input wins after a failed submit; with wire:model, the bound property.
    'checked' => false,
    // Also submits this when unticked (e.g. 0), so the field is always in the request.
    'uncheckedValue' => null,
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be changed.
    'disabled' => false,
    // Shown, and submitted, but it can't be changed. With disabled, disabled wins.
    'readonly' => false,
])

@php
    $readonly = $readonly && ! $disabled;
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'checkbox', attributes: $attributes);
    // After a failed submit of this box's own form, old input decides; otherwise `checked` does. See FormField::checked().
    $isChecked = $field->checked($value, (bool) $checked, (bool) $disabled, $uncheckedValue !== null, $errors ?? null, $bag);
@endphp

{{-- The label sits beside the box, so the frame's own label above is unused; its error and hint still show below. --}}
<x-widget.field :id="$field->id" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" :required="$attributes->has('required')" bare :class="$attributes->get('class')">
    {{-- Sent when the box is unticked, e.g. unchecked-value="0" for a boolean column. --}}
    @if ($uncheckedValue !== null && $name)
        <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
    @endif

    <label for="{{ $field->id }}" @class(['flex items-start gap-3', 'cursor-pointer' => ! $disabled && ! $readonly, 'cursor-not-allowed opacity-60' => $disabled, 'opacity-70' => $readonly])>
        <span class="relative mt-0.5 grid size-5 shrink-0 place-items-center">
            <input
                type="checkbox"
                id="{{ $field->id }}"
                @if ($name) name="{{ $name }}" @endif
                value="{{ $value }}"
                @checked($isChecked)
                @disabled($disabled)
                {{-- resources/js/widget/field keeps a click or Space from changing it; a checkbox has no readonly of its own. --}}
                @if ($readonly) aria-readonly="true" data-readonly @endif
                {{ $field->controlAttributes($attributes, (bool) $info)->class([
                    // border-muted: an unchecked box's edge must reach 3:1 against the page to be seen at all.
                    'peer border-muted bg-surface size-5 appearance-none rounded-md border transition-colors outline-none',
                    'checked:border-primary-fill checked:bg-primary-fill focus-visible:ring-primary focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                    'group-data-invalid/field:border-error disabled:cursor-not-allowed',
                    'cursor-pointer' => ! $disabled && ! $readonly,
                ]) }}
            >
            <x-widget.icon name="check" class="text-on-primary pointer-events-none absolute size-3.5 opacity-0 peer-checked:opacity-100" />
        </span>

        <span class="min-w-0">
            <span class="text-foreground">{{ $slot->isNotEmpty() ? $slot : $label }}@if ($attributes->has('required'))<span class="text-error" aria-hidden="true"> *</span>@endif</span>
            @if ($description)
                <span class="text-foreground/70 mt-0.5 block text-xs">{{ $description }}</span>
            @endif
        </span>
    </label>
</x-widget.field>
