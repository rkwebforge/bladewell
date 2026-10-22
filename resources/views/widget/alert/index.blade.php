@props([
    // info, success, warning or error: sets the colours and the icon.
    'tone' => 'info',
    // A bold first line above the message.
    'title' => null,
    // A session key, e.g. "status": shows what was flashed under it, and nothing at all when nothing was.
    // Read out to screen readers when it appears (error and warning interrupt).
    'flash' => null,
    // An icon name in place of the tone's own; false for none.
    'icon' => null,
    // A close button that hides it for this page view; remember the choice on the server to keep it hidden.
    'dismissible' => false,
    // The close button's name for screen readers. Defaults to "Dismiss".
    'dismissLabel' => null,
])

@php
    // [box, icon colour, default icon]. Tints and text follow the table badge's mixes, so each tone stays AA.
    // Warning's yellow is too light to colour an icon on its own tint, so it's mixed towards the text colour.
    $tones = [
        'info' => ['border-link/25 bg-link/5', 'text-link', 'info'],
        'success' => ['border-success/30 bg-success/5', 'text-[color-mix(in_oklab,var(--color-success)_75%,var(--color-foreground))]', 'circle-check'],
        'warning' => ['border-warning/60 bg-warning/10', 'text-[color-mix(in_oklab,var(--color-warning)_60%,var(--color-foreground))]', 'triangle-alert'],
        'error' => ['border-error/30 bg-error/5', 'text-error', 'circle-alert'],
    ];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($tone, $tones)) {
        throw new \InvalidArgumentException("Unknown tone [{$tone}] for <x-widget.alert>. Use one of: ".implode(', ', array_keys($tones)).'.');
    }
    [$box, $iconColour, $defaultIcon] = $tones[$tone];
    // An icon name swaps the tone's icon; false drops it.
    $icon = $icon === false ? null : (is_string($icon) && $icon !== '' ? $icon : $defaultIcon);

    // flash="status" shows session('status'), the key Laravel's own auth screens use. It reads a key of its
    // own on purpose: 'success', 'error' and friends belong to the toast, and would otherwise show twice.
    $flashed = $flash !== null ? session($flash) : null;
    $message = is_string($flashed) && $flashed !== '' ? $flashed : null;
    $hasBody = $slot->isNotEmpty() || $message !== null;
    // A flash alert with nothing flashed renders nothing, so it can sit in a layout permanently.
    $render = $flash === null || $message !== null;
    // Only a message that arrives after an action is announced; a banner that's always on the page isn't news.
    // resources/js/widget/alert reads it out: a live region that already holds its text when the page loads is
    // announced by no screen reader for role=status and only by some for role=alert.
    $announce = $flash === null ? null : (in_array($tone, ['error', 'warning'], true) ? 'alert' : 'status');
    $dismissLabel ??= 'Dismiss';
@endphp

@if ($render)
    <div
        data-alert
        @if ($announce) data-announce="{{ $announce }}" @endif
        {{ $attributes->class(['flex gap-3 rounded-2xl border p-4 text-sm transition-opacity duration-200 data-leaving:opacity-0 motion-reduce:transition-none', $box]) }}
    >
        @if ($icon)
            <x-widget.icon :name="$icon" @class(['mt-px size-5', $iconColour]) />
        @endif

        <div class="min-w-0 flex-1">
            @if ($title)
                <p class="text-foreground font-semibold">{{ $title }}</p>
            @endif
            @if ($hasBody)
                <div @class(['text-foreground/80 leading-6', 'mt-1' => $title])>{{ $slot->isNotEmpty() ? $slot : $message }}</div>
            @endif
            {{-- Links or buttons that act on the message: "Add a card", "Undo". --}}
            @isset($actions)
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 font-medium">{{ $actions }}</div>
            @endisset
        </div>

        @if ($dismissible)
            {{-- Hides it for this page view. To keep it hidden, remember the choice on the server. --}}
            <button type="button" data-alert-dismiss aria-label="{{ $dismissLabel }}" class="text-foreground/70 hover:text-foreground hover:bg-foreground/5 focus-visible:ring-primary tap-target relative -m-1.5 grid size-8 shrink-0 place-items-center rounded-full outline-none focus-visible:ring-2">
                <x-widget.icon name="x" class="size-4" />
            </button>
        @endif
    </div>
@endif
