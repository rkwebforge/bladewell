@props([
    // What it submits as; its error and old input are found under it. Optional with wire:model, which then names it.
    'name' => null,
    // Defaults to one made from the name (or the wire:model property).
    'id' => null,
    // Defaults to the query string under the name, so a search form fills back in; or the bound Livewire property.
    'value' => null,
    // Shown while it's empty.
    'placeholder' => 'Search',
    // Its name for screen readers; defaults to the placeholder.
    'label' => null,
    // sm (32px) or md (48px).
    'size' => 'md',
    // An × button, shown while there's text, that empties the box. It fires input and change, so whatever listens (a
    // table's filters, wire:model) reacts as if the text had been deleted.
    'clearable' => true,
    // Where the magnifier sits: end (after the text) or start (before it).
    'iconPosition' => 'end',
    // A key that jumps to the box from anywhere on the page, e.g. "/", shown as a hint while the box is empty and
    // unfocused. One character, without modifiers; it doesn't fire while you're typing in another field.
    'shortcut' => null,
    // Suggestions as you type, like a store's search: a URL that answers GET ?q=… with JSON, a list or {"data": [...]}. Each
    // suggestion is a string, or ['label' => …, 'value' => what fills the box, 'meta' => small text at the end,
    // 'href' => a page to open instead, 'group' => a heading to list it under].
    'suggestUrl' => null,
    // How many characters to type before asking for suggestions.
    'suggestMin' => 2,
    // Rewords what screen readers hear about the suggestions, by key: count (:count), none, failed.
    'messages' => [],
])

@php
    // What other widgets call these: say what this one calls them, rather than let them through as plain attributes
    // that do nothing.
    foreach (['search-url' => 'suggest-url', 'search-min-length' => 'suggest-min'] as $other => $own) {
        if ($attributes->hasAny([$other, \Illuminate\Support\Str::camel($other)])) {
            throw new \InvalidArgumentException("<x-widget.search> calls it {$own}, not {$other}.");
        }
    }
    $field = \Bladewell\Support\FormField::make($name, $id, null, idPrefix: 'search', attributes: $attributes);
    $id = $field->id;
    // Search forms submit with GET, so the current query string is the natural default (dot key: filter[q] works too).
    $fromQuery = $field->key !== null ? request()->query($field->key) : null;
    // Otherwise the value prop, or a bound Livewire property (see FormField::old).
    $value = is_string($fromQuery) ? $fromQuery : $field->old($value);
    $sizes = ['sm' => 'h-8 text-xs', 'md' => 'h-12 text-sm'];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($size, $sizes)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.search>. Use one of: ".implode(', ', array_keys($sizes)).'.');
    }
    if (! in_array($iconPosition, ['end', 'start'], true)) {
        throw new \InvalidArgumentException("Unknown icon-position [{$iconPosition}] for <x-widget.search>. Use one of: end, start.");
    }
    $start = $iconPosition === 'start';
    $shortcut = is_string($shortcut) && mb_strlen($shortcut) === 1 && trim($shortcut) !== '' ? $shortcut : null;
    // Plain English like every component; pass messages="[…]" to reword or translate any of them.
    $messages = [
        'count' => ':count suggestions. Use the arrow keys to choose one.',
        'none' => 'No suggestions.',
        'failed' => 'Couldn\'t load suggestions.',
        ...$messages,
    ];
    $suggest = $suggestUrl !== null && $suggestUrl !== '';
@endphp

