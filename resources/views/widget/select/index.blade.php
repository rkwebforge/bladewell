@props([
    // What the value submits as; with multiple, name[] (a trailing [] is added for you).
    'name' => null,
    // Defaults to one made from the name.
    'id' => null,
    // Shown above the field (or inside it, with inner-label), and its name for screen readers.
    'label' => null,
    // A list (['Red', 'Blue']), a value => label map, or rows: ['value' => 1, 'label' => 'Savings', 'meta' => '$120',
    // 'disabled' => true]; id and name work as value and label. meta shows at the end of the option's line, e.g. a count.
    // Every option is about 0.8 KB of HTML on the page: past about 100, use search-url and send only the chosen one(s).
    'options' => [],
    // The chosen value (an array with multiple). Old input wins after a failed submit. With wire:model and no value,
    // it comes from the bound Livewire property.
    'value' => null,
    // Shown while nothing is chosen.
    'placeholder' => '',
    // An error message of your own; otherwise the validation error for the name, from the session.
    'error' => null,
    // A hint under the field.
    'info' => null,
    // Which error bag to read the error from.
    'bag' => 'default',
    // Greyed out: it can't be opened or changed (its value still submits).
    'disabled' => false,
    // Shown, and submitted, but it can't be changed: the list doesn't open. With disabled, disabled wins.
    'readonly' => false,
    // A search box at the top of the list, once there are at least search-min options.
    'searchable' => false,
    // How many options it takes before searchable shows the search box.
    'searchMin' => 12,
    // The search box's placeholder.
    'searchPlaceholder' => 'Search',
    // Shown when the search matches nothing.
    'noResults' => 'No results',
    // Searches your app instead of the options on the page, for lists too long to send: a URL that answers GET ?q=… with
    // JSON, a list or {"data": [...]}, each option a string or ['value' => 7, 'label' => 'Ana Silva', 'meta' => …]. Pass the
    // chosen option(s) in options, so the field shows them before any search; any others there show until you type.
    'searchUrl' => null,
    // With search-url: how many characters to type before asking.
    'searchMinLength' => 2,
    // With search-url: shown, and announced, while a search runs.
    'searching' => 'Searching…',
    // With search-url: shown, and announced, when a search fails.
    'searchFailed' => 'Couldn\'t search. Try again.',
    // The label inside the field, above the value, instead of above the field.
    'innerLabel' => false,
    // Choose several: the list stays open and each pick toggles an option.
    'multiple' => false,
    // An × button that clears the choice.
    'clearable' => false,
    // With multiple, how the choices show: list (labels joined), chips (removable) or count ("3 selected").
    'display' => 'list',
    // display="count": the text, with :count for the number.
    'countLabel' => ':count selected',
    // With required: the message when nothing is chosen.
    'requiredMessage' => 'Choose an option.',
])

