@props([
    // Which control: operator:{type} (the conditions a type offers), text, number, number-to, whole, whole-to, date,
    // date-to, days, or choice:{field key}.
    'kind',
    // With choice: the field, whose options it lists.
    'field' => null,
    // What it holds: a condition, a value, or with choice a list of them.
    'value' => null,
    // Why the server turned the row down, when it's about this control: shown on it, as on any field.
    'error' => null,
])

{{-- The builder's own labels, so each control is named as the builder was told. --}}
@aware([
    'conditionLabel' => 'Condition',
    'valueLabel' => 'Value',
    'andLabel' => 'and',
    'daysLabel' => 'Days',
])

@php
    [$what, $of] = explode(':', $kind, 2) + [1 => null];
@endphp

@if ($what === 'operator')
    @php($type = \Bladewell\Support\FilterType::from((string) $of))
    <div data-filter-part="operator" data-filter-type="{{ $type->value }}">
        <x-widget.select :label="$conditionLabel" :options="array_column(array_map(static fn ($operator): array => [$operator->value, $operator->label()], $type->operators()), 1, 0)" :value="$value ?? $type->operators()[0]->value" :error="$error" />
    </div>
@else
    <div data-filter-value="{{ $kind }}">
        @switch ($what)
            @case ('text')
                <x-widget.text-input :label="$valueLabel" :value="$value" :error="$error" />
                @break
            @case ('number')
            @case ('number-to')
                <x-widget.number :label="$what === 'number' ? $valueLabel : $andLabel" :decimals="4" negative :value="$value" :error="$error" />
                @break
            @case ('whole')
            @case ('whole-to')
                <x-widget.number :label="$what === 'whole' ? $valueLabel : $andLabel" :decimals="0" negative :value="$value" :error="$error" />
                @break
            @case ('date')
            @case ('date-to')
                <x-widget.datepicker :label="$what === 'date' ? $valueLabel : $andLabel" :max="false" :value="$value" :error="$error" />
                @break
            @case ('days')
                <x-widget.number :label="$daysLabel" :decimals="0" :min="1" :max="\Bladewell\Support\Filters::MAX_DAYS" :value="$value" :error="$error" />
                @break
            @case ('choice')
                <x-widget.select :label="$valueLabel" :options="$field?->options ?? []" multiple :value="(array) $value" :error="$error" />
                @break
        @endswitch
    </div>
@endif
