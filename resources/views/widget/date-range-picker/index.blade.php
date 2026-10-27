@props([
    // Submits the range as name[start] and name[end]. Optional with wire:model, which binds an array's start and
    // end.
    'name' => null,
    // Or name the start field yourself (e.g. from).
    'startName' => null,
    // And the end field (e.g. to).
    'endName' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // The first date: YYYY-MM-DD or a date object. Old input wins; with wire:model and no start or end, the bound
    // property.
    'start' => null,
    // The last date, the same way. A range that ends before it starts is dropped.
    'end' => null,
    // Shown while no range is picked; "Choose dates" by default.
    'placeholder' => null,
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be opened.
    'disabled' => false,
    // Shown, and submitted, but it can't be changed: the button opens nothing. With disabled, disabled wins.
    'readonly' => false,
    // The earliest date that can be picked: YYYY-MM-DD, a date object, or "today" (the visitor's today).
    'min' => null,
    // The latest date: YYYY-MM-DD, a date object, "today" (the default), or false for no limit.
    'max' => 'today',
    // The first day of the week, 0 (Sunday) to 6 (Saturday); defaults to the locale's.
    'weekStart' => null,
    // 2 shows two months side by side where the screen is wide enough (one on phones); 1 always shows one.
    'months' => 2,
    // The language for month and day names and the date shown (en, fr, ar…); defaults to the app's.
    'locale' => null,
    // With required: the message when nothing is picked.
    'requiredMessage' => 'Choose a start and an end date.',
])

@php
    $readonly = $readonly && ! $disabled;
    $toIso = static function (mixed $date): ?string {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        if (is_string($date) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return $date;
        }

        return null;
    };

    // name="stay" submits stay[start] and stay[end]; start-name / end-name override either (e.g. flat "from" / "to").
    $startName ??= $name !== null ? "{$name}[start]" : null;
    $endName ??= $name !== null ? "{$name}[end]" : null;
    // wire:model="period" (or x-model) binds an array, as name="period" submits period[start] and period[end]: the
    // start input binds period.start and the end input period.end, with the same modifiers (wire:model.live …).
    $binding = \Bladewell\Support\FormField::binding($attributes);
    $bindingAttribute = array_key_first($binding);
    $bound = $binding[$bindingAttribute] ?? null;
    $startKey = match (true) {
        $startName !== null => \Bladewell\Support\FormField::key($startName),
        $bound !== null => "{$bound}.start",
        default => null,
    };
    $endKey = match (true) {
        $endName !== null => \Bladewell\Support\FormField::key($endName),
        $bound !== null => "{$bound}.end",
        default => null,
    };

    // Messages filed under the range itself ("stay") or either end ("stay.start", "stay.end") all show
    // under the one field. Validate with the same rules as the datepicker, e.g.
    // 'stay.start' => ['required', new NotAfterToday], 'stay.end' => ['required', new NotAfterToday, 'after_or_equal:stay.start'].
    $bagErrors = ($errors ?? null) instanceof \Illuminate\Support\ViewErrorBag ? $errors->getBag($bag) : null;
    $error ??= $bagErrors
        ? array_values(array_unique(array_merge(...array_map(
            static fn (?string $key): array => $key !== null ? $bagErrors->get($key) : [],
            [$name !== null ? \Bladewell\Support\FormField::key($name) : null, $startKey, $endKey],
        ))))
        : null;

    // Same limits as the datepicker: "today" is the user's local day; :max="false" removes the limit.
    $max = match (true) {
        $max === 'today' => 'today',
        $max === false => null,
        default => $toIso($max),
    };
    $min = $min === 'today' ? 'today' : $toIso($min);

    $field = \Bladewell\Support\FormField::make($name ?? $startName, $id, null, $error ?: null, $bag, 'date-range', attributes: $attributes);
    $id = $field->id;
    // Without start and end props, the bound Livewire property (see FormField::fromLivewire).
    if ($start === null && $end === null && $bound !== null) {
        $start = $field->fromLivewire('start')[1];
        $end = $field->fromLivewire('end')[1];
    }
    $start = $toIso($startKey !== null ? old($startKey, $start) : $start);
    $end = $toIso($endKey !== null ? old($endKey, $end) : $end);
    // A half or back-to-front range from old input can't be shown as a range: start over.
    if ($start === null || $end === null || $end < $start) {
        [$start, $end] = [null, null];
    }
    // Dates read the way the locale writes them. The browser redraws the range with Intl's formatRange,
    // which also shortens it naturally ("Sep 26 – Oct 3, 2026").
    $locale = \Bladewell\Support\LocalDate::locale($locale);
    $weekStart ??= \Bladewell\Support\LocalDate::firstDayOfWeek($locale);
    $placeholder ??= 'Choose dates';
    $shown = $start ? \Bladewell\Support\LocalDate::format($start, $locale).' – '.\Bladewell\Support\LocalDate::format($end, $locale) : null;
    // The month and year selects draw their own arrow (appearance-none hides the browser's, which sits against the
    // focus ring), with room for it on the end.
    $control = 'hover:bg-field shrink-0 cursor-pointer appearance-none rounded-md bg-transparent py-1 ps-1 pe-5 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary';
    $nav = 'enabled:hover:bg-field tap-target relative grid size-7 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-30';
@endphp

{{--
    The datepicker's range sibling: same frame, limits and keyboard, but two hidden inputs. `class` and
    `form` go where they belong (wrapper / both inputs); any other attribute goes on the wrapper, since
    neither input alone is "the" control.
--}}
{{-- The button can't be required or aria-required, so the label says it for screen readers, and resources/js/widget/field
     stops an empty submit (the value is in hidden inputs, which the browser never validates). --}}
