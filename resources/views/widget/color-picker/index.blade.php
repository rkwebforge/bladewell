@props([
    // What the colour submits as (#rrggbb); its error and old input are found under it. Optional with wire:model, which
    // then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // The colour, as #rrggbb or #rgb. Old input wins after a failed submit; with wire:model and no value, the bound
    // property.
    'value' => null,
    // Colours to pick from in one click: a list of hex values, or name => hex ("Brand" => "#7c3aed"), whose names screen
    // readers hear.
    'swatches' => [],
    // Offers the browser's own picker beside the box, for any colour. false for the swatches and the box only.
    'custom' => true,
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be changed.
    'disabled' => false,
    // Shown, and submitted, but it can't be changed: no picker or swatches, and the box can't be typed in. With disabled,
    // disabled wins.
    'readonly' => false,
])

@php
    $readonly = $readonly && ! $disabled;
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'color', attributes: $attributes);
    // #abc and #ABCDEF read as #aabbcc and #abcdef; anything else isn't a colour this takes.
    $hex = static function (mixed $raw): ?string {
        if (! is_string($raw) || preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($raw), $parts) !== 1) {
            return null;
        }
        $digits = strtolower($parts[1]);

        return '#'.(strlen($digits) === 3 ? preg_replace('/(.)/', '$1$1', $digits) : $digits);
    };
    $current = $hex($field->old($value)) ?? '';
    $swatches = collect($swatches)
        ->mapWithKeys(fn (mixed $color, int|string $key): array => [is_int($key) ? (string) $hex($color) : $key => $hex($color)])
        ->filter()
        ->map(fn (string $color, string $name): array => ['color' => $color, 'name' => $name === $color ? strtoupper($color) : $name]);
@endphp

{{--
    The hex box submits, and wire:model binds it. Beside it, the browser's own colour input for any colour; under it, the
    swatches as a radio group (arrow keys, named for screen readers), outside any form so they never submit. Colours are
    painted by resources/js/widget/color-picker through a CSS variable, as no style attribute may be in the markup.
--}}
<x-widget.field :required="$attributes->has('required')" :id="$field->id" :label="$label" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" :class="$attributes->get('class')">
    <div data-color-picker class="flex h-full w-full items-center gap-3 ps-3 pe-3">
        @if ($custom && ! $readonly)
            <input type="color" data-color-native value="{{ $current ?: '#000000' }}" aria-label="{{ trim(($label ?? 'Colour').' — any colour') }}" @disabled($disabled) class="size-8 shrink-0 cursor-pointer rounded-lg border-0 bg-transparent p-0 disabled:cursor-not-allowed [&::-moz-color-swatch]:rounded-lg [&::-moz-color-swatch]:border-0 [&::-webkit-color-swatch]:rounded-lg [&::-webkit-color-swatch]:border-0 [&::-webkit-color-swatch-wrapper]:p-0">
        @endif
        <input
            type="text"
            id="{{ $field->id }}"
            data-color-hex
            @if ($name) name="{{ $name }}" @endif
            value="{{ $current }}"
            placeholder="#rrggbb"
            inputmode="text"
            autocomplete="off"
            spellcheck="false"
            maxlength="7"
            pattern="#[0-9a-fA-F]{6}"
            @disabled($disabled)
            @readonly($readonly)
            {{ $field->controlAttributes($attributes, (bool) $info)->class(['placeholder:text-muted h-full min-w-0 flex-1 bg-transparent font-mono uppercase outline-none disabled:cursor-not-allowed']) }}
        >
        {{-- The colour behind text, with black or white on it, whichever reads better. Only a picture. --}}
        <span data-color-preview aria-hidden="true" class="border-line grid h-7 shrink-0 place-items-center rounded-md border bg-(--color-picked) px-2 text-xs font-semibold text-(--color-ink) empty:hidden">@if ($current) Aa @endif</span>
    </div>

    <x-slot:after>
        @if ($swatches->isNotEmpty() && ! $readonly)
            <div role="radiogroup" aria-label="{{ trim(($label ?? 'Colour').' swatches') }}" class="mt-3 flex flex-wrap gap-2">
                @foreach ($swatches as $swatch)
                    {{-- form points at no form, so the swatches group together but never submit. --}}
                    <label class="relative cursor-pointer">
                        <input type="radio" form="{{ $field->id }}-no-form" name="{{ $field->id }}-swatch" value="{{ $swatch['color'] }}" data-color-swatch @checked($swatch['color'] === $current) @disabled($disabled) aria-label="{{ $swatch['name'] }}" class="peer sr-only">
                        <span data-color="{{ $swatch['color'] }}" class="border-line peer-checked:ring-primary peer-focus-visible:ring-primary peer-focus-visible:ring-offset-surface block size-8 rounded-full border bg-(--color-swatch) peer-checked:ring-2 peer-checked:ring-offset-2 peer-checked:ring-offset-surface peer-focus-visible:ring-2 peer-focus-visible:ring-offset-2 peer-disabled:cursor-not-allowed peer-disabled:opacity-50"></span>
                    </label>
                @endforeach
            </div>
        @endif
        <span role="status" data-color-status class="sr-only"></span>
    </x-slot:after>
</x-widget.field>
