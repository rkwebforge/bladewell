@props([
    // What Filters::read() made of the request. It brings your fields with it, so the builder only ever offers what the
    // server accepts, and the rows to show back, with the reason next to any it turned down. Before any request:
    // $filters->read(null).
    'state',
    // The name the filters are sent under, as one field: ?filters={"match":"all","rules":[…]}. Left out when there are none.
    'name' => 'filters',
    // Above the filters, and their name for screen readers.
    'label' => 'Filters',
    // The add button's text.
    'addLabel' => 'Add filter',
    // Shown while there are no filters, so the add button reads as the next step.
    'emptyLabel' => 'No filters yet, so every row is shown.',
    // What a filter row is called, numbered after it: "Remove Filter 2".
    'rowLabel' => 'Filter',
    // The remove button's name, followed by the row's.
    'removeLabel' => 'Remove',
    // The label of the choice between matching all filters or any.
    'matchLabel' => 'Show rows that match',
    // With matchLabel: matching every filter.
    'allLabel' => 'All filters',
    // With matchLabel: matching at least one.
    'anyLabel' => 'Any filter',
    // The field select's label.
    'fieldLabel' => 'Field',
    // The condition select's label.
    'conditionLabel' => 'Condition',
    // The value's label.
    'valueLabel' => 'Value',
    // The second value's label, with between.
    'andLabel' => 'and',
    // The label of the number of days, with "is in the last … days".
    'daysLabel' => 'Days',
    // Read out when a row's field changes and its conditions change with it. :field is the field's label.
    'changedMessage' => 'Conditions for :field',
    // Above the filters when the server left some out, so people know the rows shown aren't filtered by them. One
    // form, then the other after |, chosen by :count.
    'leftOutMessage' => ':count filter was left out: see the marked field.|:count filters were left out: see the marked fields.',
    // Under the filters while some rows are still missing a value, or have one the server would turn down: those aren't
    // used, so the rows shown aren't filtered by them yet. One form, then the other after |, chosen by :count.
    'pendingMessage' => ':count filter isn\'t complete, so it isn\'t used yet.|:count filters aren\'t complete, so they aren\'t used yet.',
])

@php
    $filters = $state->filters;
    $fields = $filters->fields;
    if ($fields === []) {
        throw new \InvalidArgumentException('<x-widget.filter-builder> needs a field to filter by: make the Filters with at least one FilterField.');
    }
    $id = app(\Bladewell\Support\ElementIds::class)->claim(\Bladewell\Support\FormField::idFor($name).'-builder');

    // Each row as it was sent, then a new one for the template. FilterState works out what each shows.
    $rows = [];
    foreach ($state->shownRows() as $index => $row) {
        $rows['r'.$index] = $row;
    }
    $rows['__filter__'] = $state->blankRow();
    // In Livewire your component can set the filters itself (a saved one loaded, cleared): see the template below.
    $livewire = $attributes->whereStartsWith('wire:model')->isNotEmpty();
@endphp

{{--
    resources/js/filter-builder puts the right condition and value controls in a row as its field or condition
    changes, copied from the templates below, and writes the complete rows to the one hidden field as JSON: in a table's
    filters slot, that filters in place, as any field there does. The rows' own controls have no names, so only that
    field is sent. wire:ignore: the rows are the browser's until it's sent. Without JavaScript the current filters show
    and go with the form unchanged, but can't be edited. A row the server turned down (data-filter-rejected) isn't sent
    again until it's changed, so it can't come back as something else.
--}}
<div
    data-filter-builder
    data-filter-script="{{ json_encode($filters->forScript()) }}"
    data-changed-message="{{ $changedMessage }}"
    role="group"
    aria-labelledby="{{ $id }}-label"
    {{ $attributes->whereDoesntStartWith('wire:model')->class(['grid w-full gap-4']) }}
