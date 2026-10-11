@props([
    // What it submits as; its error and old input are found under it. Optional with wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Beside the switch, and its name for screen readers.
    'label' => null,
    // Smaller text under the label.
    'description' => null,
    // What it submits when on.
    'value' => '1',
    // On to start with. Old input wins after a failed submit; with wire:model, the bound property.
    'checked' => false,
    // Also submits this when off (e.g. 0), so the field is always in the request.
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
    // sm, md or lg.
    'size' => 'md',
    // end (label first, switch after it, for settings rows) or start (switch first, like a checkbox).
    'placement' => 'end',
    // default, or card: a bordered box that highlights when on.
    'variant' => 'default',
    // A tick when on and a cross when off, so the state doesn't rest on colour alone.
    'icons' => false,
])

@php
    $readonly = $readonly && ! $disabled;
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'switch', attributes: $attributes);
    // Same rule as the checkbox: after a failed submit of its own form, old input decides; otherwise `checked` does.
    $isChecked = $field->checked($value, (bool) $checked, (bool) $disabled, $uncheckedValue !== null, $errors ?? null, $bag);

    // Track, knob and how far the knob travels (track width - knob - 2 × 2px inset), per size. Written out in
    // full so Tailwind finds every class.
    $sizes = [
        'sm' => ['track' => 'h-5 w-9', 'knob' => 'size-4', 'travel' => 'peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4', 'icon' => 'size-2.5'],
        'md' => ['track' => 'h-6 w-11', 'knob' => 'size-5', 'travel' => 'peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5', 'icon' => 'size-3'],
        'lg' => ['track' => 'h-7 w-13', 'knob' => 'size-6', 'travel' => 'peer-checked:translate-x-6 rtl:peer-checked:-translate-x-6', 'icon' => 'size-3.5'],
    ];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($size, $sizes)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.switch>. Use one of: ".implode(', ', array_keys($sizes)).'.');
    }
    if (! in_array($placement, ['end', 'start'], true)) {
        throw new \InvalidArgumentException("Unknown placement [{$placement}] for <x-widget.switch>. Use one of: end, start.");
    }
    if (! in_array($variant, ['default', 'card'], true)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.switch>. Use one of: default, card.");
    }
    $size = $sizes[$size];
    // end: text first, switch at the end, for settings rows. start: switch first, like a checkbox.
    $switchFirst = $placement === 'start';
    $card = $variant === 'card';
@endphp

{{-- A checkbox with role="switch": it submits like one, and screen readers announce on / off. --}}
<x-widget.field :id="$field->id" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" :required="$attributes->has('required')" bare :class="$attributes->get('class')">
    @if ($uncheckedValue !== null && $name)
        <input type="hidden" name="{{ $name }}" value="{{ $uncheckedValue }}">
    @endif

    <label for="{{ $field->id }}" @class([
        'group/switch flex items-center gap-4',
        'justify-between' => ! $switchFirst,
        'flex-row-reverse justify-end gap-3' => $switchFirst,
        // card: the whole row is a tile that lights up while on.
        'border-line bg-surface rounded-2xl border p-4 transition-colors has-checked:border-primary has-checked:bg-primary/5' => $card,
        'cursor-pointer' => ! $disabled && ! $readonly,
        'cursor-not-allowed opacity-60' => $disabled,
        'opacity-70' => $readonly,
    ])>
        <span class="min-w-0">
            <span class="text-foreground">{{ $slot->isNotEmpty() ? $slot : $label }}@if ($attributes->has('required'))<span class="text-error" aria-hidden="true"> *</span>@endif</span>
            @if ($description)
                <span class="text-foreground/60 mt-0.5 block text-xs">{{ $description }}</span>
            @endif
        </span>

        <span @class(['relative inline-flex shrink-0', $size['track']])>
            <input
                type="checkbox"
                role="switch"
                id="{{ $field->id }}"
                @if ($name) name="{{ $name }}" @endif
                value="{{ $value }}"
                @checked($isChecked)
                @disabled($disabled)
                {{-- resources/js/widget/field keeps a click or Space from changing it; a checkbox has no readonly of its own. --}}
                @if ($readonly) aria-readonly="true" data-readonly @endif
                {{ $field->controlAttributes($attributes, (bool) $info)->class([
                    // muted/75: the off track, and the white knob on it, both reach 3:1; the old light grey was 1.25:1.
                    'peer bg-muted/75 absolute inset-0 appearance-none rounded-full transition-colors outline-none',
                    'checked:bg-primary focus-visible:ring-primary focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                    'group-data-invalid/field:ring-error group-data-invalid/field:ring-1 disabled:cursor-not-allowed',
                    'cursor-pointer' => ! $disabled && ! $readonly,
                ]) }}
            >
            {{-- The knob travels toward the end edge: right, or left in RTL. --}}
            <span @class([
                'bg-surface pointer-events-none absolute start-0.5 top-0.5 grid place-items-center rounded-full shadow transition-transform duration-200 motion-reduce:transition-none',
                $size['knob'],
                $size['travel'],
            ])>
                {{-- icons: a tick when on and a cross when off, so the state doesn't rest on colour alone. --}}
                @if ($icons)
                    <x-widget.icon name="check" :class="'text-primary col-start-1 row-start-1 opacity-0 transition-opacity group-has-checked/switch:opacity-100 '.$size['icon']" />
                    <x-widget.icon name="x" :class="'text-muted col-start-1 row-start-1 transition-opacity group-has-checked/switch:opacity-0 '.$size['icon']" />
                @endif
            </span>
        </span>
    </label>
</x-widget.field>
