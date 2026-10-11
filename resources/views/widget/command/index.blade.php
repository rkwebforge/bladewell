@props([
    // The palette's id: anything with data-command-open="{id}" opens it, and so does command.open('{id}'). Made up when
    // left out.
    'id' => null,
    // Its name for screen readers, and the trigger button's text.
    'label' => 'Search',
    // The search box's placeholder.
    'placeholder' => 'Type a command or search…',
    // The key that opens it with Ctrl (Cmd on a Mac): Ctrl+K by default. false for none.
    'shortcut' => 'k',
    // A button that opens it, styled like a search box, with the shortcut on it. false to open it only by the shortcut or
    // your own data-command-open element.
    'trigger' => true,
    // Your app's search, asked as people type (GET ?q=…, JSON): the same answer the search widget's suggest-url takes.
    // Its results are listed after the items that match.
    'suggestUrl' => null,
    // Keeps the last few items chosen, in this browser only (localStorage), and shows them before anything is typed.
    'recent' => false,
    // What it says when nothing matches.
    'empty' => 'No results',
])

@php
    // What other widgets call these: say what this one calls them, rather than let them through as plain attributes
    // that do nothing.
    foreach (['search-url' => 'suggest-url', 'no-results' => 'empty'] as $other => $own) {
        if ($attributes->hasAny([$other, \Illuminate\Support\Str::camel($other)])) {
            throw new \InvalidArgumentException("<x-widget.command> calls it {$own}, not {$other}.");
        }
    }
    // Scripts open it by its id, so an explicit id must be unique; a derived one gets a suffix.
    $id = app(\Bladewell\Support\ElementIds::class)->claim($id ?? 'command', explicit: $id !== null);
    $shortcut = $shortcut === false || $shortcut === null ? null : strtolower((string) $shortcut);
@endphp

@if ($trigger)
    {{-- Looks like a search box, but it's a button: the box is in the dialog. --}}
    <button
        type="button"
        data-command-open="{{ $id }}"
        aria-haspopup="dialog"
        {{ $attributes->class(['bg-field text-foreground/60 hover:text-foreground focus-visible:ring-primary inline-flex h-10 min-w-56 items-center gap-2 rounded-xl px-3 text-sm outline-none transition-colors focus-visible:ring-2']) }}
    >
        <x-widget.icon name="search" class="size-4 shrink-0" />
        <span class="flex-1 text-start">{{ $label }}</span>
        @if ($shortcut)
            {{-- Ctrl here; the script shows ⌘ on a Mac. --}}
            <kbd data-command-key="{{ $shortcut }}" class="border-line bg-surface text-foreground/60 rounded-md border px-1.5 py-0.5 font-sans text-xs">Ctrl {{ strtoupper($shortcut) }}</kbd>
        @endif
    </button>
@endif

{{--
    A native modal <dialog>: the browser keeps focus inside it, closes it on Esc and puts focus back where it was. The
    search box is a combobox: focus stays in it while the arrow keys move the highlight through the options
    (aria-activedescendant), as in any search-as-you-type list. resources/js/widget/command filters, fetches and
    activates.
    wire:ignore.self: whether it's open is the person's; a Livewire render would put back the server's and close it.
--}}
<dialog
    id="{{ $id }}"
    data-command
    wire:ignore.self
    aria-label="{{ $label }}"
    @if ($shortcut) data-shortcut="{{ $shortcut }}" @endif
    @if ($suggestUrl) data-suggest-url="{{ $suggestUrl }}" @endif
    @if ($recent) data-recent @endif
    class="group fixed inset-0 m-0 h-dvh max-h-none w-full max-w-none bg-transparent px-3 pt-[12dvh] outline-none open:flex open:flex-col open:items-center backdrop:bg-foreground/40"
>
    <div class="bg-surface text-foreground flex max-h-[min(32rem,76dvh)] w-full max-w-xl flex-col overflow-hidden rounded-2xl shadow-xl">
        <div class="border-line flex items-center gap-2 border-b px-4">
            <x-widget.icon name="search" class="text-foreground/60 size-5 shrink-0" />
            <input
                type="text"
                role="combobox"
                aria-expanded="true"
                aria-controls="{{ $id }}-list"
                aria-autocomplete="list"
                aria-label="{{ $label }}"
                autocomplete="off"
                spellcheck="false"
                enterkeyhint="go"
                placeholder="{{ $placeholder }}"
                data-command-input
                class="placeholder:text-muted h-14 min-w-0 flex-1 bg-transparent text-base outline-none"
            >
            <span data-command-busy hidden class="border-line border-t-primary size-4 shrink-0 animate-spin rounded-full border-2"></span>
            <kbd aria-hidden="true" class="border-line text-foreground/60 hidden rounded-md border px-1.5 py-0.5 font-sans text-xs sm:inline">Esc</kbd>
        </div>

        <div id="{{ $id }}-list" role="listbox" aria-label="{{ $label }}" data-command-list class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2 [scrollbar-width:thin]">
            {{-- Filled by the script: recent choices before anything is typed, then your app's results. --}}
            <div role="group" aria-label="Recent" data-command-recent hidden></div>
            {{ $slot }}
            <div role="group" aria-label="Results" data-command-results hidden></div>
        </div>

        <p data-command-empty hidden class="text-muted px-4 py-8 text-center text-sm">{{ $empty }}</p>
        <p role="status" data-command-status class="sr-only"></p>
    </div>
</dialog>
