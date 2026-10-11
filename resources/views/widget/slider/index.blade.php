@props([
    // What it submits as; its error and old input are found under it. With range, two values as name[]. Optional with
    // wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the slider, and its name for screen readers.
    'label' => null,
    // The value, or with range [from, to]. Old input wins after a failed submit; with wire:model and no value, the
    // bound Livewire property. Defaults to min (and max, for range).
    'value' => null,
    // The lowest value.
    'min' => 0,
    // The highest value.
    'max' => 100,
    // How far one move goes: the arrow keys move a step, and values snap to steps from min.
    'step' => 1,
    // Two thumbs on one track, for a span such as a price range. Submits [from, to] as name[].
    'range' => false,
    // Shown before each value, under the label and to screen readers: "$".
    'prefix' => '',
    // Shown after each value: " km", "%".
    'suffix' => '',
    // With range, the first thumb's name for screen readers, after the field's label: "Price Minimum".
    'fromLabel' => 'Minimum',
    // With range, the second thumb's.
    'toLabel' => 'Maximum',
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the slider.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be changed.
    'disabled' => false,
    // Shown, and submitted, but it can't be moved. With disabled, disabled wins.
    'readonly' => false,
])

@php
    $readonly = $readonly && ! $disabled;
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'slider', attributes: $attributes);
    $min = (float) $min;
    $max = (float) $max;
    $step = (float) $step > 0 ? (float) $step : 1.0;
    // A typo fails loudly, instead of a slider that can't move.
    if ($max <= $min) {
        throw new \InvalidArgumentException("<x-widget.slider> needs max greater than min; got min [{$min}] and max [{$max}].");
    }
    // Into range and onto a step, as the browser would: a value it couldn't show isn't shown.
    $fit = static function (mixed $raw, float $fallback) use ($min, $max, $step): float {
        $number = is_numeric($raw) ? (float) $raw : $fallback;
        $number = $min + round(($number - $min) / $step) * $step;

        return max($min, min($max, $number));
    };
    $current = $field->old($value);
    $values = $range
        ? [$fit(is_array($current) ? ($current[0] ?? null) : null, $min), $fit(is_array($current) ? ($current[1] ?? null) : null, $max)]
        : [$fit(is_array($current) ? null : $current, $min)];
    if ($range && $values[0] > $values[1]) {
        $values = [$values[1], $values[0]];
    }
    // Whole numbers print without ".0".
    $plain = static fn (float $number): string => rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
    $percent = static fn (float $number): int => (int) round(($number - $min) / ($max - $min) * 100);
    $fromPercent = $range ? $percent($values[0]) : 0;
    $toPercent = $percent($values[$range ? 1 : 0]);
    $show = static fn (float $number): string => $prefix.$plain($number).$suffix;

    // With range, a binding to the whole array becomes one to each end: wire:model="price" → price.0 and price.1.
    $bindings = $field->bindings($attributes)->getAttributes();
    $bindingFor = static fn (int $end): array => collect($bindings)
        ->mapWithKeys(fn (mixed $property, string $key): array => [$key => ($range && $key !== 'form') ? "{$property}.{$end}" : $property])
        ->all();
    $inputAttributes = $field->forwarded($attributes)->except([...array_keys($bindings), 'required'])
        ->merge($field->aria($attributes, (bool) $info)->getAttributes());
    $inputName = $name ? ($range ? "{$name}[]" : $name) : null;
    // One thumb: the native range input, restyled, so its own keyboard (arrows, Page Up and Down, Home, End) and screen
    // reader support stay.
    $thumb = 'appearance-none bg-transparent absolute inset-0 m-0 h-full w-full outline-none disabled:cursor-not-allowed [&::-moz-range-track]:bg-transparent '
        .'[&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:size-5 [&::-webkit-slider-thumb]:cursor-grab [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-2 [&::-webkit-slider-thumb]:border-primary [&::-webkit-slider-thumb]:bg-surface [&::-webkit-slider-thumb]:shadow-sm '
        .'[&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:size-5 [&::-moz-range-thumb]:cursor-grab [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-2 [&::-moz-range-thumb]:border-primary [&::-moz-range-thumb]:bg-surface [&::-moz-range-thumb]:shadow-sm '
        .'focus-visible:[&::-webkit-slider-thumb]:ring-2 focus-visible:[&::-webkit-slider-thumb]:ring-primary focus-visible:[&::-webkit-slider-thumb]:ring-offset-2 focus-visible:[&::-webkit-slider-thumb]:ring-offset-surface '
        .'focus-visible:[&::-moz-range-thumb]:ring-2 focus-visible:[&::-moz-range-thumb]:ring-primary '
        .'group-data-invalid/field:[&::-webkit-slider-thumb]:border-error group-data-invalid/field:[&::-moz-range-thumb]:border-error';
    // Two thumbs: two native inputs would lie on top of each other, one target over the other, so each thumb is its own
    // element instead (role="slider", as in the ARIA two-thumb slider), moved by the script, with a hidden input each
    // carrying its value. Each thumb's span ends at the other.
    $knob = 'bg-surface border-primary absolute top-1/2 size-5 -translate-y-1/2 cursor-grab touch-none rounded-full border-2 shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface data-front:z-10 group-data-invalid/field:border-error aria-disabled:cursor-not-allowed aria-readonly:cursor-default';
    // Where each sits: its share of the track, which runs between the two thumbs' centres at the ends.
    $knobPlace = ['from' => 'start-[calc((100%-1.25rem)*var(--slider-from)/100)]', 'to' => 'start-[calc((100%-1.25rem)*var(--slider-to)/100)]'];
    $locale = str_replace('_', '-', app()->getLocale());
