@props([
    // What it submits as; its error and old input are found under it. Optional with wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // The full international number (+14155550123). Old input wins; with wire:model and no value, the bound
    // property.
    'value' => null,
    // The country to start with (US); otherwise guessed from the locale.
    'country' => null,
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
    // The language for country names and that guess; defaults to the app's.
    'locale' => null,
])

@php
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'phone', attributes: $attributes);
    $locale = str_replace('_', '-', $locale ?? app()->getLocale());
    // The stored value is one international number (+60123456789); it opens on its own country.
    [$selected, $national] = \Bladewell\Support\Countries::split($field->old($value), \Bladewell\Support\Countries::guess($country, $locale));
    $dial = \Bladewell\Support\Countries::DIAL_CODES[$selected];
    $flag = implode('', array_map(static fn (string $letter): string => mb_chr(0x1F1E6 + ord($letter) - 65), str_split($selected)));
@endphp

{{--
    Submits one international number, +60123456789, from the hidden input: validate it on the server,
    e.g. with propaganistas/laravel-phone ('phone' => 'phone:INTERNATIONAL'). The country list is built in
    the browser (resources/js/widget/phone), with names in the page's language.
--}}
<x-widget.field :required="$attributes->has('required')" data-phone data-locale="{{ $locale }}" data-countries="{{ \Bladewell\Support\Countries::compact() }}" :id="$field->id" :label="$label" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" box="h-12 items-center" :class="$attributes->get('class')">
    <button
        type="button"
        popovertarget="{{ $field->id }}-countries"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-label="Country code +{{ $dial }}"
        data-phone-country
        @disabled($disabled || $readonly)
        class="border-line hover:bg-line/40 focus-visible:ring-primary flex h-full shrink-0 items-center gap-1.5 border-e ps-5 pe-3 outline-none focus-visible:ring-2 focus-visible:ring-inset disabled:cursor-not-allowed disabled:opacity-50"
    >
        <span data-phone-flag aria-hidden="true" class="text-base leading-none">{{ $flag }}</span>
        <span data-phone-dial class="tabular-nums">+{{ $dial }}</span>
        <x-widget.icon name="chevron-down" class="text-foreground/70 size-4" />
    </button>

    <input
        type="tel"
        id="{{ $field->id }}"
        value="{{ $national }}"
        placeholder="{{ $placeholder }}"
        inputmode="tel"
        autocomplete="tel-national"
        data-phone-national
        @disabled($disabled)
        @readonly($readonly)
        {{-- The visible field gets required, autofocus and the like, so the browser checks it and screen readers hear it. --}}
        {{ $field->visibleAttributes($attributes, (bool) $info)->class([
            'h-full w-full min-w-0 bg-transparent ps-3 pe-5 tabular-nums outline-none placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-50 read-only:cursor-default',
            'group-data-invalid/field:placeholder:text-error group-data-invalid/field:focus:placeholder:text-muted',
        ]) }}
    >

    <input type="hidden" @if ($name) name="{{ $name }}" @endif value="{{ \Bladewell\Support\Countries::international($selected, $national) }}" data-phone-value data-country="{{ $selected }}" {{ $field->bindings($attributes) }}>

    <div id="{{ $field->id }}-countries" popover data-phone-popover class="border-line bg-surface text-foreground fixed inset-auto m-0 overflow-hidden rounded-2xl border p-0 text-sm shadow-lg">
        <div class="border-line border-b p-2">
            {{-- A fixed id: Livewire's morph matches elements by id, and a generated one would swap in a box without the script's listeners. --}}
            <x-widget.search :id="$field->id.'-search'" size="sm" placeholder="Search countries or codes" data-phone-search role="searchbox" aria-controls="{{ $field->id }}-country-list" autocomplete="off" />
        </div>
        <div id="{{ $field->id }}-country-list" role="listbox" aria-label="Country" data-phone-list class="flex max-h-72 flex-col overflow-y-auto overscroll-contain [scrollbar-width:thin]"></div>
        <p data-phone-empty hidden class="text-muted px-5 py-2">No country matches</p>
    </div>
</x-widget.field>
