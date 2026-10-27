@props([
    // Where toasts appear: top-left, top-center, top-right, center, bottom-left, bottom-center or bottom-right.
    'position' => 'top-right',
    // Milliseconds before a toast closes itself; false keeps it until dismissed. The rest of these props are defaults
    // too: a single toast can change them in its options.
    'autoClose' => 3000,
    // A bar along the bottom that shows the time left before it closes.
    'showProgress' => true,
    // Holds the timer while the pointer is over a toast.
    'pauseOnHover' => true,
    // Holds the timer while the window is in the background or the tab is hidden.
    'pauseOnFocusLoss' => true,
])

@php
    // 1rem from each edge, plus the notch or home indicator where the page is drawn under them (viewport-fit=cover, as
    // NativePHP and home-screen web apps do). env() is 0 everywhere else.
    $top = 'top-[calc(1rem+env(safe-area-inset-top))]';
    $bottom = 'bottom-[calc(1rem+env(safe-area-inset-bottom))]';
    $left = 'left-[calc(1rem+env(safe-area-inset-left))]';
    $right = 'right-[calc(1rem+env(safe-area-inset-right))]';
    $positions = [
        'top-left' => "{$top} {$left} flex-col",
        'top-center' => "{$top} left-1/2 -translate-x-1/2 flex-col",
        'top-right' => "{$top} {$right} flex-col",
        'center' => 'top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 flex-col',
        'bottom-left' => "{$bottom} {$left} flex-col-reverse",
        'bottom-center' => "{$bottom} left-1/2 -translate-x-1/2 flex-col-reverse",
        'bottom-right' => "{$bottom} {$right} flex-col-reverse",
    ];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($position, $positions)) {
        throw new \InvalidArgumentException("Unknown position [{$position}] for <x-widget.toast>. Use one of: ".implode(', ', array_keys($positions)).'.');
    }

    // Flash messages: ->with('success', '...'), or ->with('toast', ['type' => ..., 'message' => ..., 'title' => ...]).
    $flashTypes = ['success', 'error', 'warning', 'info'];
    $flashed = collect($flashTypes)
        ->filter(fn (string $type): bool => is_string(session($type)))
        ->map(fn (string $type): array => ['type' => $type, 'message' => session($type)])
        ->values()
        ->all();

    $custom = session('toast');
    if (is_array($custom) && is_string($custom['message'] ?? null)) {
        $flashed[] = [
            'type' => in_array($custom['type'] ?? null, [...$flashTypes, 'comingSoon'], true) ? $custom['type'] : 'info',
            'message' => $custom['message'],
            'title' => is_string($custom['title'] ?? null) ? $custom['title'] : null,
        ];
    }
@endphp

{{-- popover="manual" puts toasts in the browser's top layer, above page content and dropdowns. --}}
<div
    popover="manual"
    role="region"
    aria-label="Notifications"
    data-toast-container
    data-position="{{ $position }}"
    data-auto-close="{{ $autoClose === false ? 'false' : (int) $autoClose }}"
    data-show-progress="{{ $showProgress ? 'true' : 'false' }}"
    data-pause-on-hover="{{ $pauseOnHover ? 'true' : 'false' }}"
    data-pause-on-focus-loss="{{ $pauseOnFocusLoss ? 'true' : 'false' }}"
    {{-- Never taller than the screen: past what fits, the toasts scroll, so none is out of reach. The 0.5rem of padding
         (and as much negative margin, so nothing moves) keeps their shadows and slide-in from being clipped. --}}
    {{ $attributes->class([$positions[$position], 'pointer-events-none fixed inset-auto -m-2 max-h-[calc(100dvh-1rem-env(safe-area-inset-top)-env(safe-area-inset-bottom))] w-[min(25rem,calc(100vw-1rem))] gap-3 overflow-y-auto overscroll-contain border-0 bg-transparent p-2 [scrollbar-width:thin] open:flex']) }}
></div>

@if ($flashed)
    {{-- A fresh id per page: a copy of this page brought back later (wire:navigate's Back and Forward) carries the same
         one, and the script shows each id once. --}}
    <script type="application/json" data-toast-flash="{{ \Illuminate\Support\Str::random(16) }}">@json($flashed)</script>
@endif
