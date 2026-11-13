@props([
    // What it submits as; its error and old input are found under it. Optional with wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // The value to show. Old input wins after a failed submit; with wire:model and no value, the bound Livewire
    // property.
    'value' => null,
    // Shown while it's empty.
    'placeholder' => null,
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be changed.
    'disabled' => false,
    // Shown, and submitted, but it can't be edited.
    'readonly' => false,
    // Decimal places allowed (0 for whole numbers). Comma or dot both type the decimal point.
    'decimals' => 2,
    // Digits only, for phone-style numbers: no decimals, no minus, and the phone keypad.
    'mobile' => false,
    // Text at the start, inside the field (e.g. https://).
    'prefix' => null,
    // Text or a small control at the end, inside the field (e.g. a unit).
    'suffix' => null,
    // Shows the suffix in capitals (usdt → USDT).
    'uppercase' => true,
    // Allows a leading minus.
    'negative' => false,
    // The lowest value: leaving the field pulls it into range, and the stepper stops there.
    'min' => null,
    // The highest value, in the same way.
    'max' => null,
    // How much the stepper buttons and arrow keys change it (1 by default).
    'step' => null,
    // − and + buttons, plus ArrowUp and ArrowDown; announced as a spin button.
    'stepper' => false,
    // Thousands separators while typing (1,234,567.5); it still submits the plain number.
    'grouped' => false,
    // The locale for grouping and the decimal point; defaults to the app's.
    'locale' => null,
])

@php
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'number', attributes: $attributes);
    $value = $field->old($value);
    // A mobile number is digits only, so it never takes a decimal point (for full phone numbers, see input.phone).
    $decimals = $mobile ? 0 : max(0, (int) $decimals);
    $negative = $negative && ! $mobile;
    $step ??= 1;

    // grouped shows 1,250,000.50 while typing and submits 1250000.50 from a hidden input. It writes and reads
    // numbers the locale's way (1.250.000,50 in de), so the separators come from the locale here and in the JS.
    $locale = str_replace('_', '-', $locale ?? app()->getLocale());
    $groupedDisplay = null;
    if ($grouped && ! $mobile && is_numeric($value) && class_exists(\NumberFormatter::class)) {
        [$whole, $fraction] = array_pad(explode('.', ltrim((string) $value, '-'), 2), 2, null);
        // @numbers=latn: Arabic, Persian or Devanagari locales still write 0-9, as the JS parses and the form submits.
        $formatter = new \NumberFormatter(str_replace('-', '_', $locale).'@numbers=latn', \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, 0);
        $groupedDisplay = (str_starts_with((string) $value, '-') ? '-' : '')
            .$formatter->format((float) $whole)
            .($fraction !== null ? $formatter->getSymbol(\NumberFormatter::DECIMAL_SEPARATOR_SYMBOL).$fraction : '');
    }
    $grouped = $grouped && ! $mobile;
    $stepButton = 'text-foreground/70 hover:bg-line hover:text-foreground focus-visible:ring-primary tap-target-y relative grid size-8 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2 disabled:opacity-40';
@endphp

{{-- type="text" rather than "number": number inputs allow "e", scroll-wheel changes and lose leading zeros.
     min and max are therefore enforced by resources/js/widget/number (kept in range when the field is left). --}}
<x-widget.field :required="$attributes->has('required')" :id="$field->id" :label="$label" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" box="h-12 items-center pe-5" :class="$attributes->get('class')">
    @if ($stepper)
        <button type="button" data-number-step="-1" aria-label="Decrease" aria-controls="{{ $field->id }}" tabindex="-1" @disabled($disabled || $readonly) class="{{ $stepButton }} ms-2">
            <x-widget.icon name="minus" class="size-4" />
        </button>
    @endif

    @if ($mobile)
        <span class="shrink-0 ps-5 pe-2 select-none">+</span>
    @elseif ($prefix)
        <span @class(['text-muted shrink-0 select-none', 'ps-5 pe-2' => ! $stepper, 'ps-1 pe-2' => $stepper])>{{ $prefix }}</span>
    @endif

    <input
        type="text"
        id="{{ $field->id }}"
        @if ($name && ! $grouped) name="{{ $name }}" @endif
        value="{{ $grouped ? ($groupedDisplay ?? $value) : $value }}"
        placeholder="{{ $placeholder }}"
        inputmode="{{ $mobile ? 'tel' : ($decimals > 0 ? 'decimal' : 'numeric') }}"
        autocomplete="{{ $mobile ? 'tel' : 'off' }}"
        {{-- With a stepper, ArrowUp/ArrowDown step the value (the buttons are pointer-only), so say so the ARIA way. --}}
        @if ($stepper)
            role="spinbutton"
            @if ($min !== null) aria-valuemin="{{ $min }}" @endif
            @if ($max !== null) aria-valuemax="{{ $max }}" @endif
            @if (is_numeric($value)) aria-valuenow="{{ $value }}" @endif
        @endif
        data-number-input
        data-decimals="{{ $decimals }}"
        data-step="{{ $step }}"
        @if ($negative) data-negative @endif
        @if ($min !== null) data-min="{{ $min }}" @endif
        @if ($max !== null) data-max="{{ $max }}" @endif
        data-locale="{{ $locale }}"
        @if ($grouped) data-grouped @endif
        @disabled($disabled)
        @readonly($readonly)
        {{ ($grouped ? $field->visibleAttributes($attributes, (bool) $info) : $field->controlAttributes($attributes, (bool) $info))->class([
            'h-full w-full min-w-0 bg-transparent outline-none placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-50 read-only:cursor-default',
            'ps-5' => ! $mobile && ! $prefix && ! $stepper,
            // Between - and + the value sits centred.
            'text-center' => $stepper,
            'tabular-nums' => $grouped,
            'group-data-invalid/field:placeholder:text-error group-data-invalid/field:focus:placeholder:text-muted',
        ]) }}
    >

    {{-- grouped: the visible field is for people; this carries the plain number to the form, Livewire and Alpine. --}}
    @if ($grouped)
        <input type="hidden" @if ($name) name="{{ $name }}" @endif value="{{ $value }}" data-number-value {{ $field->bindings($attributes) }}>
    @endif

    @if ($suffix)
        <span @class(['text-muted shrink-0 cursor-default px-2.5 select-none', 'uppercase' => $uppercase])>{{ $suffix }}</span>
    @endif

    @if ($stepper)
        <button type="button" data-number-step="1" aria-label="Increase" aria-controls="{{ $field->id }}" tabindex="-1" @disabled($disabled || $readonly) class="{{ $stepButton }}">
            <x-widget.icon name="plus" class="size-4" />
        </button>
    @endif

    @isset($action)
        <div class="ms-1 shrink-0">{{ $action }}</div>
    @endisset
</x-widget.field>