@endphp

<x-widget.field :id="$field->id" :label="$label" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" :required="$attributes->has('required')" :labels-control="! $range" bare :class="$attributes->get('class')">
    {{-- Under the label: the value, or from – to, as people read it. Screen readers hear each thumb's own value instead. --}}
    <x-slot:before>
        <output data-slider-output for="{{ $range ? "{$field->id}-from {$field->id}" : $field->id }}" aria-hidden="true" class="text-foreground -mt-1.5 mb-2 block text-sm font-medium tabular-nums">{{ $range ? $show($values[0]).' – '.$show($values[1]) : $show($values[0]) }}</output>
    </x-slot:before>

    <div
        data-slider
        data-min="{{ $plain($min) }}"
        data-max="{{ $plain($max) }}"
        data-prefix="{{ $prefix }}"
        data-suffix="{{ $suffix }}"
        data-locale="{{ $locale }}"
        data-step="{{ $plain($step) }}"
        @if ($range) data-range @endif
        @class(['relative h-5', "[--slider-from:{$fromPercent}] [--slider-to:{$toPercent}]", 'opacity-50' => $disabled])
    >
        {{-- The track, and the filled part between the ends (or from the start), placed from the CSS variables: the
             server sets where they start, the script moves them. Logical sides, so right to left fills from the right. --}}
        <div aria-hidden="true" @class(['bg-line pointer-events-none absolute inset-x-2.5 top-1/2 h-1.5 -translate-y-1/2 rounded-full', 'opacity-70' => $readonly])>
            <div class="bg-primary-fill absolute inset-y-0 start-[calc(var(--slider-from)*1%)] end-[calc(100%-var(--slider-to)*1%)] rounded-full group-data-invalid/field:bg-error"></div>
        </div>

        @if ($range)
            @foreach ([['from', 0, $fromLabel, "{$field->id}-from"], ['to', 1, $toLabel, $field->id]] as [$end, $at, $endLabel, $thumbId])
                <div
                    role="slider"
                    id="{{ $thumbId }}"
                    data-slider-thumb="{{ $end }}"
                    tabindex="{{ $disabled ? -1 : 0 }}"
                    aria-label="{{ trim(($label ?? '').' '.$endLabel) }}"
                    aria-orientation="horizontal"
                    aria-valuemin="{{ $plain($at === 0 ? $min : $values[0]) }}"
                    aria-valuemax="{{ $plain($at === 0 ? $values[1] : $max) }}"
                    aria-valuenow="{{ $plain($values[$at]) }}"
                    aria-valuetext="{{ $show($values[$at]) }}"
                    @if ($disabled) aria-disabled="true" @endif
                    @if ($readonly) aria-readonly="true" @endif
                    {{ $field->aria($attributes, (bool) $info)->class([$knob, $knobPlace[$end]]) }}
                ></div>
                <input type="hidden" data-slider-value="{{ $end }}" @if ($inputName) name="{{ $inputName }}" @endif value="{{ $plain($values[$at]) }}" @disabled($disabled) {{ new \Illuminate\View\ComponentAttributeBag($bindingFor($at)) }}>
            @endforeach
        @else
            <input type="range" id="{{ $field->id }}" data-slider-to @if ($inputName) name="{{ $inputName }}" @endif min="{{ $plain($min) }}" max="{{ $plain($max) }}" step="{{ $plain($step) }}" value="{{ $plain($values[0]) }}" aria-valuetext="{{ $show($values[0]) }}" @disabled($disabled) @if ($readonly) aria-readonly="true" data-readonly @endif {{ $inputAttributes->merge($bindingFor(0))->class([$thumb, 'pointer-events-none' => $readonly]) }}>
        @endif
    </div>

    {{-- The ends of the scale. --}}
    <div aria-hidden="true" class="text-muted mt-2 flex justify-between text-xs tabular-nums">
        <span data-slider-end="{{ $plain($min) }}">{{ $show($min) }}</span>
        <span data-slider-end="{{ $plain($max) }}">{{ $show($max) }}</span>
    </div>
</x-widget.field>
