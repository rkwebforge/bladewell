@props([
    // How far along it is, out of max. Reaching max turns it green.
    'value' => 0,
    // Progress is value out of max: :value="3" :max="5" for files, bytes, money, anything countable.
    'max' => 100,
    // Its name for screen readers ("Progress" if left out); shown too with show-label.
    'label' => null,
    // Shows the label and the value next to the bar, placed by label-position. Without it only screen readers get them
    // (label-position="inside" still shows the value).
    'showLabel' => false,
    // Where the label and value go: above the bar, beside it on one line, or inside a thicker bar.
    'labelPosition' => 'above',
    // Shown instead of the percentage, and read out by screen readers: "3 of 5 files", "1.2 GB of 5 GB".
    'valueText' => null,
    // Turns it red, e.g. for an upload that stopped, and screen readers hear "failed".
    'failed' => false,
    // Greyed out, e.g. for a paused job, and screen readers hear "disabled".
    'disabled' => false,
    // How thick the bar is: sm, md or lg. label-position="inside" always uses a thicker bar.
    'size' => 'md',
    // The amount isn't known yet: a sliding bar and no value. (A prop, not :value="null": Blade turns a passed null back into the default.)
    'indeterminate' => false,
    // Moving diagonal stripes on the filled part, to show work is going on. They stay still for people who
    // prefer reduced motion.
    'striped' => false,
])

@php
    $max = max((float) $max, 0.0) ?: 100.0;
    $value = max(0.0, min($max, (float) $value));
    $percent = (int) round($value / $max * 100);
    $heights = ['sm' => 'h-1.5', 'md' => 'h-3', 'lg' => 'h-4'];
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($labelPosition, ['above', 'beside', 'inside'], true)) {
        throw new \InvalidArgumentException("Unknown label-position [{$labelPosition}] for <x-widget.progress>. Use one of: above, beside, inside.");
    }
    if (! array_key_exists($size, $heights)) {
        throw new \InvalidArgumentException("Unknown size [{$size}] for <x-widget.progress>. Use one of: ".implode(', ', array_keys($heights)).'.');
    }
    $inside = $labelPosition === 'inside';
    $height = $inside ? 'h-6' : $heights[$size];
    $text = $valueText ?? "{$percent}%";
    $complete = ! $indeterminate && ! $failed && ! $disabled && $value >= $max;

    // Base colour per state; reaching max turns it green through data-complete, which resources/js/widget/progress
    // also sets, so the colour follows progress.set() too.
    // With the label inside, white text sits on the bar, so it takes the deeper fill shades (they differ in dark mode).
    $fill = match (true) {
        $disabled => 'bg-muted opacity-50',
        $failed => $inside ? 'bg-error-fill' : 'bg-error',
        $inside => 'bg-primary-fill group-data-complete/progress:bg-success-fill',
        default => 'bg-primary group-data-complete/progress:bg-success',
    };
    $state = match (true) {
        $disabled => ', disabled',
        $failed => ', failed',
        default => '',
    };
    $number = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
@endphp

<div {{ $attributes->class(['w-full', 'flex items-center gap-3' => $labelPosition === 'beside' && $showLabel]) }}>
    @if ($showLabel && $labelPosition === 'above')
        <div class="text-foreground mb-1.5 flex items-center justify-between gap-2 text-xs">
            <span>{{ $label }}</span>
            <span data-progress-value class="text-foreground/70 tabular-nums" @if ($indeterminate) hidden @endif>{{ $text }}</span>
        </div>
    @elseif ($showLabel && $labelPosition === 'beside' && $label)
        <span class="text-foreground shrink-0 text-xs">{{ $label }}</span>
    @endif

    <div
        role="progressbar"
        data-progress="linear"
        aria-valuemin="0"
        aria-valuemax="{{ $max }}"
        @unless ($indeterminate)
            aria-valuenow="{{ $number }}"
            aria-valuetext="{{ $text }}{{ $state }}"
        @endunless
        aria-label="{{ $label ?? 'Progress' }}"
        @if ($indeterminate) data-indeterminate @endif
        @if ($complete) data-complete @endif
        @if ($failed) data-failed @endif
        @if ($disabled) aria-disabled="true" @endif
        @class(['group/progress bg-field relative w-full min-w-0 overflow-hidden rounded-[14px]', $height, '@container' => $inside])
    >
        {{-- Inline width is the one dynamic value; progress.set() updates it and the transition animates it. --}}
        <div
            data-progress-fill
            @class([
                $fill,
                'absolute inset-y-0 start-0 rounded-[10px] transition-[width,background-color] duration-500 ease-in-out motion-reduce:transition-none',
                'bg-[linear-gradient(45deg,rgb(255_255_255/0.25)_25%,transparent_25%,transparent_50%,rgb(255_255_255/0.25)_50%,rgb(255_255_255/0.25)_75%,transparent_75%,transparent)] bg-size-[1rem_1rem] animate-progress-stripes motion-reduce:animate-none' => $striped,
                // Indeterminate: a short bar sliding across. Reduced motion gets a still bar instead.
                'group-data-indeterminate/progress:w-2/5! group-data-indeterminate/progress:animate-progress-slide rtl:group-data-indeterminate/progress:animate-progress-slide-rtl motion-reduce:group-data-indeterminate/progress:animate-none',
                // A class, not style="": see the ranges at the end of base.css.
                "w-[{$percent}%]",
                // Inside labels: the fill clips its own white copy of the label (below).
                'overflow-hidden' => $inside,
            ])
        >
            {{-- No one colour reads on both a dark fill and the light track, so the label is drawn twice: dark over
                 the track, and this white copy, as wide as the whole bar (100cqw), cut off where the fill ends.
                 Not for an indeterminate bar, whose fill slides and would carry the copy with it. --}}
            @if ($inside && ! $indeterminate)
                <div aria-hidden="true" class="text-on-primary absolute inset-y-0 start-0 flex w-[100cqw] items-center justify-between gap-2 px-3 text-xs font-medium">
                    <span class="truncate">{{ $showLabel ? $label : '' }}</span>
                    <span data-progress-value class="shrink-0 tabular-nums">{{ $text }}</span>
                </div>
            @endif
        </div>

        @if ($inside)
            {{-- The dark copy, for the track. It sits under the fill, which covers it with the white copy as it grows. --}}
            <div class="text-foreground flex h-full items-center justify-between gap-2 px-3 text-xs font-medium">
                <span class="truncate">{{ $showLabel ? $label : '' }}</span>
                <span data-progress-value class="shrink-0 tabular-nums" @if ($indeterminate) hidden @endif>{{ $text }}</span>
            </div>
        @endif
    </div>

    @if ($showLabel && $labelPosition === 'beside')
        <span data-progress-value class="text-foreground/70 shrink-0 text-xs tabular-nums" @if ($indeterminate) hidden @endif>{{ $text }}</span>
    @endif
</div>
