{{--
    Shared frame for every input: label, field box, and error/info message.
    The error look hangs off data-invalid on the root (group-data-invalid/field:*), not server-side
    conditions, so resources/js/widget/field can clear it the moment the user edits the field.
--}}
@props([
    // The control's id, which the label points at.
    'id',
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // The error message(s) to show under the box.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Greyed out: it can't be changed.
    'disabled' => false,
    // Shown, and submitted, but it can't be edited.
    'readonly' => false,
    // Classes for the field's box (its height and alignment).
    'box' => 'h-12 items-center',
    // No box, for controls that draw their own (OTP boxes, a provider's captcha).
    'bare' => false,
    // Marks the label as required.
    'required' => false,
    // Also says "required" to screen readers, for a control that can't carry required itself.
    'announceRequired' => false,
    // The label is a <label for> the control; false for markup that isn't yours, named by the label's id instead.
    'labelsControl' => true,
    // A count under the field, against this maximum.
    'counter' => null,
    // The count to start at.
    'count' => 0,
])

@php
    // Accepts one message or several (Laravel's $errors->get() returns an array).
    $messages = array_values(array_filter(\Illuminate\Support\Arr::wrap($error), 'is_string'));
    $hasError = $messages !== [];
@endphp

<div data-field @if ($hasError) data-invalid @endif {{ $attributes->class(['group/field text-style-2 relative w-full min-w-44']) }}>
    @if ($label)
        {{-- The id lets a control whose value sits inside it (the date pickers' buttons) be named "label, value". --}}
        {{-- labels-control false: the control is someone else's markup (a captcha provider's), so the text names a group
             by id instead of pointing <label for> at an element this page doesn't own. --}}
        <{{ $labelsControl ? 'label' : 'div' }} @if ($labelsControl) for="{{ $id }}" @endif id="{{ $id }}-label" @class(['text-style-2 mb-[11.5px] block', 'text-muted' => $disabled, 'text-foreground' => ! $disabled])>
            {{ $label }}
            {{-- Visual only: the control's own required (or aria-required) is what screen readers announce. A control
                 that can have neither, like the date pickers' buttons, sets announce-required to say it in the label. --}}
            @if ($required)
                <span class="text-error" aria-hidden="true">*</span>
                @if ($announceRequired)
                    <span class="sr-only">(required)</span>
                @endif
            @endif
        </{{ $labelsControl ? 'label' : 'div' }}>
    @endif

    {{-- Content between the label and the box, e.g. the stacked captcha's image. --}}
    @isset($before)
        {{ $before }}
    @endisset

    @if ($bare)
        {{ $slot }}
    @else
        <div @class([
            'bg-field text-foreground flex overflow-hidden rounded-[20px] border border-transparent transition-colors',
            $box,
            // Hover and focus both turn the border primary (7:1 against the page), with no ring around it: one edge,
            // never two. An invalid field keeps its red border instead (below). An open popup (calendar, list) keeps it
            // primary too: focus can leave the box, or never land on it (Safari doesn't focus a clicked button).
            'hover:border-primary focus-within:border-primary has-aria-expanded:border-primary' => ! $disabled && ! $readonly,
            'group-data-invalid/field:border-error group-data-invalid/field:bg-error/10 group-data-invalid/field:text-error',
            'group-data-invalid/field:hover:border-error group-data-invalid/field:focus-within:border-error group-data-invalid/field:focus-within:bg-field group-data-invalid/field:focus-within:text-foreground',
            'opacity-70' => $readonly,
        ])>
            {{ $slot }}
        </div>
    @endif

    {{-- Content that belongs under the box but above the messages, e.g. the password's requirements list. --}}
    @isset($after)
        {{ $after }}
    @endisset

    {{-- data-field-error: removed by the clear-on-edit script, which also brings the hint back. --}}
    @if (count($messages) > 1)
        <ul id="{{ $id }}-error" data-field-error class="text-error mt-1 list-disc ps-4 text-xs">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @elseif ($hasError)
        <div data-field-error class="mt-1 flex items-start gap-1">
            <x-widget.icon name="circle-alert" class="text-error mt-0.5 size-3.5" />
            <p id="{{ $id }}-error" class="text-error break-words">{{ $messages[0] }}</p>
        </div>
    @endif

    @if ($info)
        <div class="mt-1 flex items-start gap-1 group-data-invalid/field:hidden">
            <x-widget.icon name="info" class="text-foreground/70 mt-0.5 size-3.5" />
            <p id="{{ $id }}-info" data-field-info class="text-foreground/70 break-words">{{ $info }}</p>
        </div>
    @endif

    {{-- counter="160": characters used against the limit, kept up to date by resources/js/widget/field. --}}
    @if ($counter)
        <p data-field-counter data-max="{{ (int) $counter }}" class="text-foreground/70 mt-1 text-end text-xs tabular-nums">{{ $count }} / {{ (int) $counter }}</p>
    @endif
</div>