<x-widget.field
    :required="$attributes->has('required')"
    :announce-required="true"
    :data-required-message="$attributes->has('required') && ! $disabled ? $requiredMessage : false"
    data-date-range
    data-min="{{ $min }}"
    data-max="{{ $max }}"
    data-week-start="{{ $weekStart }}"
    data-locale="{{ $locale }}"
    data-months="{{ (int) $months === 1 ? 1 : 2 }}"
    :id="$id"
    :label="$label"
    :error="$field->errors"
    :info="$info"
    :disabled="$disabled"
    :readonly="$readonly"
    box="h-12 items-center"
    :class="$attributes->get('class')"
    {{ $attributes->except(['class', 'form', 'required', 'aria-invalid', 'aria-describedby', ...array_keys($binding)]) }}
>
    <button
        type="button"
        id="{{ $id }}"
        {{-- Readonly: a button that opens nothing, so it says it has no popup; "read only" joins its name instead, as a
             button can't carry aria-readonly. --}}
        @unless ($readonly) popovertarget="{{ $id }}-calendar" aria-haspopup="dialog" aria-expanded="false" @endunless
        {{-- A <label> would otherwise be the button's whole name, and the chosen range inside it would never be read. --}}
        @if ($label) aria-labelledby="{{ $id }}-label {{ $id }}-display{{ $readonly ? " {$id}-readonly" : '' }}" @endif
        {{ $field->aria($attributes, (bool) $info) }}
        @disabled($disabled)
        data-date-range-trigger
        class="flex h-full w-full min-w-0 items-center justify-between gap-2.5 px-5 text-start whitespace-nowrap outline-none select-none disabled:cursor-not-allowed disabled:opacity-50 {{ $readonly ? 'cursor-default' : 'enabled:cursor-pointer' }}"
    >
        @if ($readonly)
            <span id="{{ $id }}-readonly" class="sr-only">read only</span>
        @endif
        <span
            id="{{ $id }}-display"
            data-date-range-display
            data-placeholder="{{ $placeholder }}"
            @if (! $shown) data-empty @endif
            class="text-style-2 data-empty:text-muted group-data-invalid/field:data-empty:text-error truncate"
        >{{ $shown ?? $placeholder }}</span>

        <x-widget.icon name="calendar-range" @class(['size-5 shrink-0', 'opacity-40' => $disabled]) />
    </button>

    <input type="hidden" @if ($startName) name="{{ $startName }}" @endif value="{{ $start }}" data-date-range-start @if ($bound) {{ $bindingAttribute }}="{{ $bound }}.start" @endif {{ $attributes->only(['form']) }}>
    <input type="hidden" @if ($endName) name="{{ $endName }}" @endif value="{{ $end }}" data-date-range-end @if ($bound) {{ $bindingAttribute }}="{{ $bound }}.end" @endif {{ $attributes->only(['form']) }}>

    {{-- wire:ignore: the script builds everything in here (the month and year lists, the days), and a Livewire render
         would empty it again, since the server sends it empty. The value lives outside, on the hidden input(s). --}}
    <div
        wire:ignore
        id="{{ $id }}-calendar"
        popover
        role="dialog"
        aria-label="{{ $label ?? 'Choose dates' }}"
        data-date-range-popover
        class="border-line text-foreground bg-surface fixed inset-auto m-0 overflow-y-auto overscroll-contain rounded-md border p-3 shadow-lg"
    >
        {{-- The selects and arrows drive the first month shown; a second month, when there is room, follows it. --}}
        <div class="mb-2 flex items-center justify-between">
            <button type="button" data-date-range-prev aria-label="Previous month" class="{{ $nav }}">
                <x-widget.icon name="chevron-left" class="size-4 rtl:rotate-180" />
            </button>

            <div class="flex shrink-0 gap-1">
                <span class="relative inline-flex shrink-0">
                    <select id="{{ $id }}-month" data-date-range-month aria-label="Month" class="{{ $control }}"></select>
                    <x-widget.icon name="chevron-down" class="text-foreground/70 pointer-events-none absolute end-1.5 top-1/2 size-3 -translate-y-1/2" />
                </span>
                <span class="relative inline-flex shrink-0">
                    <select id="{{ $id }}-year" data-date-range-year aria-label="Year" class="{{ $control }}"></select>
                    <x-widget.icon name="chevron-down" class="text-foreground/70 pointer-events-none absolute end-1.5 top-1/2 size-3 -translate-y-1/2" />
                </span>
            </div>

            <button type="button" data-date-range-next aria-label="Next month" class="{{ $nav }}">
                <x-widget.icon name="chevron-right" class="size-4 rtl:rotate-180" />
            </button>
        </div>

        {{-- Month grids are built by resources/js/widget/date-range-picker: day colours in its DAY_TONE; day shape and the
             today ring in DAY_BASE and TODAY_MARK, shared from resources/js/widget/datepicker. --}}
        {{-- On a touch screen a slide that starts on a day picks a range (resources/js/widget/date-range-picker), so the days
             don't scroll or zoom; the rest of the calendar still does. --}}
        <div data-date-range-months class="grid gap-6 [&_td]:touch-none"></div>

        <div class="border-line mt-3 flex items-center justify-between gap-3 border-t pt-3">
            <p data-date-range-status aria-live="polite" class="text-muted min-w-0 text-xs"></p>
            <x-widget.button variant="link" size="sm" data-date-range-clear>Clear</x-widget.button>
        </div>
    </div>
</x-widget.field>
