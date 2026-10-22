@props([
    // stopwatch counts up from zero; timer counts down from duration.
    'mode' => 'stopwatch',
    // Timer length in seconds, e.g. 1500 for 25 minutes.
    'duration' => 60,
    // Small text above the time, and its name for screen readers.
    'label' => null,
    // Announced, and shown, when a timer reaches zero.
    'done' => "Time's up",
    // How big the time is: sm, md or lg.
    'size' => 'md',
])

@php
    $sizes = ['sm' => 'text-2xl', 'md' => 'text-4xl', 'lg' => 'text-6xl'];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($mode, ['stopwatch', 'timer'], true)) {
        throw new \InvalidArgumentException("Unknown mode [{$mode}] for <x-widget.clock.stopwatch>. Use one of: stopwatch, timer.");
    }
    if (! array_key_exists($size, $sizes)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.clock.stopwatch>. Use one of: ".implode(', ', array_keys($sizes)).'.');
    }
    $timer = $mode === 'timer';
    $duration = max(1, (int) $duration);
    // Same format as resources/js/widget/clock: m:ss (h:mm:ss past an hour), plus tenths on a stopwatch.
    $start = $timer
        ? ($duration >= 3600 ? sprintf('%d:%02d:%02d', intdiv($duration, 3600), intdiv($duration % 3600, 60), $duration % 60) : sprintf('%d:%02d', intdiv($duration, 60), $duration % 60))
        : '0:00.0';
    $numbers = $sizes[$size];
@endphp

{{-- wire:ignore: all of it is the browser's (running or not, the time, the button's label), and a Livewire render would put
     back the server's 0:00.0 and Start, stopping it. So its props are read once, when it first appears. --}}
<div
    wire:ignore
    data-clock="{{ $timer ? 'timer' : 'stopwatch' }}"
    @if ($timer) data-duration="{{ $duration }}" data-done="{{ $done }}" @endif
    {{ $attributes->class(['group/clock inline-flex flex-col items-center gap-3']) }}
>
    @if ($label)
        <span class="text-foreground/70 text-xs">{{ $label }}</span>
    @endif

    {{-- Not a live region; it reads when reached. The status line below announces the end of a timer. --}}
    <div role="timer" @if ($label) aria-label="{{ $label }}" @endif data-clock-display class="{{ $numbers }} text-foreground leading-none font-semibold tracking-tight tabular-nums group-data-finished/clock:text-error">{{ $start }}</div>

    <div class="flex gap-2">
        <x-widget.button size="sm" data-clock-toggle class="min-w-24">
            <x-widget.icon name="play" class="size-4 group-data-running/clock:hidden" />
            <x-widget.icon name="pause" class="hidden size-4 group-data-running/clock:block" />
            <span data-clock-toggle-label>Start</span>
        </x-widget.button>
        <x-widget.button size="sm" variant="neutral" data-clock-reset>
            <x-widget.icon name="rotate-ccw" class="size-4" />
            Reset
        </x-widget.button>
    </div>

    <p role="status" data-clock-done class="text-error text-sm font-medium"></p>
</div><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
