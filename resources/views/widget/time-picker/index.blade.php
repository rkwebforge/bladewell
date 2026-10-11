@props([
    // What the time submits as (HH:MM, 24-hour). Optional with wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // The time: "09:30", "9:30 PM" or a date object. Old input wins after a failed submit; with wire:model and no value,
    // the bound property.
    'value' => null,
    // list: a menu of times, step minutes apart. columns: hour, minute (and AM/PM) columns, for any exact minute.
    // segmented: typed straight into the field, hour and minute, with the arrow keys. slots: a grid of times to choose
    // one of, for bookings.
    'variant' => 'list',
    // Shown while no time is picked; "Choose a time" by default (list and columns).
    'placeholder' => null,
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be changed.
    'disabled' => false,
    // Shown, and submitted, but it can't be changed: the button opens nothing. With disabled, disabled wins.
    'readonly' => false,
    // The earliest time that can be picked, within one day: "09:00". Defaults to 00:00.
    'min' => null,
    // The latest time that can be picked, within one day: "17:30". Defaults to 23:59.
    'max' => null,
    // Minutes between the times offered: 15 by default for list and slots, 1 for columns and segmented.
    'step' => null,
    // A 12-hour clock with AM and PM (true) or a 24-hour one (false); by default the locale's own.
    'hour12' => null,
    // The language times are written in (en-US, de, ja…); defaults to the app's.
    'locale' => null,
    // slots: the times to offer, as "HH:MM" strings or ['time' => '10:30', 'disabled' => true] for a taken one.
    // Without them, every time from min to max, step minutes apart.
    'slots' => null,
    // With required: the message when nothing is picked.
    'requiredMessage' => 'Choose a time.',
])

@php
    $readonly = $readonly && ! $disabled;
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($variant, ['list', 'columns', 'segmented', 'slots'], true)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.time-picker>. Use one of: list, columns, segmented, slots.");
    }
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'time', attributes: $attributes);
    $id = $field->id;
    $value = \Bladewell\Support\TimeOfDay::normalize($field->old($value));
    $locale = str_replace('_', '-', (string) ($locale ?? app()->getLocale()));
    $hour12 = $hour12 === null ? \Bladewell\Support\TimeOfDay::usesHour12($locale) : (bool) $hour12;
    // The browser writes times with this same pattern and these words (resources/js/widget/time-picker), so it agrees
    // with this first paint whatever its own ICU data says.
    $pattern = \Bladewell\Support\TimeOfDay::pattern($locale, $hour12);
    $periods = \Bladewell\Support\TimeOfDay::periods($locale);
    $format = static fn (string $time): string => \Bladewell\Support\TimeOfDay::format($time, $pattern, $periods);
    $min = \Bladewell\Support\TimeOfDay::normalize($min) ?? '00:00';
    $max = \Bladewell\Support\TimeOfDay::normalize($max) ?? '23:59';
    $step = max(1, (int) ($step ?? (in_array($variant, ['columns', 'segmented'], true) ? 1 : 15)));
    $within = static fn (string $time): bool => $time >= $min && $time <= $max;
    $display = $value ? $format($value) : null;
    $placeholder ??= 'Choose a time';
    // segmented: an example of what to type, since the parts alone (hh : mm AM/PM) can read like hours, minutes and
    // seconds. info="" hides it; your own info replaces it.
    if ($variant === 'segmented') {
        $info ??= 'Type it as '.($hour12 ? "09:30 {$periods[1]}" : '21:30');
    }

    // list and slots: the times on offer, each with whether it can be picked.
    $times = collect($slots ?? \Bladewell\Support\TimeOfDay::range($min, $max, $step))
        ->map(static function (mixed $slot) use ($within): ?array {
            $time = \Bladewell\Support\TimeOfDay::normalize(is_array($slot) ? ($slot['time'] ?? null) : $slot);

            return $time === null ? null : ['time' => $time, 'disabled' => ! empty($slot['disabled']) || ! $within($time)];
        })
        ->filter()
        ->values();
    // The list's one Tab stop (a roving tabindex): the chosen time, or else the first that can be picked. The script
    // moves it to whichever option has focus, so Tab always lands back where you were.
    $stop = $times->search(fn (array $slot): bool => $slot['time'] === $value);
    $stop = $stop !== false ? $stop : $times->search(fn (array $slot): bool => ! $slot['disabled']);

    // columns and segmented: the parts of the value.
    [$hour, $minute] = $value ? array_map('intval', explode(':', $value)) : [null, null];
    $isPm = $hour !== null && $hour >= 12;
    $hourShown = $hour === null ? null : ($hour12 ? ($hour % 12 ?: 12) : $hour);

    $required = $attributes->has('required');
    $popover = in_array($variant, ['list', 'columns'], true);
