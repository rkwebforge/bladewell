@props([
    // How far along it is, out of max. Reaching max turns it green.
    'value' => 0,
    // Progress is value out of max: :value="3" :max="5" for files, bytes, money, anything countable.
    'max' => 100,
    // Its name for screen readers ("Progress" if left out); shown too with show-label.
    'label' => null,
    // Shows the label under the circle.
    'showLabel' => false,
    // The percentage in the centre of the circle.
    'showValue' => true,
    // Read out by screen readers and shown under the label, e.g. "3 of 5 files". The centre keeps the percentage.
    'valueText' => null,
    // Turns it red, e.g. for an upload that stopped, and screen readers hear "failed".
    'failed' => false,
    // Greyed out, e.g. for a paused job, and screen readers hear "disabled".
    'disabled' => false,
    // How big the circle is: sm, md or lg.
    'size' => 'md',
    // The amount isn't known yet: a spinning arc and no value. (A prop, not :value="null": Blade turns a passed null
    // back into the default.)
    'indeterminate' => false,
])

@php
    // indeterminate: the amount isn't known yet, so a spinning arc and no aria-valuenow. (A prop, not
    // :value="null": Blade turns a passed null back into the default.)
    $max = max((float) $max, 0.0) ?: 100.0;
    $value = max(0.0, min($max, (float) $value));
    $percent = (int) round($value / $max * 100);
    $number = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    $complete = ! $indeterminate && ! $failed && ! $disabled && $value >= $max;

    // Box, ring thickness (in viewBox units, so it scales with the box) and centre text, per size.
    $sizes = [
        'sm' => ['box' => 'size-10', 'stroke' => 10, 'text' => 'text-[10px]'],
        'md' => ['box' => 'size-16', 'stroke' => 9, 'text' => 'text-sm'],
        'lg' => ['box' => 'size-24', 'stroke' => 8, 'text' => 'text-lg'],
    ];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! array_key_exists($size, $sizes)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.progress.circular>. Use one of: ".implode(', ', array_keys($sizes)).'.');
    }
    ['box' => $box, 'stroke' => $stroke, 'text' => $text] = $sizes[$size];
    $radius = 50 - $stroke / 2;

    // Same colours per state as the linear <x-widget.progress>, completion included (data-complete).
    $arc = match (true) {
        $disabled => 'stroke-muted opacity-50',
        $failed => 'stroke-error',
        default => 'stroke-primary group-data-complete/progress:stroke-success',
    };
    $state = match (true) {
        $disabled => ', disabled',
        $failed => ', failed',
        default => '',
    };
@endphp

<div {{ $attributes->class(['inline-flex flex-col items-center gap-2']) }}>
    <div
        role="progressbar"
        data-progress="circular"
        aria-valuemin="0"
        aria-valuemax="{{ $max }}"
        @unless ($indeterminate)
            aria-valuenow="{{ $number }}"
            aria-valuetext="{{ $valueText ?? $percent.'%' }}{{ $state }}"
        @endunless
        aria-label="{{ $label ?? 'Progress' }}"
        @if ($indeterminate) data-indeterminate @endif
        @if ($complete) data-complete @endif
        @if (! $indeterminate && $percent === 0) data-empty @endif
        @if ($failed) data-failed @endif
        @if ($disabled) aria-disabled="true" @endif
        class="group/progress {{ $box }} relative shrink-0"
    >
        {{-- pathLength="100" makes the dash maths plain percentages; -rotate-90 starts the arc at 12 o'clock.
             --progress is the one dynamic value; progress.set() updates it and the transition animates it. --}}
        <svg viewBox="0 0 100 100" aria-hidden="true" class="size-full -rotate-90 group-data-indeterminate/progress:animate-spin motion-reduce:group-data-indeterminate/progress:animate-none">
            <circle cx="50" cy="50" r="{{ $radius }}" fill="none" stroke-width="{{ $stroke }}" class="stroke-field" />
            <circle
                data-progress-fill
                cx="50" cy="50" r="{{ $radius }}" fill="none" stroke-width="{{ $stroke }}"
                stroke-linecap="round" pathLength="100" stroke-dasharray="100"
                @class([
                    $arc,
                    // --progress as a class from the ranges at the end of base.css, not style="".
                    '[--progress:'.($indeterminate ? 25 : $percent).'] [stroke-dashoffset:calc(100-var(--progress))]',
                    'transition-[stroke-dashoffset,stroke,opacity] duration-500 ease-in-out motion-reduce:transition-none',
                    // A round cap would draw a dot at 0%; progress.set() toggles data-empty the same way.
                    'group-data-empty/progress:opacity-0',
                ])
            />
        </svg>

        @if ($showValue)
            <span data-progress-percent aria-hidden="true" class="{{ $text }} text-foreground absolute inset-0 grid place-items-center font-semibold tabular-nums group-data-indeterminate/progress:hidden">{{ $percent }}%</span>
        @endif
    </div>

    @if ($showLabel && $label)
        <span class="text-foreground text-center text-xs">{{ $label }}</span>
    @endif
    @if ($valueText !== null)
        <span data-progress-value class="text-foreground/70 text-center text-xs tabular-nums">{{ $valueText }}</span>
    @endif
</div><?php /* No newline after this: PHP drops it after a closing tag, so no space trails the component in running text. */ ?>