<div {{ $attributes->only('class')->class([
    'bg-field text-foreground hover:border-primary focus-within:border-primary relative flex w-full items-center overflow-hidden rounded-[20px] border border-transparent transition-colors',
    $sizes[$size],
    'pe-3' => $start,
]) }}>
    @if ($start)
        <x-widget.icon name="search" class="text-muted ms-4 size-4 shrink-0" />
    @endif
    <input
        type="search"
        id="{{ $id }}"
        @if ($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        {{-- A space when there's no placeholder: the clear button hides on :placeholder-shown, which needs one. --}}
        placeholder="{{ $placeholder !== '' ? $placeholder : ' ' }}"
        aria-label="{{ $label ?? $placeholder }}"
        @if ($shortcut) aria-keyshortcuts="{{ $shortcut }}" data-search-shortcut="{{ $shortcut }}" @endif
        {{-- With suggestions it's a combobox: the list below is its popup, and the arrow keys move through it while focus
             stays in the box (aria-activedescendant). The browser's own autofill list would cover it. --}}
        @if ($suggest)
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="{{ $id }}-suggestions"
            autocomplete="off"
            data-search-suggest="{{ $suggestUrl }}"
            data-suggest-min="{{ max(1, (int) $suggestMin) }}"
            data-suggest-messages="{{ json_encode($messages) }}"
        @endif
        {{ $attributes->except('class')->class([
            'peer h-full w-full min-w-0 bg-transparent outline-none placeholder:text-muted [&::-webkit-search-cancel-button]:appearance-none',
            $start ? 'ps-3' : 'ps-5',
        ]) }}
    >
    @if ($clearable)
        {{-- Hidden by CSS while the box is empty (its placeholder showing), so it needs no script to stay right, even
             when something else empties the box. The browser's own clear button is hidden above: it differs in every
             browser and Firefox has none. --}}
        <button type="button" data-search-clear aria-label="Clear search" aria-controls="{{ $id }}" @class([
            'text-muted hover:text-foreground focus-visible:ring-primary grid shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2 peer-placeholder-shown:hidden',
            'me-1 size-6' => $size === 'sm',
            'me-2 size-7' => $size === 'md',
        ])>
            <x-widget.icon name="x" @class(['size-3.5' => $size === 'sm', 'size-4' => $size === 'md']) />
        </button>
    @endif
    @if ($shortcut)
        {{-- The key, until the box has focus or text; screen readers get it from aria-keyshortcuts instead. --}}
        <kbd aria-hidden="true" @class([
            'border-line-strong text-muted font-code grid shrink-0 place-items-center rounded-md border text-xs peer-focus:hidden peer-[:not(:placeholder-shown)]:hidden',
            'me-2 size-5' => $size === 'sm',
            'me-3 size-6' => $size === 'md',
        ])>{{ $shortcut }}</kbd>
    @endif
    @unless ($start)
        <x-widget.icon name="search" class="text-muted me-5 size-4 shrink-0" />
    @endunless
    @if ($suggest)
        {{-- In the top layer (a popover), so the box's rounded clip and a table or modal can't cut it off; the script
             places it under the box and fills it. wire:ignore: a Livewire render would empty it while it's open. --}}
        <div id="{{ $id }}-suggestions" role="listbox" aria-label="Suggestions" popover="manual" wire:ignore data-search-suggestions class="border-line bg-surface text-foreground fixed inset-auto m-0 max-h-80 overflow-y-auto overscroll-contain rounded-2xl border p-1.5 text-sm shadow-lg [scrollbar-width:thin]"></div>
        {{-- What a screen reader hears as suggestions come and go. --}}
        <p data-search-status role="status" class="sr-only"></p>
        {{-- One suggestion and one group heading, filled in by the script. Here rather than in the JS so you can restyle
             them in the Blade you own. The typed text is shown in bold within each label. --}}
        <template data-search-suggestion>
            <div role="option" aria-selected="false" class="aria-selected:bg-field flex cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2">
                <span data-suggestion-label class="text-foreground/80 min-w-0 truncate [&_mark]:text-foreground [&_mark]:bg-transparent [&_mark]:font-semibold"></span>
                <span data-suggestion-meta class="text-muted shrink-0 text-xs empty:hidden"></span>
            </div>
        </template>
        <template data-search-group>
            <div role="group" class="not-first:border-line not-first:mt-1 not-first:border-t not-first:pt-1">
                <div data-group-label class="text-muted px-3 pt-1.5 pb-1 text-xs font-medium"></div>
            </div>
        </template>
    @endif
</div>
