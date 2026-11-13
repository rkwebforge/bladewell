@props([
    // Required: names the tabs, their panels and their place in the URL (with remember). Unique on the page.
    'id',
    // Names the tab list for screen readers ("Account settings").
    'label' => null,
    // key => label, or key => ['label' => …, 'icon' => …, 'badge' => …, 'disabled' => bool, 'href' => …]. Each tab's
    // panel is the named slot of the same key: <x-slot:profile>…</x-slot:profile>. With href on every tab they're links
    // to other pages instead, and there are no panels.
    'tabs' => [],
    // The open tab's key; the first that isn't disabled by default. With wire:model, the bound property.
    'value' => null,
    // underline, tinted (underlined, the open tab on a wash of the primary colour), pills, segmented (a track with the
    // open tab raised in it), or vertical (stacked beside the panel).
    'variant' => 'underline',
    // Keep the open tab in the URL (#settings=security), so a link, a reload or Back opens it.
    'remember' => false,
    // What the tabs submit as, with a form around them: the open tab's key. Optional with wire:model.
    'name' => null,
])

@php
    $field = \Bladewell\Support\FormField::make($name, $id, null, idPrefix: 'tabs', attributes: $attributes);
    $id = $field->id;
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($variant, ['underline', 'tinted', 'pills', 'segmented', 'vertical'], true)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.tabs>. Use one of: underline, tinted, pills, segmented, vertical.");
    }
    $vertical = $variant === 'vertical';
    $items = collect($tabs)->map(static fn (mixed $tab, int|string $key): array => [
        'key' => (string) $key,
        'label' => (string) (is_array($tab) ? ($tab['label'] ?? $key) : $tab),
        'icon' => is_array($tab) ? ($tab['icon'] ?? null) : null,
        'badge' => is_array($tab) && isset($tab['badge']) && $tab['badge'] !== '' ? (string) $tab['badge'] : null,
        'disabled' => is_array($tab) && ! empty($tab['disabled']),
        'href' => is_array($tab) ? ($tab['href'] ?? null) : null,
    ])->values();
    // Links when every tab has an href: navigation between pages, so a <nav> of links, not a tab list.
    $links = $items->isNotEmpty() && $items->every(static fn (array $tab): bool => $tab['href'] !== null);
    $chosen = (string) ($field->old($value) ?? '');
    $selected = $items->first(static fn (array $tab): bool => $tab['key'] === $chosen && ! $tab['disabled'])['key']
        ?? $items->first(static fn (array $tab): bool => ! $tab['disabled'])['key']
        ?? null;
    // Bound with wire:model, the server's property says which tab is open, so a render may change it. Otherwise the
    // person does, and renders leave their choice alone (wire:ignore.self below).
    $bound = \Bladewell\Support\FormField::binding($attributes) !== [];
    // Each key as an id part. Two keys can come out alike ('a b' and 'a-b'): the second gets -2, so every tab and panel
    // keeps its own id and points at its own partner. Worked out here, in key order, so a render gives the same ids.
    $slugs = [];
    foreach ($items as $item) {
        $base = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $item['key']), '-');
        $candidate = $base;
        for ($n = 2; in_array($candidate, $slugs, true); $n++) {
            $candidate = "{$base}-{$n}";
        }
        $slugs[$item['key']] = $candidate;
    }
    $slug = static fn (string $key): string => $slugs[$key];

    $list = [
        'underline' => 'border-line flex gap-6 border-b',
        'tinted' => 'border-line flex gap-1 border-b',
        'pills' => 'flex gap-1.5',
        'segmented' => 'bg-field inline-flex gap-1 rounded-2xl p-1',
        'vertical' => 'border-line flex flex-col gap-0.5 border-s',
    ][$variant];
    $tab = [
        'underline' => '-mb-px border-b-2 border-transparent py-3 text-foreground/70 hover:text-foreground aria-selected:border-primary aria-selected:text-foreground aria-[current=page]:border-primary aria-[current=page]:text-foreground',
        // Underlined and tinted: the open tab on a light wash of the primary colour, as in the vertical tabs, its underline
        // in the primary colour.
        'tinted' => '-mb-px rounded-t-xl border-b-2 border-transparent px-4 py-3 text-foreground/70 hover:text-foreground hover:not-aria-selected:not-aria-[current=page]:bg-field/70 aria-selected:border-primary aria-selected:bg-primary/5 aria-selected:text-foreground aria-[current=page]:border-primary aria-[current=page]:bg-primary/5 aria-[current=page]:text-foreground',
        'pills' => 'rounded-full px-4 py-2 text-foreground/70 hover:bg-field hover:text-foreground aria-selected:bg-primary-fill aria-selected:text-on-primary aria-[current=page]:bg-primary-fill aria-[current=page]:text-on-primary',
        'segmented' => 'rounded-xl px-4 py-2 text-foreground/70 hover:text-foreground aria-selected:bg-surface aria-selected:text-foreground aria-selected:shadow-sm aria-[current=page]:bg-surface aria-[current=page]:text-foreground aria-[current=page]:shadow-sm',
        'vertical' => '-ms-px border-s-2 border-transparent px-4 py-2 text-start text-foreground/70 hover:text-foreground aria-selected:border-primary aria-selected:bg-primary/5 aria-selected:text-foreground aria-[current=page]:border-primary aria-[current=page]:bg-primary/5 aria-[current=page]:text-foreground',
    ][$variant];
    $tabBase = 'focus-visible:ring-primary inline-flex shrink-0 items-center gap-2 font-medium whitespace-nowrap outline-none transition-colors select-none focus-visible:ring-2 aria-disabled:cursor-not-allowed aria-disabled:opacity-40 '.$tab;
