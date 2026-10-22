@props([
    // What it submits as. Never filled back into the page, Livewire included. Optional with wire:model.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the field, and its name for screen readers.
    'label' => null,
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
    // Defaults to current-password, or new-password with new.
    'autocomplete' => null,
    // For choosing a password: new-password autocomplete and a live checklist of the rules below.
    'new' => false,
    // A button to show or hide what's typed.
    'toggle' => true,
    // With new: the minimum length in the checklist.
    'min' => 8,
    // With new: requires a letter, and lists it.
    'letters' => false,
    // With new: requires upper and lower case.
    'mixedCase' => false,
    // With new: requires a number.
    'numbers' => false,
    // With new: requires a symbol.
    'symbols' => false,
])

@php
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'password', attributes: $attributes);
    // new: creating a password (sign-up, reset). Password managers then offer to generate one instead of
    // filling an old one, and the requirements show as a checklist that ticks off while typing.
    $autocomplete ??= $new ? 'new-password' : 'current-password';

    // Mirror your Form Request's Password rule, e.g. Password::min(12)->mixedCase()->symbols() is
    // :min="12" mixed-case symbols. The server still decides; this only shows the same rules up front.
    $rules = $new ? array_filter([
        'min' => "At least {$min} characters",
        'letters' => $letters ? 'A letter' : null,
        'mixedCase' => $mixedCase ? 'Upper and lower case letters' : null,
        'numbers' => $numbers ? 'A number' : null,
        'symbols' => $symbols ? 'A symbol' : null,
    ]) : [];
    $rulesId = "{$field->id}-rules";
    // The checklist joins what screen readers announce for the field.
    $described = $rules === [] ? $attributes : new \Illuminate\View\ComponentAttributeBag([
        ...$attributes->getAttributes(),
        'aria-describedby' => trim($attributes->get('aria-describedby', '').' '.$rulesId),
    ]);
@endphp

{{-- No value prop and no old() on purpose: a password is never rendered back into the page. --}}
<x-widget.field :required="$attributes->has('required')" :id="$field->id" :label="$label" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" :class="$attributes->get('class')">
    <input
        type="password"
        id="{{ $field->id }}"
        @if ($name) name="{{ $name }}" @endif
        placeholder="{{ $placeholder }}"
        autocomplete="{{ $autocomplete }}"
        data-password-input
        @disabled($disabled)
        @readonly($readonly)
        {{ $field->controlAttributes($described, (bool) $info)->class([
            'h-full w-full min-w-0 bg-transparent ps-5 outline-none placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-50 read-only:cursor-default',
            'group-data-invalid/field:placeholder:text-error group-data-invalid/field:focus:placeholder:text-muted',
        ]) }}
    >

    {{-- Shown by resources/js/widget/password while the field has focus and Caps Lock is on. --}}
    {{-- A live region is only announced if it was already on the page, so this one is always there and the script
         only changes its text (from data-message). Empty, it takes no space. --}}
    <span data-caps-lock role="status" data-message="Caps Lock is on" class="bg-warning/25 text-foreground ms-2 shrink-0 rounded-md px-2 py-0.5 text-xs whitespace-nowrap empty:m-0 empty:bg-transparent empty:p-0"></span>

    @if ($toggle)
    <button
        type="button"
        data-password-toggle
        aria-controls="{{ $field->id }}"
        aria-pressed="false"
        aria-label="Show password"
        @disabled($disabled)
        class="text-foreground/70 hover:text-foreground focus-visible:ring-primary tap-target-y relative me-3 grid size-8 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2 disabled:opacity-50"
    >
        <x-widget.icon name="eye" class="size-4.5" data-icon-show />
        <x-widget.icon name="eye-off" class="hidden size-4.5" data-icon-hide />
    </button>
    @endif

    @if ($rules !== [])
        <x-slot:after>
            {{-- resources/js/widget/password sets data-met as each rule is satisfied. --}}
            <ul id="{{ $rulesId }}" data-password-rules class="mt-2 grid gap-1 text-xs">
                @foreach ($rules as $rule => $text)
                    <li data-password-rule="{{ $rule }}" @if ($rule === 'min') data-min="{{ (int) $min }}" @endif class="group/rule text-foreground/70 data-met:text-success flex items-center gap-1.5">
                        <span aria-hidden="true" class="border-line-strong size-3.5 shrink-0 rounded-full border group-data-met/rule:hidden"></span>
                        <x-widget.icon name="check" class="hidden size-3.5 group-data-met/rule:block" />
                        <span>{{ $text }}</span>
                        <span data-password-rule-status class="sr-only"></span>
                    </li>
                @endforeach
            </ul>
        </x-slot:after>
    @endif
</x-widget.field>
