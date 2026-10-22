@props([
    // Required: an IANA timezone like "Europe/London".
    'timezone',
    // 12-hour time with AM or PM, instead of 24-hour.
    'hour12' => false,
    // Shows the seconds as well.
    'seconds' => false,
    // A line under the time: "Friday, 27 September".
    'date' => false,
    // Small text above the time, e.g. the city.
    'label' => null,
    // How big the time is: sm, md or lg.
    'size' => 'md',
])

@php
    // The first paint comes from the server so there's no empty flash; resources/js/widget/clock takes over.
    // Required, so the server paints the same time the browser will; pass the user's own for "their" time.
    if (! isset($timezone) || ! is_string($timezone) || $timezone === '') {
        throw new \InvalidArgumentException('<x-widget.clock> needs a timezone, e.g. timezone="Europe/London" or :timezone="$user->timezone".');
    }
    $now = now($timezone);
    $sizes = ['sm' => 'text-lg', 'md' => 'text-3xl', 'lg' => 'text-5xl'];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($size, $sizes)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.clock>. Use one of: ".implode(', ', array_keys($sizes)).'.');
    }
@endphp

<div
    data-clock="digital"
    data-timezone="{{ $timezone }}"
    @if ($hour12) data-hour12 @endif
    @if ($seconds) data-seconds @endif
    {{ $attributes->class(['inline-flex flex-col gap-1']) }}
>
    @if ($label)
        <span class="text-foreground/70 text-xs">{{ $label }}</span>
    @endif

    {{-- Not a live region: announcing every second would drown out everything else. It reads when reached. --}}
    <time datetime="{{ $now->toIso8601String() }}" class="{{ $sizes[$size] }} text-foreground leading-none font-semibold tracking-tight tabular-nums">
        <span data-clock-time>{{ $now->format($hour12 ? 'g:i' : 'H:i') }}{{ $seconds ? $now->format(':s') : '' }}</span>
        @if ($hour12)
            <span data-clock-period class="text-[0.45em] font-medium tracking-normal">{{ $now->format('A') }}</span>
        @endif
    </time>

    @if ($date)
        <span data-clock-date class="text-foreground/70 text-sm">{{ $now->format('l, j F') }}</span>
    @endif
</div><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