@endphp

{{--
    The ARIA tabs pattern: one tab in the Tab order (the open one), the arrow keys move between them, Home and End jump
    to the ends, and each panel is labelled by its tab. Panels are on the page from the server, the closed ones hidden,
    so it works before (and without) the script, which only switches them. A long row of tabs scrolls sideways.
--}}
<div
    data-tabs="{{ $id }}"
    data-variant="{{ $variant }}"
    @if ($remember && ! $links) data-tabs-remember @endif
    {{ $attributes->whereDoesntStartWith('wire:model')->whereDoesntStartWith('x-model')->except(['form'])->class(['flex gap-6' => $vertical, 'flex flex-col gap-5' => ! $vertical]) }}
>
    @if ($links)
        <nav @if ($label) aria-label="{{ $label }}" @endif class="max-w-full overflow-x-auto overscroll-x-contain [scrollbar-width:thin]">
            <div @class([$list, 'w-max min-w-full' => ! $vertical && $variant !== 'segmented'])>
                @foreach ($items as $item)
                    <a
                        href="{{ $item['href'] }}"
                        @if ($item['key'] === $selected) aria-current="page" @endif
                        @if ($item['disabled']) aria-disabled="true" tabindex="-1" @endif
                        class="{{ $tabBase }}"
                    >
                        @if ($item['icon'])
                            <x-widget.icon :name="$item['icon']" class="size-4 shrink-0" />
                        @endif
                        <span>{{ $item['label'] }}</span>
                        @if ($item['badge'] !== null)
                            <span class="bg-foreground/10 rounded-full px-2 py-0.5 text-xs leading-none tabular-nums">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </nav>
    @else
        <div @class(['max-w-full overflow-x-auto overscroll-x-contain [scrollbar-width:thin]' => ! $vertical, 'shrink-0' => $vertical])>
            <div
                role="tablist"
                @if ($label) aria-label="{{ $label }}" @endif
                @if ($vertical) aria-orientation="vertical" @endif
                data-tabs-list
                @class([$list, 'w-max min-w-full' => ! $vertical && $variant !== 'segmented'])
            >
                @foreach ($items as $item)
                    @php($open = $item['key'] === $selected)
                    {{-- wire:ignore.self, unless bound: which tab is open is the person's, and a render would put back the
                         server's. The label and badge inside still update. --}}
                    <button
                        type="button"
                        role="tab"
                        id="{{ $id }}-tab-{{ $slug($item['key']) }}"
                        aria-controls="{{ $id }}-panel-{{ $slug($item['key']) }}"
                        aria-selected="{{ $open ? 'true' : 'false' }}"
                        tabindex="{{ $open ? '0' : '-1' }}"
                        @if ($item['disabled']) aria-disabled="true" @endif
                        data-tab="{{ $item['key'] }}"
                        @unless ($bound) wire:ignore.self @endunless
                        class="{{ $tabBase }}"
                    >
                        @if ($item['icon'])
                            <x-widget.icon :name="$item['icon']" class="size-4 shrink-0" />
                        @endif
                        <span>{{ $item['label'] }}</span>
                        @if ($item['badge'] !== null)
                            <span class="bg-foreground/10 rounded-full px-2 py-0.5 text-xs leading-none tabular-nums">{{ $item['badge'] }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        <div @class(['min-w-0 flex-1' => $vertical])>
            @foreach ($items as $item)
                @php($open = $item['key'] === $selected)
                <div
                    role="tabpanel"
                    id="{{ $id }}-panel-{{ $slug($item['key']) }}"
                    aria-labelledby="{{ $id }}-tab-{{ $slug($item['key']) }}"
                    {{-- Focusable, so a panel with nothing focusable in it can still be reached with Tab. --}}
                    tabindex="0"
                    data-tab-panel="{{ $item['key'] }}"
                    @unless ($open) hidden @endunless
                    @unless ($bound) wire:ignore.self @endunless
                    class="focus-visible:ring-primary rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-offset-4 focus-visible:ring-offset-surface"
                >{{ $__laravel_slots[$item['key']] ?? '' }}</div>
            @endforeach
        </div>

        {{-- The open tab's key, for a form around the tabs and for wire:model / x-model. --}}
        @if ($name || $bound)
            <input type="hidden" @if ($name) name="{{ $name }}" @endif value="{{ $selected }}" data-tabs-input {{ $field->bindings($attributes) }}>
        @endif
    @endif
</div>