@php
    $readonly = $readonly && ! $disabled;
    // Nothing to clear when it can't be changed.
    $clearable = $clearable && ! $readonly;
    // What other widgets call these: say what this one calls them, rather than let them through as plain attributes
    // that do nothing.
    foreach (['suggest-url' => 'search-url', 'suggest-min' => 'search-min-length', 'empty' => 'no-results'] as $other => $own) {
        if ($attributes->hasAny([$other, \Illuminate\Support\Str::camel($other)])) {
            throw new \InvalidArgumentException("<x-widget.select> calls it {$own}, not {$other}.");
        }
    }
    $field = \Bladewell\Support\FormField::make($name, $id, $errors ?? null, $error, $bag, 'select', attributes: $attributes);
    $id = $field->id;
    // multiple submits name[] (one hidden input per choice); a single select submits name. name="tags" and
    // name="tags[]" both mean tags[], so a trailing [] isn't doubled into tags[][].
    $inputName = $multiple && $name !== null ? preg_replace('/\[\]$/', '', $name) : $name;
    // Flattened, so old input that arrived nested (from a page that still sent tags[][]) can't crash the render.
    // Enum values (model casts) submit their backing value.
    $selectedValues = array_map(
        static fn (mixed $v): string => (string) ($v instanceof \BackedEnum ? $v->value : $v),
        array_values(array_filter(
            \Illuminate\Support\Arr::flatten(\Illuminate\Support\Arr::wrap($field->old($value))),
            static fn (mixed $v): bool => $v instanceof \BackedEnum || (is_scalar($v) && $v !== ''),
        )),
    );
    if (! $multiple) {
        $selectedValues = array_slice($selectedValues, 0, 1);
    }
    $selectedValue = $selectedValues[0] ?? null;

    // Accepts ['a', 'b'], ['value' => 'Label'], or rows like ['id' => 1, 'name' => 'X', 'formattedBalance' => '…'].
    $isList = array_is_list($options);
    $items = collect($options)->map(function (mixed $option, int|string $key) use ($isList): array {
        if (is_array($option)) {
            $optionValue = (string) ($option['value'] ?? $option['id'] ?? $key);

            return [
                'value' => $optionValue,
                'label' => (string) ($option['label'] ?? $option['name'] ?? $optionValue),
                'meta' => $option['meta'] ?? $option['formattedBalance'] ?? null,
                'disabled' => ! empty($option['disabled']),
            ];
        }

        return ['value' => (string) ($isList ? $option : $key), 'label' => (string) $option, 'meta' => null, 'disabled' => false];
    })->values();

    $chosen = $items->whereIn('value', $selectedValues)->values();
    $selected = $multiple ? null : $chosen->first();
    // One label, or several joined for multiple (the full list is also the tooltip).
    $shown = $chosen->isNotEmpty() ? $chosen->pluck('label')->implode(', ') : null;
    // How a multiple select shows its choices: list (joined), chips (one removable chip each) or count ("3 selected").
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($display, ['list', 'chips', 'count'], true)) {
        throw new \InvalidArgumentException("Unknown display [{$display}] for <x-widget.select>. Use one of: list, chips, count.");
    }
    $display = $multiple ? $display : 'list';
    $visible = $display === 'count' && $chosen->count() > 1 ? str_replace(':count', (string) $chosen->count(), $countLabel) : $shown;
    // A short list doesn't need a search box, same threshold as the React version. Searching the server always does.
    $remote = $searchUrl !== null && $searchUrl !== '';
    $showSearch = $remote || ($searchable && $items->count() >= (int) $searchMin);
    // One look for every option, the server's and those a search brings (the template below).
    $optionClass = 'flex shrink-0 cursor-pointer items-center justify-between gap-3 px-5 py-2 transition-colors duration-150 select-none aria-disabled:cursor-not-allowed aria-disabled:opacity-40 aria-selected:bg-primary/10 data-active:bg-field aria-selected:data-active:bg-primary/15';
@endphp

{{-- data-required-message: the value is in hidden inputs, which the browser never validates, so resources/js/widget/field
     stops an empty submit itself and shows this. --}}
