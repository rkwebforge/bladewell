@props([
    // ['London' => 'Europe/London', 'Tokyo' => 'Asia/Tokyo'], or a list of ['label' => …, 'timezone' => …].
    'zones' => [],
    // 12-hour time with AM or PM, instead of 24-hour.
    'hour12' => false,
    // The list's name for screen readers.
    'label' => 'World clocks',
])

@php
    $zones = collect($zones)->map(fn (mixed $zone, int|string $key): array => is_array($zone)
        ? ['label' => (string) $zone['label'], 'timezone' => (string) $zone['timezone']]
        : ['label' => (string) $key, 'timezone' => (string) $zone])->values();

    // Offsets and "tomorrow" are relative to the visitor, whom only the browser knows; the server's first
    // paint uses the app's timezone and resources/js/widget/clock corrects it straight away.
    $here = now(config('app.timezone'));
    $relative = function (\Carbon\CarbonInterface $there) use ($here): string {
        $minutes = $there->utcOffset() - $here->utcOffset();
        if ($minutes === 0) {
            return 'Same time';
        }
        $sign = $minutes > 0 ? '+' : '−';
        $minutes = abs($minutes);

        return $sign.intdiv($minutes, 60).'h'.($minutes % 60 ? ' '.($minutes % 60).'m' : '');
    };
    $day = fn (\Carbon\CarbonInterface $there): ?string => match ($there->toDateString() <=> $here->toDateString()) {
        1 => 'Tomorrow',
        -1 => 'Yesterday',
        default => null,
    };
@endphp

<ul aria-label="{{ $label }}" {{ $attributes->class(['grid gap-3 sm:grid-cols-[repeat(auto-fit,minmax(10rem,1fr))]']) }}>
    @foreach ($zones as $zone)
        @php
            $there = now($zone['timezone']);
            $night = $there->hour < 6 || $there->hour >= 18;
            $dayHint = $day($there);
        @endphp
        <li
            data-clock="world"
            data-timezone="{{ $zone['timezone'] }}"
            @if ($hour12) data-hour12 @endif
            class="border-line bg-surface flex items-start justify-between gap-3 rounded-2xl border p-4"
        >
            <div class="flex min-w-0 flex-col gap-1">
                <span class="text-foreground truncate text-sm font-medium">{{ $zone['label'] }}</span>
                <time datetime="{{ $there->toIso8601String() }}" class="text-foreground text-2xl leading-none font-semibold tabular-nums">
                    <span data-clock-time>{{ $there->format($hour12 ? 'g:i' : 'H:i') }}</span>
                    @if ($hour12)
                        <span data-clock-period class="text-xs font-medium">{{ $there->format('A') }}</span>
                    @endif
                </time>
                <span class="text-foreground/70 text-xs">
                    <span data-clock-offset>{{ $relative($there) }}</span><span data-clock-day>{{ $dayHint ? ', '.$dayHint : '' }}</span>
                </span>
            </div>
            {{-- Day or night there (6:00 to 18:00 counts as day); the time already says it for screen readers. --}}
            <span data-clock-daylight aria-hidden="true" class="text-foreground/50 shrink-0">
                <x-widget.icon name="sun" :class="$night ? 'size-5 hidden' : 'size-5'" data-clock-sun />
                <x-widget.icon name="moon" :class="$night ? 'size-5' : 'size-5 hidden'" data-clock-moon />
            </span>
        </li>
    @endforeach
</ul><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
