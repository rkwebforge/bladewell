@props([
    // The number to show. Set data-value on it later and the digits roll to the new one.
    'value' => 0,
    // A currency code like USD or EUR; config('app.currency') (or USD) when left out, and false for a plain number.
    'currency' => null,
    // How the number is written, e.g. en-IN or de-DE: separators, symbol and its place. Defaults to the app's locale.
    'locale' => null,
    // Digits after the decimal point, always that many. Left out, the currency's usual number (up to 3 without one).
    'decimals' => null,
    // Said before the price by screen readers ("Price: $64,210.50"); not shown.
    'label' => null,
    // Announces each new price to screen readers as it changes.
    'live' => false,
    // Where the price started (today's open, say): adds a badge with the change since then, kept up to date as it rolls.
    'previous' => null,
    // What the badge shows: percent (+4.38%), amount (+$115.65) or both. From a previous of 0, always the amount.
    'change' => 'percent',
    // How the change is coloured: default (up is green, down red), inverse (up is red, for a cost or a wait) or none.
    'trendColors' => 'default',
])

@php
    $value = is_numeric($value) ? (float) $value : 0.0;
    // de_DE as well as de-DE: PHP takes both, but the browser's Intl, which rolls the number, only the second.
    $locale = str_replace('_', '-', $locale ?? app()->getLocale());
    // Each character of the price is its own flex item, so the row must run in the price's own direction, not the
    // page's: "$1,234" stays in order on an Arabic page, and an Arabic-script price reads right to left anywhere.
    $priceDirection = in_array(strtolower(explode('-', $locale)[0]), ['ar', 'he', 'fa', 'ur', 'ps', 'yi', 'dv', 'ug', 'ckb', 'sd'], true) ? 'rtl' : 'ltr';
    $currency ??= config('app.currency', 'USD');

    // Same formatting as Intl.NumberFormat in resources/js/widget/price-roll, so the JS picks up the
    // server's markup as-is. currency=false formats a plain number. Needs PHP's intl extension.
    $formatter = new \NumberFormatter($locale, $currency ? \NumberFormatter::CURRENCY : \NumberFormatter::DECIMAL);
    if ($decimals !== null) {
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, (int) $decimals);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, (int) $decimals);
    }
    $text = $currency ? $formatter->formatCurrency($value, $currency) : $formatter->format($value);
    $chars = mb_str_split((string) $text);

    // previous="…": a badge with the change since then ("+4.38%", "+$2,750.50", or both), which the script keeps
    // up to date as the price moves. Worked out the same way as resources/js/widget/price-roll, so they agree.
    $previous = is_numeric($previous) ? (float) $previous : null;
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($change, ['percent', 'amount', 'both'], true)) {
        throw new \InvalidArgumentException("Unknown change [{$change}] for <x-widget.price-roll>. Use one of: percent, amount, both.");
    }
    // default: up is good (green). inverse: up is bad, for a cost or a wait. none: no colours at all.
    if (! in_array($trendColors, ['default', 'inverse', 'none'], true)) {
        throw new \InvalidArgumentException("Unknown trend-colors [{$trendColors}] for <x-widget.price-roll>. Use one of: default, inverse, none.");
    }
    $changeText = null;
    $direction = 'flat';
    $spokenChange = '';
    if ($previous !== null) {
        $delta = round($value - $previous, 10);
        $direction = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
        $sign = $delta > 0 ? '+' : ($delta < 0 ? '-' : '');
        $amount = $sign.($currency ? $formatter->formatCurrency(abs($delta), $currency) : $formatter->format(abs($delta)));
        $percentFormatter = new \NumberFormatter($locale, \NumberFormatter::PERCENT);
        $percentFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $percentFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 2);
        // No percentage from a zero baseline; the amount says it instead.
        $percent = $previous != 0.0 ? $sign.$percentFormatter->format(abs($delta / $previous)) : null;
        $changeText = match (true) {
            $change === 'amount' || $percent === null => $amount,
            $change === 'both' => "{$amount} ({$percent})",
            default => $percent,
        };
        $spokenChange = ', '.($direction === 'flat' ? 'unchanged' : $direction.' '.ltrim($changeText, '+-'));
    }
    // Good or bad, not up or down: the colour follows trend-colors, for the badge and the flash while digits roll.
    $tone = static fn (string $direction): string => $trendColors === 'none' || $direction === 'flat'
        ? 'neutral'
        : ((($direction === 'up') !== ($trendColors === 'inverse')) ? 'good' : 'bad');
    $digitCount = count(array_filter($chars, static fn (string $char): bool => ctype_digit($char)));
    $position = $digitCount;
