@props([
    // Required: an IANA timezone like "Europe/London".
    'timezone',
    // Draws a seconds hand.
    'seconds' => true,
    // Puts 12, 3, 6 and 9 on the face.
    'numbers' => false,
    // A caption under the face, e.g. the city.
    'label' => null,
    // How big the face is: sm, md or lg.
    'size' => 'md',
])

@php
    // Required, so the server paints the same time the browser will; pass the user's own for "their" time.
    if (! isset($timezone) || ! is_string($timezone) || $timezone === '') {
        throw new \InvalidArgumentException('<x-widget.clock.analog> needs a timezone, e.g. timezone="Europe/London" or :timezone="$user->timezone".');
    }
    $now = now($timezone);
    $sizes = ['sm' => 'size-24', 'md' => 'size-36', 'lg' => 'size-52'];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($size, $sizes)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.clock.analog>. Use one of: ".implode(', ', array_keys($sizes)).'.');
    }
    // Hand angles, clockwise from 12. resources/js/widget/clock sets the same rotation every second.
    $angles = [
        'hour' => ($now->hour % 12) * 30 + $now->minute * 0.5,
        'minute' => $now->minute * 6 + $now->second * 0.1,
        'second' => $now->second * 6,
    ];
    // The SVG transform attribute, not style="": a Content Security Policy doesn't restrict it, and rotate(a 50 50)
    // turns the hand about the centre by itself.
    $hand = fn (string $name): string => "rotate({$angles[$name]} 50 50)";
    // AM/PM and the date in the app's locale, from ICU as the browser's Intl does it (resources/js/widget/clock), so the
    // first frame and the ticks agree word for word. Carbon's own translations differ (Chinese 晚上 for 下午). Without the
    // intl extension, English.
    $icuLocale = str_replace('-', '_', app()->getLocale());
    $icu = static function (\DateTimeInterface $at, string $pattern, string $fallback) use ($icuLocale): string {
        if (! class_exists(\IntlDateFormatter::class)) {
            return $at->format($fallback);
        }
        // A skeleton (letters in braces) becomes the locale's own pattern: Thursday, November 19 or 11月19日星期四.
        if (str_starts_with($pattern, '{')) {
            $pattern = (string) (new \IntlDatePatternGenerator($icuLocale))->getBestPattern(trim($pattern, '{}'));
        }

        return (string) (new \IntlDateFormatter($icuLocale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $at->getTimezone(), null, $pattern))->format($at) ?: $at->format($fallback);
    };
@endphp

<div
    data-clock="analog"
    data-locale="{{ str_replace('_', '-', app()->getLocale()) }}"
    data-timezone="{{ $timezone }}"
    {{ $attributes->class(['inline-flex flex-col items-center gap-2']) }}
>
    <svg viewBox="0 0 100 100" aria-hidden="true" class="{{ $sizes[$size] }}">
        <circle cx="50" cy="50" r="48" class="fill-surface stroke-line" stroke-width="2" />
        @for ($i = 0; $i < 12; $i++)
            {{-- Longer, darker marks at the quarters. --}}
            <line
                x1="50" y1="{{ $i % 3 === 0 ? 6 : 7 }}" x2="50" y2="{{ $i % 3 === 0 ? 13 : 10 }}"
                stroke-linecap="round" stroke-width="{{ $i % 3 === 0 ? 2.5 : 1.5 }}"
                class="{{ $i % 3 === 0 ? 'stroke-foreground' : 'stroke-muted' }}"
                transform="rotate({{ $i * 30 }} 50 50)"
            />
        @endfor
        @if ($numbers)
            @foreach ([12 => [50, 23], 3 => [79, 53.5], 6 => [50, 84], 9 => [21, 53.5]] as $n => [$x, $y])
                <text x="{{ $x }}" y="{{ $y }}" text-anchor="middle" class="fill-foreground text-[9px] font-semibold">{{ $n }}</text>
            @endforeach
        @endif

        <line data-clock-hand="hour" x1="50" y1="50" x2="50" y2="28" stroke-linecap="round" stroke-width="4" class="stroke-foreground" transform="{{ $hand('hour') }}" />
        <line data-clock-hand="minute" x1="50" y1="50" x2="50" y2="15" stroke-linecap="round" stroke-width="3" class="stroke-foreground" transform="{{ $hand('minute') }}" />
        @if ($seconds)
            <line data-clock-hand="second" x1="50" y1="58" x2="50" y2="12" stroke-linecap="round" stroke-width="1.5" class="stroke-error" transform="{{ $hand('second') }}" />
        @endif
        <circle cx="50" cy="50" r="3" class="{{ $seconds ? 'fill-error' : 'fill-foreground' }}" />
    </svg>

    @if ($label)
        <span class="text-foreground text-sm font-medium">{{ $label }}</span>
    @endif
    {{-- The face is a picture; this is the time in words for screen readers, updated each minute. --}}
    <time data-clock-spoken datetime="{{ $now->toIso8601String() }}" class="sr-only">{{ $icu($now, '{hm}', 'g:i A') }}</time>
</div><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