@endphp

{{--
    Built on the shared input frame (widget/field): same label, box, error and hint as every other input. The time
    submits as HH:MM on a 24-hour clock, from the hidden input (or, for slots, the radio buttons), whatever it shows.
    The caller's attributes go on what's submitted; `class` goes on the wrapper.
--}}
<x-widget.field
    :required="$required"
    :announce-required="$variant !== 'slots'"
    :data-required-message="$required && ! $disabled && $variant !== 'slots' ? $requiredMessage : false"
    :data-time-picker="$variant"
    :data-pattern="$pattern"
    :data-periods="json_encode($periods)"
    :data-min="$min"
    :data-max="$max"
    :data-step="$step"
    :data-hour12="$hour12 ? 'true' : 'false'"
    :id="$id"
    :label="$label"
    :error="$field->errors"
    :info="$info"
    :disabled="$disabled"
    :readonly="$readonly"
    :bare="$variant === 'slots'"
    :labels-control="! in_array($variant, ['segmented', 'slots'], true)"
    box="h-12 items-center"
    :class="$attributes->get('class')"
>
    @if ($popover)
        {{-- wire:ignore.self: the script keeps aria-expanded in step with the popover; a render would reset it. --}}
        <button
            type="button"
            id="{{ $id }}"
            wire:ignore.self
            {{-- Readonly: a button that opens nothing, so it says it has no popup; "read only" joins its name instead, as a
                 button can't carry aria-readonly. --}}
            @unless ($readonly) popovertarget="{{ $id }}-popover" aria-haspopup="{{ $variant === 'list' ? 'listbox' : 'dialog' }}" aria-expanded="false" @endunless
            {{-- A <label> would otherwise be the button's whole name, and the chosen time inside it would never be read. --}}
            @if ($label) aria-labelledby="{{ $id }}-label {{ $id }}-display{{ $readonly ? " {$id}-readonly" : '' }}" @endif
            {{ $field->aria($attributes, (bool) $info) }}
            @disabled($disabled)
            data-time-picker-trigger
            class="flex h-full w-full min-w-0 items-center justify-between gap-2.5 px-5 text-start whitespace-nowrap outline-none select-none disabled:cursor-not-allowed disabled:opacity-50 {{ $readonly ? 'cursor-default' : 'enabled:cursor-pointer' }}"
        >
            @if ($readonly)
                <span id="{{ $id }}-readonly" class="sr-only">read only</span>
            @endif
            <span
                id="{{ $id }}-display"
                data-time-picker-display
                data-placeholder="{{ $placeholder }}"
                @if (! $display) data-empty @endif
                class="text-style-2 data-empty:text-muted group-data-invalid/field:data-empty:text-error truncate tabular-nums"
            >{{ $display ?? $placeholder }}</span>
            <x-widget.icon name="clock" @class(['size-5', 'opacity-40' => $disabled]) />
        </button>
    @endif

    @if ($variant === 'segmented')
        {{-- One spinbutton per part, as the browser's own time field has: arrows change it, digits type it, Left and Right
             move between them. The first one carries the field's id, so the label, errors and focus all land on it. --}}
        <div
            role="group"
            @if ($label) aria-labelledby="{{ $id }}-label" @endif
            data-time-picker-segments
            @class(['flex h-full w-full min-w-0 items-center gap-0.5 px-5 tabular-nums', 'opacity-50' => $disabled])
        >
            @foreach (array_filter([
                'hour' => ['Hour', $hourShown, $hour12 ? 1 : 0, $hour12 ? 12 : 23, 'hh'],
                'minute' => ['Minute', $minute, 0, 59, 'mm'],
            ]) as $part => [$partLabel, $partValue, $low, $high, $empty])
                @if ($part === 'minute')
                    <span aria-hidden="true" class="text-muted">:</span>
                @endif
                <span
                    @if ($part === 'hour') id="{{ $id }}" data-time-picker-trigger {{ $field->aria($attributes, (bool) $info) }} @endif
                    role="spinbutton"
                    @unless ($disabled) tabindex="0" @endunless
                    aria-label="{{ $partLabel }}"
                    aria-valuemin="{{ $low }}"
                    aria-valuemax="{{ $high }}"
                    @if ($partValue !== null) aria-valuenow="{{ $partValue }}" aria-valuetext="{{ $partValue }}" @else aria-valuetext="Empty" @endif
                    @if ($disabled) aria-disabled="true" @endif
                    @if ($readonly) aria-readonly="true" @endif
                    data-time-picker-segment="{{ $part }}"
                    {{-- What goes here while it's empty: hh, mm. The script puts it back when a part is cleared. --}}
                    data-placeholder="{{ $empty }}"
                    class="focus:bg-primary/15 data-empty:text-muted min-w-[2ch] rounded-md px-0.5 text-center outline-none"
                    @if ($partValue === null) data-empty @endif
                >{{ $partValue === null ? $empty : str_pad((string) $partValue, 2, '0', STR_PAD_LEFT) }}</span>
            @endforeach
            @if ($hour12)
                <span
                    role="spinbutton"
                    @unless ($disabled) tabindex="0" @endunless
                    aria-label="AM or PM"
                    aria-valuemin="0"
                    aria-valuemax="1"
                    @if ($hour !== null) aria-valuenow="{{ $isPm ? 1 : 0 }}" aria-valuetext="{{ $periods[$isPm ? 1 : 0] }}" @else aria-valuetext="Empty" @endif
                    @if ($disabled) aria-disabled="true" @endif
                    @if ($readonly) aria-readonly="true" @endif
                    data-time-picker-segment="period"
                    data-placeholder="{{ implode('/', $periods) }}"
                    class="focus:bg-primary/15 data-empty:text-muted ms-1.5 rounded-md px-1 text-center outline-none"
                    @if ($hour === null) data-empty @endif
                >{{ $hour === null ? implode('/', $periods) : $periods[$isPm ? 1 : 0] }}</span>
            @endif
            <x-widget.icon name="clock" class="text-muted ms-auto size-5 shrink-0" />
        </div>
    @endif

    @if ($variant === 'slots')
        {{-- Real radio buttons: the arrow keys, required and wire:model work as the browser has them, with no script. --}}
        <div
            role="radiogroup"
            @if ($readonly) aria-readonly="true" @endif
            @if ($label) aria-labelledby="{{ $id }}-label" @endif
            {{ $field->aria($attributes, (bool) $info) }}
            @class(['grid grid-cols-[repeat(auto-fill,minmax(6.5rem,1fr))] gap-2', 'opacity-70' => $readonly])
        >
            @foreach ($times as $i => $slot)
                <label @class([
                    'border-line text-foreground relative grid h-11 place-items-center rounded-xl border text-sm font-medium tabular-nums transition-colors select-none',
                    'has-checked:bg-primary-fill has-checked:text-on-primary has-checked:border-primary-fill has-focus-visible:ring-primary has-focus-visible:ring-2 has-focus-visible:ring-offset-2 has-focus-visible:ring-offset-surface',
                    'hover:border-primary cursor-pointer' => ! $slot['disabled'] && ! $disabled && ! $readonly,
                    'text-muted cursor-not-allowed line-through opacity-60' => $slot['disabled'] || $disabled,
                    'group-data-invalid/field:border-error',
                ])>
                    <input
                        type="radio"
                        @if ($i === 0) id="{{ $id }}" @endif
                        @if ($name) name="{{ $name }}" @endif
                        value="{{ $slot['time'] }}"
                        @checked($value === $slot['time'])
                        @disabled($slot['disabled'] || $disabled)
                        @if ($readonly) data-readonly @endif
                        {{ $field->forwarded($attributes)->except(['aria-describedby']) }}
                        class="sr-only"
                    >
                    {{ $format($slot['time']) }}
                </label>
            @endforeach
        </div>
    @else
        <input type="hidden" @if ($name) name="{{ $name }}" @endif value="{{ $value }}" data-time-picker-input {{ $field->forwarded($attributes)->except(['required']) }}>
    @endif

    @if ($variant === 'list')
        {{-- wire:ignore.self: the script positions the open popover (inline top, left and width); a render would wipe that.
             The times inside still render from the server, the chosen one included. --}}
        <div
            id="{{ $id }}-popover"
            wire:ignore.self
            popover
            role="listbox"
            aria-label="{{ $label ?? 'Choose a time' }}"
            data-time-picker-popover
            class="border-line bg-surface text-foreground fixed inset-auto m-0 hidden max-h-72 min-w-40 flex-col overflow-y-auto overscroll-contain rounded-2xl border p-1.5 text-sm shadow-lg open:flex [scrollbar-width:thin]"
        >
            @foreach ($times as $i => $slot)
                <div
                    id="{{ $id }}-option-{{ $i }}"
                    role="option"
                    tabindex="{{ $i === $stop ? 0 : -1 }}"
                    data-value="{{ $slot['time'] }}"
                    aria-selected="{{ $value === $slot['time'] ? 'true' : 'false' }}"
                    @if ($slot['disabled']) aria-disabled="true" @endif
                    class="focus-visible:bg-field hover:bg-field aria-selected:bg-primary/10 aria-selected:text-primary flex shrink-0 cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2 tabular-nums outline-none select-none aria-disabled:cursor-not-allowed aria-disabled:opacity-40"
                >{{ $format($slot['time']) }}<x-widget.icon name="check" class="in-aria-selected:block hidden size-4" /></div>
            @endforeach
        </div>
    @endif

    @if ($variant === 'columns')
        {{-- wire:ignore.self, as for the list. Which hours and minutes are out of range is worked out by the script as you
             pick, since it depends on the hour chosen. --}}
        <div
            id="{{ $id }}-popover"
            wire:ignore.self
            popover
            role="dialog"
            aria-label="{{ $label ?? 'Choose a time' }}"
            data-time-picker-popover
            class="border-line bg-surface text-foreground fixed inset-auto m-0 hidden flex-col rounded-2xl border p-2 text-sm shadow-lg open:flex"
        >
            <div class="flex gap-1">
                @foreach (array_filter([
                    'hour' => ['Hour', 'Hr', $hour12 ? [12, ...range(1, 11)] : range(0, 23), $hourShown],
                    'minute' => ['Minute', 'Min', range(0, 59, $step), $minute],
                    'period' => $hour12 ? ['AM or PM', '', [0, 1], $hour === null ? null : (int) $isPm] : null,
                ]) as $part => [$partLabel, $heading, $choices, $chosen])
                    {{-- A heading over each column, so the hours and minutes can't be mistaken for each other. Screen readers get
                         the listbox's own name instead. --}}
                    <div class="flex flex-col gap-1">
                        <span aria-hidden="true" class="text-muted h-4 text-center text-xs font-medium">{{ $heading }}</span>
                        <div role="listbox" aria-label="{{ $partLabel }}" data-time-picker-column="{{ $part }}" class="flex max-h-60 w-14 flex-col gap-0.5 overflow-y-auto overscroll-contain [scrollbar-width:none]">
                            {{-- One Tab stop per column, the chosen value or else the first, so Tab moves from column to column. --}}
                            @foreach ($choices as $choice)
                                <div
                                    role="option"
                                    tabindex="{{ $choice === (in_array($chosen, $choices, true) ? $chosen : reset($choices)) ? 0 : -1 }}"
                                    data-value="{{ $choice }}"
                                    aria-selected="{{ $chosen === $choice ? 'true' : 'false' }}"
                                    class="hover:bg-field focus-visible:ring-primary aria-selected:bg-primary-fill aria-selected:text-on-primary grid h-9 shrink-0 cursor-pointer place-items-center rounded-lg tabular-nums outline-none select-none focus-visible:ring-2 focus-visible:ring-inset aria-disabled:cursor-not-allowed aria-disabled:opacity-30"
                                >{{ $part === 'period' ? $periods[$choice] : str_pad((string) $choice, 2, '0', STR_PAD_LEFT) }}</div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="border-line mt-2 flex justify-end border-t pt-2">
                <button type="button" data-time-picker-done class="text-primary hover:bg-primary/10 focus-visible:ring-primary rounded-lg px-3 py-1.5 font-medium outline-none focus-visible:ring-2">Done</button>
            </div>
        </div>
    @endif
</x-widget.field>
