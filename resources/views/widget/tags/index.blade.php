@props([
    // What the tags submit as (name[]); their error and old input are found under it. Optional with wire:model, which
    // then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Shown above the field, and its name for screen readers.
    'label' => null,
    // The tags to start with. Old input wins after a failed submit; with wire:model and no value, the bound property.
    'value' => null,
    // Shown in the box while there are no tags and nothing is typed.
    'placeholder' => 'Add a tag',
    // Tags to suggest as people type (the browser's own list). Any other text can still be added.
    'suggestions' => [],
    // How many tags it takes at most. Past that, typing more is refused and said so.
    'max' => null,
    // An error message of your own; otherwise the validation error for the name, from the session or Livewire.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be changed.
    'disabled' => false,
    // Shown, and submitted, but it can't be changed: no typing, and no remove buttons. With disabled, disabled wins.
    'readonly' => false,
    // Where the tags show: inline, in the box before the text; or below, as cards under a one-line box, for a long list.
    'layout' => 'inline',
])

@php
    $readonly = $readonly && ! $disabled;
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($layout, ['inline', 'below'], true)) {
        throw new \InvalidArgumentException("Unknown layout [{$layout}] for <x-widget.tags>. Use one of: inline, below.");
    }
    $below = $layout === 'below';
    // inline: tinted pills in the box. below: cards under it, their remove button turning red as it's pointed at.
    $chipClass = $below
        ? 'border-line bg-surface text-foreground inline-flex max-w-full items-center gap-1 rounded-xl border py-1 ps-3 pe-1 text-sm shadow-xs'
        : 'bg-primary/10 text-primary inline-flex max-w-full items-center gap-1 rounded-full py-1 ps-3 pe-1 text-sm';
    $removeClass = $below
        ? 'text-foreground/70 hover:bg-error/10 hover:text-error focus-visible:ring-primary grid size-6 place-items-center rounded-full outline-none focus-visible:ring-2'
        : 'hover:bg-primary/20 focus-visible:ring-primary grid size-6 place-items-center rounded-full outline-none focus-visible:ring-2';
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'tags', attributes: $attributes);
    $tags = collect(\Illuminate\Support\Arr::wrap($field->old($value)))
        ->map(fn (mixed $tag): string => trim((string) ($tag instanceof \BackedEnum ? $tag->value : $tag)))
        ->filter(fn (string $tag): bool => $tag !== '')
        ->unique(fn (string $tag): string => mb_strtolower($tag))
        ->values();
    $suggestions = collect($suggestions)->map(fn (mixed $tag): string => (string) $tag)->unique()->values();
    // The box's own text goes nowhere: what submits is the hidden select of tags below, so the bindings go there.
    $bindings = $field->bindings($attributes);
    $inputAttributes = $field->forwarded($attributes)->except([...array_keys($bindings->getAttributes()), 'required'])
        ->merge($field->aria($attributes, (bool) $info)->getAttributes());
    $required = $attributes->has('required');
@endphp

{{--
    Tags as chips in the box, each with its remove button, and the text box after them: Enter or a comma adds what's
    typed, a pasted list adds each item, and Backspace in the empty box removes the last tag. resources/js/widget/tags
    keeps the hidden select, which is what submits and what wire:model binds, in step with the chips.
--}}
<x-widget.field :id="$field->id" :label="$label" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" :required="$required" :box="$below ? 'h-12 items-center px-4' : 'min-h-12 flex-wrap items-center gap-1.5 px-3 py-2'" :class="$attributes->get('class')">
    @unless ($below)
        <ul data-tags-list @if ($label) aria-label="{{ $label }}" @endif class="contents">
            @foreach ($tags as $tag)
                <li data-tags-chip data-value="{{ $tag }}" class="{{ $chipClass }}">
                    <span class="truncate">{{ $tag }}</span>
                    @unless ($readonly)
                        <button type="button" data-tags-remove aria-label="Remove {{ $tag }}" @disabled($disabled) class="{{ $removeClass }}"><x-widget.icon name="x" class="size-3.5" /></button>
                    @endunless
                </li>
            @endforeach
        </ul>
    @endunless

    <input
        type="text"
        id="{{ $field->id }}"
        data-tags-input
        @if ($max) data-max="{{ (int) $max }}" @endif
        @if ($suggestions->isNotEmpty()) list="{{ $field->id }}-suggestions" @endif
        @if ($required) aria-required="true" @endif
        @unless ($readonly) placeholder="{{ $placeholder }}" @endunless
        autocomplete="off"
        enterkeyhint="enter"
        @disabled($disabled)
        @readonly($readonly)
        {{ $inputAttributes->class(['placeholder:text-muted h-8 min-w-32 flex-1 bg-transparent ps-1 outline-none disabled:cursor-not-allowed disabled:opacity-50']) }}
    >

    @if ($suggestions->isNotEmpty())
        <datalist id="{{ $field->id }}-suggestions">
            @foreach ($suggestions as $suggestion)
                <option value="{{ $suggestion }}"></option>
            @endforeach
        </datalist>
    @endif

    {{-- The tags as they submit (name[]), and what wire:model binds. --}}
    <select multiple hidden data-tags-value @if ($name) name="{{ $name }}[]" @endif @disabled($disabled) {{ $bindings }}>
        @foreach ($tags as $tag)
            <option value="{{ $tag }}" selected>{{ $tag }}</option>
        @endforeach
    </select>

    {{-- A new chip, as the script makes one. --}}
    <template data-tags-template>
        <li data-tags-chip class="{{ $chipClass }}">
            <span class="truncate"></span>
            <button type="button" data-tags-remove class="{{ $removeClass }}"><x-widget.icon name="x" class="size-3.5" /></button>
        </li>
    </template>

    {{-- below: the list under the box, out of the way of the text, wrapping as it grows; nothing shows while it's empty. --}}
    @if ($below)
        <x-slot:after>
            <ul data-tags-list @if ($label) aria-label="{{ $label }}" @endif class="mt-3 flex flex-wrap gap-2 empty:hidden">@foreach ($tags as $tag)<li data-tags-chip data-value="{{ $tag }}" class="{{ $chipClass }}">
                    <span class="truncate">{{ $tag }}</span>
                    @unless ($readonly)
                        <button type="button" data-tags-remove aria-label="Remove {{ $tag }}" @disabled($disabled) class="{{ $removeClass }}"><x-widget.icon name="x" class="size-3.5" /></button>
                    @endunless
                </li>@endforeach</ul>
        </x-slot:after>
    @endif

    {{-- Says what was added or removed, or why something wasn't. --}}
    <span role="status" data-tags-status class="sr-only"></span>
</x-widget.field>