@endphp

{{--
    Odometer-style price. Each digit is a one-line window onto a 0–9 column, shifted up by the digit
    (translate: 0 calc(var(--d) * -1lh)), so changing --d rolls it. Change the price by setting
    data-value (el.dataset.value = 1299.5); the JS formats it, rolls the digits and flashes up/down.
    Screen readers get the plain formatted price instead of the columns; `live` announces changes.
--}}
<span
    data-price-roll
    data-value="{{ $value }}"
    data-locale="{{ $locale }}"
    @if ($currency) data-currency="{{ $currency }}" @endif
    @if ($label) data-label="{{ $label }}" @endif
    @if ($decimals !== null) data-decimals="{{ (int) $decimals }}" @endif
    @if ($previous !== null) data-previous="{{ $previous }}" data-change="{{ $change }}" @endif
    data-trend-colors="{{ $trendColors }}"
    {{-- data-flash is set by the script for a moment after each change: good or bad, per trend-colors. flex-wrap: a large
         price with its change badge can be wider than a phone, so the badge drops to the next line rather than spill out. --}}
    {{ $attributes->class(['text-foreground data-[flash=bad]:text-error data-[flash=good]:text-success inline-flex flex-wrap items-baseline gap-x-1.5 gap-y-1 tabular-nums transition-colors duration-500']) }}
>
    <span data-price-roll-text class="sr-only" @if ($live) aria-live="polite" @endif>{{ $label ? $label.': ' : '' }}{{ $text }}{{ $spokenChange }}</span>
    {{-- Your own markup before the number: "from", a currency toggle, an icon. Not rolled, and read as written. --}}
    @isset($before)
        <span data-price-roll-before {{ $before->attributes }}>{{ $before }}</span>
    @endisset
    {{-- wire:ignore: the digits are the script's to roll. A Livewire render changes data-value above, which rolls them; redrawing
         them too would put each column straight at its new digit, so they'd jump instead. --}}
    <span data-price-roll-visual wire:ignore aria-hidden="true" dir="{{ $priceDirection }}" class="inline-flex">
        @foreach ($chars as $char)
            @if (ctype_digit($char))
                {{-- The mask fades the window's top and bottom edge, so digits slide in and out rather than being sliced. --}}
                {{-- --i counts from the right, so higher places start rolling a little later, like a real odometer. --}}
                <span data-price-roll-digit class="inline-block h-[1lh] overflow-hidden [mask-image:linear-gradient(transparent,#000_12%,#000_88%,transparent)]">
                    {{-- --d and --i are classes from the ranges at the end of base.css, not style=""; the script sets them inline from then on. --}}
                    <span class="[--d:{{ $char }}] [--i:{{ max(0, min(30, --$position)) }}] block [translate:0_calc(var(--d)*-1lh)] transition-[translate] duration-700 ease-[cubic-bezier(0.2,0.8,0.2,1)] [transition-delay:calc(var(--i)*40ms)] motion-reduce:transition-none">
                        @for ($n = 0; $n <= 9; $n++)
                            <span class="block text-center">{{ $n }}</span>
                        @endfor
                    </span>
                </span>
            @else
                <span data-price-roll-char class="whitespace-pre">{{ $char }}</span>
            @endif
        @endforeach
    </span>
    @if ($changeText !== null)
        {{-- Hidden from screen readers, which get the change with the price (above). The arrow backs up the colour. --}}
        <span
            data-price-roll-change
            aria-hidden="true"
            data-direction="{{ $direction }}"
            data-tone="{{ $tone($direction) }}"
            dir="{{ $priceDirection }}"
            class="data-[tone=good]:bg-success/10 data-[tone=good]:text-[color-mix(in_oklab,var(--color-success)_75%,var(--color-foreground))] data-[tone=bad]:bg-error/10 data-[tone=bad]:text-error data-[tone=neutral]:bg-field data-[tone=neutral]:text-foreground/75 self-center rounded-full px-2 py-0.5 text-[max(0.75rem,0.4em)] leading-none font-medium whitespace-nowrap transition-colors"
        ><span data-price-roll-arrow>{{ ['up' => '▲', 'down' => '▼'][$direction] ?? '' }}</span> <span data-price-roll-change-text>{{ $changeText }}</span></span>
    @endif
    {{-- Your own markup after it: "/month", "per share", a unit. --}}
    @isset($after)
        <span data-price-roll-after {{ $after->attributes }}>{{ $after }}</span>
    @endisset
</span><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
