@props([
    // When it ends: a date string ("2026-12-31 23:59") in the app's timezone, or a Carbon/DateTime instance.
    'to',
    // Small text above the boxes (not shown inline), and the countdown's name for screen readers.
    'label' => 'Time left',
    // Shown in place of the numbers, and announced, once it reaches zero.
    'done' => "Time's up",
    // boxes: a card per unit. inline: "2d 04h 12m 09s" in running text.
    'variant' => 'boxes',
    // How big the numbers are: sm, md or lg.
    'size' => 'md',
])

@php
    $end = \Illuminate\Support\Carbon::parse($to, config('app.timezone'));
    $now = now();
    // Rounded up, as the script does, so the first tick in the browser doesn't jump a second up.
    $left = max(0, (int) ceil($now->diffInSeconds($end, false)));
    // Days only when there are any to begin with, so the layout doesn't shift as it runs.
    $withDays = $left >= 86400;
    $units = [
        'days' => intdiv($left, 86400),
        'hours' => intdiv($left % 86400, 3600),
        'minutes' => intdiv($left % 3600, 60),
        'seconds' => $left % 60,
    ];
    if (! $withDays) {
        unset($units['days']);
    }
    $names = ['days' => ['Days', 'd'], 'hours' => ['Hours', 'h'], 'minutes' => ['Minutes', 'm'], 'seconds' => ['Seconds', 's']];
    $pad = fn (string $unit, int $n): string => $unit === 'days' ? (string) $n : str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    // Spoken without the zero units ("13 seconds left", not "0 hours, 0 minutes, 13 seconds left").
    $spoken = collect($units)->filter()->whenEmpty(fn ($c) => $c->put('seconds', 0))->map(fn (int $n, string $unit): string => $n.' '.($n === 1 ? rtrim($unit, 's') : $unit))->implode(', ');
    // Phones share the width between the boxes, so md and lg step down a size there.
    $sizes = ['sm' => 'text-xl', 'md' => 'text-2xl sm:text-3xl', 'lg' => 'text-4xl sm:text-5xl'];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($variant, ['boxes', 'inline'], true)) {
        throw new \InvalidArgumentException("Unknown variant [{$variant}] for <x-widget.clock.countdown>. Use one of: boxes, inline.");
    }
    if (! array_key_exists($size, $sizes)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.clock.countdown>. Use one of: ".implode(', ', array_keys($sizes)).'.');
    }
    $inline = $variant === 'inline';
    $numbers = $sizes[$size];
@endphp

{{-- data-now is the server's clock, so a visitor whose device clock is off still sees the right time left. --}}
{{-- Inline, everything is a span: it sits inside running text, and a div would end the paragraph. --}}
<{{ $inline ? 'span' : 'div' }}
    data-clock="countdown"
    data-to="{{ $end->getTimestampMs() }}"
    data-now="{{ $now->getTimestampMs() }}"
    data-done="{{ $done }}"
    @if ($left === 0) data-finished @endif
    {{ $attributes->class(['group/countdown', 'inline-flex flex-col gap-2 max-sm:flex max-sm:w-full' => $variant !== 'inline', 'inline' => $variant === 'inline']) }}
>
    @if ($variant !== 'inline' && $label)
        <span class="text-foreground/70 text-xs">{{ $label }}</span>
    @endif

    {{-- role="timer" without aria-live: not announced every second. The words below read when reached. --}}
    <{{ $inline ? 'span' : 'div' }} role="timer" @if ($label) aria-label="{{ $label }}" @endif class="group-data-finished/countdown:hidden">
        <span data-clock-spoken class="sr-only">{{ $spoken }} left</span>
        @if ($variant === 'inline')
            <span aria-hidden="true" class="text-foreground font-semibold tabular-nums">
                @foreach ($units as $unit => $n)
                    <span data-clock-unit="{{ $unit }}">{{ $pad($unit, $n) }}</span>{{ $names[$unit][1] }}
                @endforeach
            </span>
        @else
            <div aria-hidden="true" class="flex gap-2 max-sm:gap-1.5">
                @foreach ($units as $unit => $n)
                    <div class="bg-field flex min-w-16 flex-col items-center rounded-xl px-3 py-2 max-sm:min-w-0 max-sm:flex-1 max-sm:px-1">
                        <span data-clock-unit="{{ $unit }}" class="{{ $numbers }} text-foreground leading-tight font-semibold tabular-nums">{{ $pad($unit, $n) }}</span>
                        <span class="text-foreground/70 text-xs">{{ $names[$unit][0] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </{{ $inline ? 'span' : 'div' }}>

    {{-- Always present and empty until the end: a status region only announces text added while it is on the page. --}}
    <{{ $inline ? 'span' : 'p' }} role="status" data-clock-done class="text-foreground font-medium">{{ $left === 0 ? $done : '' }}</{{ $inline ? 'span' : 'p' }}>
</{{ $inline ? 'span' : 'div' }}><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
