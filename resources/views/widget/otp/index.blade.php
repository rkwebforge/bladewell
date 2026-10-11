@props([
    // What it submits as; its error and old input are found under it. Optional with wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // How many boxes.
    'length' => 6,
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // The value to show. Old input wins after a failed submit; with wire:model and no value, the bound Livewire
    // property.
    'value' => null,
    // Shown in each empty box.
    'placeholder' => '0',
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the boxes.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be changed.
    'disabled' => false,
    // Shown, and submitted, but it can't be changed. With disabled, disabled wins.
    'readonly' => false,
    // Focuses the first box when the page loads.
    'autofocus' => false,
    // filled, outline or underline.
    'variant' => 'filled',
    // Splits the boxes into groups of this size (3: 123 456).
    'group' => null,
    // Hides each character as it's typed, like a password.
    'masked' => false,
    // Letters and digits (shown in capitals) instead of digits only.
    'alphanumeric' => false,
])

@php
    $readonly = $readonly && ! $disabled;
    $length = max(1, (int) $length);
    // Resolved twice: once for the base id the boxes share (otp-0, otp-1 …), once for the first box,
    // which the frame keys its label and messages off.
    $baseId = \Bladewell\Support\FormField::make($name, $id, null, idPrefix: 'otp', attributes: $attributes)->id;
    $field = \Bladewell\Support\FormField::make($name, "{$baseId}-0", $errors ?? null, $error, $bag, attributes: $attributes);
    // alphanumeric takes letters too (shown in capitals), for codes like A7K2QX; otherwise digits only.
    $allowed = $alphanumeric ? '/[^A-Za-z0-9]/' : '/\D/';
    $value = strtoupper(substr((string) preg_replace($allowed, '', (string) $field->old($value)), 0, $length));
    $digits = array_map(fn (int $i): string => $value[$i] ?? '', range(0, $length - 1));
    $aria = $field->aria($attributes, (bool) $info);
    // group="3" puts a dash after every third box (123 – 456), which is easier to read and copy.
    $group = $group !== null && (int) $group > 0 && (int) $group < $length ? (int) $group : null;
    $variants = [
        // Hover and focus turn the border primary, like the other fields.
        'filled' => 'bg-field rounded-[14px] border border-transparent hover:border-primary focus:border-primary',
        'outline' => 'border-line-strong rounded-[14px] border bg-transparent hover:border-primary focus:border-primary',
        'underline' => 'border-line-strong rounded-none border-0 border-b-2 bg-transparent hover:border-primary focus:border-primary',
    ];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($variant, $variants)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.otp>. Use one of: ".implode(', ', array_keys($variants)).'.');
    }
    $look = $variants[$variant];
@endphp

<x-widget.field :required="$attributes->has('required')" :id="$field->id" :label="$label" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" bare :class="$attributes->get('class')">
    {{-- The boxes have no name; the hidden input carries the joined code on submit, with the caller's attributes. --}}
    {{-- dir="ltr": a code reads left to right in every language, so the boxes and the arrow keys do too. --}}
    <div data-otp dir="ltr" @if ($alphanumeric) data-alphanumeric @endif role="group" aria-label="{{ $label ?? 'One-time code' }}" class="flex w-full items-start gap-2 sm:gap-3">
        @foreach ($digits as $i => $digit)
            @if ($group && $i > 0 && $i % $group === 0)
                <span aria-hidden="true" class="text-muted grid shrink-0 place-items-center self-center text-xl">–</span>
            @endif
            <div class="aspect-square max-w-14 min-w-0 flex-1">
                <input
                    type="{{ $masked ? 'password' : 'text' }}"
                    id="{{ $baseId }}-{{ $i }}"
                    value="{{ $digit }}"
                    placeholder="{{ $placeholder }}"
                    inputmode="{{ $alphanumeric ? 'text' : 'numeric' }}"
                    @unless ($alphanumeric) pattern="[0-9]*" @endunless
                    autocomplete="{{ $i === 0 && ! $masked ? 'one-time-code' : 'off' }}"
                    aria-label="{{ $alphanumeric ? 'Character' : 'Digit' }} {{ $i + 1 }} of {{ $length }}"
                    @if ($alphanumeric) autocapitalize="characters" @endif
                    data-otp-box
                    @disabled($disabled)
                    @readonly($readonly)
                    @if ($autofocus && $i === 0) autofocus @endif
                    {{ $aria->class([
                        'text-foreground h-full w-full text-center text-[clamp(18px,5vw,24px)] font-medium uppercase outline-none transition-colors placeholder:text-muted disabled:cursor-not-allowed disabled:opacity-50 read-only:opacity-70',
                        $look,
                        'text-[clamp(28px,8vw,40px)] leading-none' => $masked,
                        'group-data-invalid/field:border-error group-data-invalid/field:bg-error/10 group-data-invalid/field:text-error group-data-invalid/field:hover:border-error group-data-invalid/field:focus:border-error',
                    ]) }}
                >
            </div>
        @endforeach

        <input type="hidden" @if ($name) name="{{ $name }}" @endif value="{{ $value }}" data-otp-value @disabled($disabled) {{ $field->forwarded($attributes) }}>
    </div>
</x-widget.field>