>
    <input type="hidden" @if ($name) name="{{ $name }}" @endif value="{{ $state->toJson() }}" data-filter-builder-value {{ $attributes->whereStartsWith('wire:model') }}>
    <p id="{{ $id }}-label" class="text-style-2 text-foreground">{{ $label }}</p>
    @php($leftOut = count(array_filter(array_column($state->rows, 'error'))))
    @if ($state->error || $leftOut > 0)
        {{-- Read out as the page comes: the rows below are filtered without these. --}}
        <div data-filter-builder-error role="alert" class="text-error flex items-start gap-1 text-xs">
            <x-widget.icon name="circle-alert" class="mt-0.5 size-3.5" />
            <p>{{ implode(' ', array_filter([$state->error, $leftOut > 0 ? app('translator')->choice($leftOutMessage, $leftOut, ['count' => $leftOut]) : null])) }}</p>
        </div>
    @endif

    <span data-filter-builder-status role="status" class="sr-only"></span>
    {{-- The rows as this state has them: shown below, and in Livewire also in a template outside wire:ignore. --}}
    @php(ob_start())
    <div data-filter-shown class="grid gap-4">
        {{-- Only from the second filter: with one, or none, all and any are the same. Hidden by CSS, so it comes and goes
             with the rows with no script; what it's set to is still sent. --}}
        <div data-filter-match class="hidden max-w-xs [[data-filter-shown]:has([data-repeater-row]~[data-repeater-row])>&]:block">
            <x-widget.select :label="$matchLabel" :options="['all' => $allLabel, 'any' => $anyLabel]" :value="$state->any ? 'any' : 'all'" />
        </div>

        <x-widget.repeater :name="$name.'-rows'" :max="\Bladewell\Support\Filters::MAX_CONDITIONS" :add-label="$addLabel" :row-label="$rowLabel" :remove-label="$removeLabel" placeholder="__filter__" compact :movable="false">
            @foreach ($rows as $key => $row)
                <x-widget.repeater.row :key="$key">
                    <div data-filter-row @if ($row['error'] !== null) data-filter-rejected @endif class="contents">
                        <div data-filter-part="field">
                            <x-widget.select :label="$fieldLabel" :options="array_map(static fn ($field): string => $field->label, $fields)" :value="$row['fieldChosen'] ? $row['field']->key : ''" :error="$row['errorOn'] === 'field' ? $row['error'] : null" />
                        </div>
                        <x-widget.filter-builder.part :kind="'operator:'.$row['field']->type->value" :value="$row['operator']?->value ?? ''" :error="$row['errorOn'] === 'operator' ? $row['error'] : null" />
                        @foreach ($row['parts'] as $kind => $value)
                            <x-widget.filter-builder.part :kind="$kind" :field="$row['field']" :value="$value" :error="$row['errorOn'] === $kind ? $row['error'] : null" />
                        @endforeach
                    </div>
                </x-widget.repeater.row>
            @endforeach
            <x-slot:empty>{{ $emptyLabel }}</x-slot:empty>
        </x-widget.repeater>
    </div>
    @php($shown = ob_get_clean())

    <div wire:ignore data-filter-rows data-filter-input="{{ $state->sent }}" class="grid gap-4">
        {{-- Markup this component just rendered from its own parts, every value in it escaped there. --}}
        {!! $shown !!}
        <p data-filter-builder-pending data-message="{{ $pendingMessage }}" aria-live="polite" class="text-foreground/70 text-sm empty:hidden"></p>

        {{-- One of each control a row can need, copied into a row as its field or condition changes. --}}
        @foreach ($filters->types() as $type)
            <template data-filter-template="operator:{{ $type }}"><x-widget.filter-builder.part :kind="'operator:'.$type" /></template>
        @endforeach
        @foreach ($filters->parts() as $kind)
            <template data-filter-template="{{ $kind }}"><x-widget.filter-builder.part :kind="$kind" :field="str_starts_with($kind, 'choice:') ? $fields[substr($kind, 7)] : null" /></template>
        @endforeach
    </div>
    @if ($livewire)
        {{--
            The rows a render brings, keyed by the filters as sent, so a render for other filters replaces it. The script
            shows them in place of the rows above only when those filters aren't ones it sent: your component set them.
            The same markup as above, escaped there.
        --}}
        <template data-filter-rows-source data-filter-input="{{ $state->sent }}" wire:key="{{ $id }}-rows-{{ md5($state->sent) }}">{!! $shown !!}</template>
    @endif
</div>
