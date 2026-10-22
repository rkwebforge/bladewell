@props([
    // What the date submits as (YYYY-MM-DD). Optional with wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // The date: YYYY-MM-DD or a date object. Old input wins after a failed submit; with wire:model and no value, the
    // bound property.
    'value' => null,
    // Shown while no date is picked; "Choose a date" by default.
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
    // A date-of-birth picker: true for 18 or older, or a number for another minimum age. Sets max and the hint to
    // match.
    'birthday' => false,
    // The first day of the week, 0 (Sunday) to 6 (Saturday); defaults to the locale's.
    'weekStart' => null,
    // The language for month and day names and the date shown (en, fr, ar…); defaults to the app's.
    'locale' => null,
    // With required: the message when nothing is picked.
    'requiredMessage' => 'Choose a date.',
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

    // birthday means 18 or older; birthday="21" sets another age. Pair it with new MinimumAge($age).
    $minAge = match (true) {
        $birthday === true => 18,
        is_numeric($birthday) && (int) $birthday > 0 => (int) $birthday,
        default => null,
    };
    $birthday = $minAge !== null;
    $maxYearsBack = $minAge;

    // "today" is resolved by the browser, on the user's own clock: the server's date can be a day
    // behind or ahead of the user (e.g. UTC server, user in IST), which would block or allow the wrong day.
    // Browser-side limit only; the Form Request must enforce the same rule with
    // Bladewell\Rules\NotAfterToday (default) or MinimumAge (birthday), not 'before_or_equal:today'.
    // :max="false" removes the limit (Blade turns a passed null back into the 'today' default).
    $max = match (true) {
        $birthday, $max === 'today' => 'today',
        $max === false => null,
        default => $toIso($max),
    };
    $min = $min === 'today' ? 'today' : $toIso($min);
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'datepicker', attributes: $attributes);
    $id = $field->id;
    $value = $toIso($field->old($value));
    // Dates read the way the locale writes them; the browser keeps the same format (see the JS).
    $locale = \Bladewell\Support\LocalDate::locale($locale);
    $weekStart ??= \Bladewell\Support\LocalDate::firstDayOfWeek($locale);
    $display = $value ? \Bladewell\Support\LocalDate::format($value, $locale) : null;
    $placeholder ??= 'Choose a date';
    // Explains the greyed-out recent dates; pass info="" to hide it, or your own text to replace it.
    $info ??= $birthday ? "You must be {$minAge} or older." : null;
@endphp

{{--
    Built on the shared input frame (widget/field): same label, box, error and hint as every
    other input, all messages, clear-on-edit. The caller's attributes go on the submitted hidden input;
    `class` goes on the wrapper.
--}}
{{-- The button can't be required or aria-required, so the label says it for screen readers, and resources/js/widget/field
     stops an empty submit (the value is in hidden inputs, which the browser never validates). --}}
<x-widget.field
    :required="$attributes->has('required')"
    :announce-required="true"
    :data-required-message="$attributes->has('required') && ! $disabled ? $requiredMessage : false"
    data-datepicker
    data-min="{{ $min }}"
    data-max="{{ $max }}"
    :data-max-years-back="$maxYearsBack"
    data-week-start="{{ $weekStart }}"
    data-locale="{{ $locale }}"
    :id="$id"
    :label="$label"
    :error="$field->errors"
    :info="$info"
    :disabled="$disabled"
    :readonly="$readonly"
    box="h-12 items-center"
    :class="$attributes->get('class')"
>
    <button
        type="button"
        id="{{ $id }}"
        {{-- Readonly: a button that opens nothing, so it says it has no popup; "read only" joins its name instead, as a
             button can't carry aria-readonly. --}}
        @unless ($readonly) popovertarget="{{ $id }}-calendar" aria-haspopup="dialog" aria-expanded="false" @endunless
        {{-- A <label> would otherwise be the button's whole name, and the chosen date inside it would never be read. --}}
        @if ($label) aria-labelledby="{{ $id }}-label {{ $id }}-display{{ $readonly ? " {$id}-readonly" : '' }}" @endif
        {{ $field->aria($attributes, (bool) $info) }}
        @disabled($disabled)
        data-datepicker-trigger
        class="flex h-full w-full min-w-0 items-center justify-between gap-2.5 px-5 text-start whitespace-nowrap outline-none select-none disabled:cursor-not-allowed disabled:opacity-50 {{ $readonly ? 'cursor-default' : 'enabled:cursor-pointer' }}"
    >
        @if ($readonly)
            <span id="{{ $id }}-readonly" class="sr-only">read only</span>
        @endif
        <span
            id="{{ $id }}-display"
            data-datepicker-display
            @if (! $display) data-empty @endif
            class="text-style-2 data-empty:text-muted group-data-invalid/field:data-empty:text-error truncate"
        >{{ $display ?? $placeholder }}</span>

        <x-widget.icon name="calendar-days" @class(['size-5', 'opacity-40' => $disabled]) />
    </button>

    <input type="hidden" @if ($name) name="{{ $name }}" @endif value="{{ $value }}" data-datepicker-input {{ $field->forwarded($attributes)->except(['required']) }}>

    {{-- wire:ignore: the script builds everything in here (the month and year lists, the days), and a Livewire render
         would empty it again, since the server sends it empty. The value lives outside, on the hidden input(s). --}}
    <div
        wire:ignore
        id="{{ $id }}-calendar"
        popover
        role="dialog"
        aria-label="{{ $label ?? 'Choose a date' }}"
        data-datepicker-popover
        class="border-line text-foreground fixed inset-auto m-0 overflow-y-auto overscroll-contain rounded-md border bg-surface p-3 shadow-lg"
    >
        {{-- Selects never shrink, so the full month name always shows; a <select> is as wide as its
             longest option, so if "September" fits at the 252px minimum, every month does. --}}
        <div class="mb-2 flex items-center justify-between">
            <button type="button" data-datepicker-prev aria-label="Previous month" class="enabled:hover:bg-field tap-target relative grid size-7 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-30">
                <x-widget.icon name="chevron-left" class="size-4 rtl:rotate-180" />
            </button>

            {{-- Each select draws its own arrow (appearance-none hides the browser's, which sits against the focus ring). --}}
            <div class="flex shrink-0 gap-1">
                <span class="relative inline-flex shrink-0">
                    <select id="{{ $id }}-month" data-datepicker-month aria-label="Month" class="hover:bg-field shrink-0 cursor-pointer appearance-none rounded-md bg-transparent py-1 ps-1 pe-5 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary"></select>
                    <x-widget.icon name="chevron-down" class="text-foreground/70 pointer-events-none absolute end-1.5 top-1/2 size-3 -translate-y-1/2" />
                </span>
                <span class="relative inline-flex shrink-0">
                    <select id="{{ $id }}-year" data-datepicker-year aria-label="Year" class="hover:bg-field shrink-0 cursor-pointer appearance-none rounded-md bg-transparent py-1 ps-1 pe-5 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-primary"></select>
                    <x-widget.icon name="chevron-down" class="text-foreground/70 pointer-events-none absolute end-1.5 top-1/2 size-3 -translate-y-1/2" />
                </span>
            </div>

            <button type="button" data-datepicker-next aria-label="Next month" class="enabled:hover:bg-field tap-target relative grid size-7 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2 focus-visible:ring-primary disabled:opacity-30">
                <x-widget.icon name="chevron-right" class="size-4 rtl:rotate-180" />
            </button>
        </div>

        <table data-datepicker-grid class="w-full table-fixed border-collapse text-center"></table>
    </div>
</x-widget.field>