<x-widget.field :required="$attributes->has('required')" :data-required-message="$attributes->has('required') && ! $disabled ? $requiredMessage : false" data-select :data-multiple="$multiple ? 'true' : false" :data-search-url="$remote ? $searchUrl : false" :data-search-min-length="$remote ? max(1, (int) $searchMinLength) : false" :data-display="$display" :data-count-label="$display === 'count' ? $countLabel : false" :id="$id" :label="$innerLabel ? null : $label" :error="$field->errors" :info="$info" :disabled="$disabled" :readonly="$readonly" box="relative min-h-12 items-stretch" :class="$attributes->get('class')">
    @if ($display === 'chips')
        {{-- Chips sit above the trigger, which fills the box behind them: clicking any empty spot opens the list,
             and only the chips' remove buttons take clicks of their own (a button can't sit inside the trigger). --}}
        <div data-select-chips @class(['pointer-events-none relative z-10 flex min-w-0 flex-1 flex-wrap items-center gap-1.5 py-2.5 ps-3', 'pe-24' => $clearable, 'pe-14' => ! $clearable])>
            @foreach ($chosen as $item)
                <span data-select-chip class="bg-primary/10 text-primary inline-flex max-w-full items-center gap-1 rounded-full py-1 ps-3 pe-1 text-sm">
                    <span data-select-chip-label class="truncate">{{ $item['label'] }}</span>
                    <button type="button" data-select-chip-remove data-value="{{ $item['value'] }}" aria-label="Remove {{ $item['label'] }}" @disabled($disabled) class="hover:bg-primary/20 focus-visible:ring-primary pointer-events-auto grid size-6 place-items-center rounded-full outline-none focus-visible:ring-2"><x-widget.icon name="x" class="size-3.5" /></button>
                </span>
            @endforeach
            <span data-select-chips-placeholder @if ($chosen->isNotEmpty()) hidden @endif class="text-muted ps-2">{{ $placeholder }}</span>
        </div>
        <template data-select-chip-template>
            <span data-select-chip class="bg-primary/10 text-primary inline-flex max-w-full items-center gap-1 rounded-full py-1 ps-3 pe-1 text-sm">
                <span data-select-chip-label class="truncate"></span>
                <button type="button" data-select-chip-remove class="hover:bg-primary/20 focus-visible:ring-primary pointer-events-auto grid size-6 place-items-center rounded-full outline-none focus-visible:ring-2"><x-widget.icon name="x" class="size-3.5" /></button>
            </span>
        </template>
    @endif

    {{-- The trigger opens the list natively via popovertarget; the JS adds keyboard, search and positioning. --}}
    <button
        type="button"
        id="{{ $id }}"
        role="combobox"
        popovertarget="{{ $id }}-popup"
        aria-haspopup="listbox"
        aria-expanded="false"
        aria-controls="{{ $id }}-listbox"
        @if ($attributes->has('required')) aria-required="true" @endif
        @if ($innerLabel && $label) aria-label="{{ $label }}" @endif
        {{ $field->aria($attributes, (bool) $info) }}
        @disabled($disabled)
        @if ($readonly) aria-readonly="true" @endif
        data-select-trigger
        @class([
            'group/trigger flex min-w-0 text-start outline-none disabled:cursor-not-allowed disabled:opacity-70',
            'w-full flex-col justify-center gap-1 px-5 py-3' => $display !== 'chips',
            'focus-visible:ring-primary absolute inset-0 items-center justify-end rounded-[20px] pe-5 focus-visible:ring-2 focus-visible:ring-inset' => $display === 'chips',
        ])
    >
        @if ($innerLabel && $label && $display !== 'chips')
            <span @class(['font-semibold', 'text-muted' => $disabled])>{{ $label }}</span>
        @endif

        <span @class(['flex items-center justify-between gap-2.5', 'w-full' => $display !== 'chips'])>
            {{-- With chips, this is the value screen readers hear; the chips show it visually. --}}
            <span
                data-select-display
                data-placeholder="{{ $placeholder }}"
                @if (! $shown) data-empty @endif
                @if ($shown) title="{{ $shown }}" @endif
                @class(['data-empty:text-muted group-data-invalid/field:data-empty:text-error min-w-0 truncate', 'sr-only' => $display === 'chips'])
            >{{ ($display === 'chips' ? $shown : $visible) ?? $placeholder }}</span>

            <span class="flex shrink-0 items-center gap-2.5">
                <span data-select-meta class="text-foreground/70 text-xs whitespace-nowrap">{{ $selected['meta'] ?? '' }}</span>
                {{-- Room for the clear button, which sits over this gap (it can't go inside the trigger). --}}
                @if ($clearable)
                    <span class="size-7" aria-hidden="true"></span>
                @endif
                <x-widget.icon name="chevron-down" @class(['size-5 transition-transform group-aria-expanded/trigger:-rotate-180', 'opacity-40' => $disabled]) />
            </span>
        </span>
    </button>

    {{-- Sits beside the trigger rather than in it: a button inside a button isn't valid HTML. --}}
    @if ($clearable)
        {{-- Centred on the value's line: the box's middle, or with an inner label, half the label's line (and gap) lower. --}}
        <button type="button" data-select-clear aria-label="Clear selection" @if (! $shown) hidden @endif @disabled($disabled)
            @class([
                'text-foreground/70 hover:text-foreground focus-visible:ring-primary tap-target-y absolute end-[3.125rem] top-1/2 z-20 grid size-7 place-items-center rounded-full outline-none focus-visible:ring-2',
                '-translate-y-1/2' => ! ($innerLabel && $label && $display !== 'chips'),
                'translate-y-[calc(-50%+0.75rem)]' => $innerLabel && $label && $display !== 'chips',
            ])>
            <x-widget.icon name="x" class="size-4" />
        </button>
    @endif

    @if ($multiple)
        {{-- One hidden input per choice, rebuilt by the script; nothing is sent when none is chosen. --}}
        <div data-select-values data-name="{{ $inputName }}" @if ($attributes->has('form')) data-form="{{ $attributes->get('form') }}" @endif>
            @foreach ($selectedValues as $chosenValue)
                <input type="hidden" @if ($inputName) name="{{ $inputName }}[]" @endif value="{{ $chosenValue }}" {{ $attributes->only(['form']) }}>
            @endforeach
        </div>
        @if ($field->bindings($attributes)->except(['form'])->isNotEmpty())
            {{-- wire:model and x-model need one element that holds the whole array: a hidden native multi-select, kept
                 in step by the script. Unnamed, so the form still submits the inputs above, once. --}}
            <select multiple hidden tabindex="-1" aria-hidden="true" data-select-model {{ $field->bindings($attributes)->except(['form']) }}>
                @foreach ($items as $item)
                    <option value="{{ $item['value'] }}" @selected(in_array($item['value'], $selectedValues, true))></option>
                @endforeach
            </select>
        @endif
    @else
        <input type="hidden" @if ($name) name="{{ $name }}" @endif value="{{ $selectedValue }}" data-select-input {{ $field->forwarded($attributes)->except(['required']) }}>
    @endif

    <div
        id="{{ $id }}-popup"
        popover
        data-select-popup
        class="border-line bg-surface text-foreground fixed inset-auto m-0 overflow-hidden rounded-2xl border p-0 text-sm font-medium shadow-lg"
    >
        @if ($showSearch)
            <div class="border-line border-b p-2">
                {{-- A fixed id, not a generated one: Livewire's morph matches elements by id, and a new id each render
                     would swap in a fresh box without the script's listeners. --}}
                <x-widget.search
                    :id="$id.'-search'"
                    size="sm"
                    :placeholder="$searchPlaceholder"
                    data-select-search
                    role="searchbox"
                    aria-controls="{{ $id }}-listbox"
                    aria-autocomplete="list"
                    autocomplete="off"
                />
            </div>
        @endif

        <div
            id="{{ $id }}-listbox"
            role="listbox"
            @if ($multiple) aria-multiselectable="true" @endif
            aria-label="{{ $label ?: $placeholder }}"
            class="flex max-h-96 flex-col overflow-y-auto overscroll-contain [scrollbar-width:thin]"
        >
            @foreach ($items as $i => $item)
                <div
                    id="{{ $id }}-option-{{ $i }}"
                    role="option"
                    data-value="{{ $item['value'] }}"
                    data-label="{{ $item['label'] }}"
                    data-meta="{{ $item['meta'] }}"
                    title="{{ $item['label'] }}"
                    aria-selected="{{ in_array($item['value'], $selectedValues, true) ? 'true' : 'false' }}"
                    @if ($item['disabled']) aria-disabled="true" @endif
                    class="{{ $optionClass }}"
                {{-- The meta (a balance, a count) at the end of the line; an empty span without one, so it can be filled in later. --}}
                ><span data-select-option-label class="min-w-0 truncate">{{ $item['label'] }}</span><span data-select-option-meta class="text-foreground/70 shrink-0 text-xs tabular-nums empty:hidden">{{ $item['meta'] }}</span></div>
            @endforeach

            <div data-select-empty data-no-results="{{ $noResults }}" @if ($remote) data-searching="{{ $searching }}" data-search-failed="{{ $searchFailed }}" @endif @if ($items->isNotEmpty()) hidden @endif class="text-muted px-5 py-2">{{ $noResults }}</div>
        </div>
        @if ($remote)
            {{-- What a screen reader hears as a search runs, finds or fails. --}}
            <p data-select-status role="status" class="sr-only"></p>
            {{-- An option a search brings, filled in by the script: the same look as the server's, kept here so you can
                 restyle both in the Blade you own. --}}
            <template data-select-option-template>
                <div role="option" aria-selected="false" class="{{ $optionClass }}"><span data-select-option-label class="min-w-0 truncate"></span><span data-select-option-meta class="text-foreground/70 shrink-0 text-xs tabular-nums empty:hidden"></span></div>
            </template>
        @endif
    </div>
</x-widget.field>
