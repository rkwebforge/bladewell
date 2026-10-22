@props([
    // A count of steps, or their names with :steps (then total can be left out).
    'total' => null,
    // The steps' names: ['Cart', 'Shipping', 'Payment'], or the other steppers' arrays (only 'label' is used here).
    'steps' => [],
    // The step you're on, counting from 1; kept between the first and the last.
    'current' => 1,
    // The bar's name for screen readers (Progress when left out), said before "Step 2 of 4". With show-label, shown when
    // the current step has no name.
    'label' => null,
    // A line above the bars: the current step's name (or the label) and "Step 2 of 4".
    'showLabel' => false,
    // Step names under the bars from the sm breakpoint up; on phones they'd crowd each other.
    'showSteps' => false,
])

@php
    // Names, or the arrays the other steppers take (only the label is used here), so one list fits every stepper.
    $steps = array_values(array_map(static fn (string|array $step): string => is_string($step) ? $step : (string) ($step['label'] ?? ''), $steps));
    $total = max(1, (int) ($total ?? count($steps)));
    $current = max(1, min($total, (int) $current));
    $name = $steps[$current - 1] ?? null;
    $text = ($label ? $label.': ' : '').'Step '.$current.' of '.$total.($name !== null ? ', '.$name : '');
@endphp

<div {{ $attributes->class(['w-full']) }}>
    @if ($showLabel)
        <div class="text-foreground mb-2 flex items-baseline justify-between gap-2 text-xs" aria-hidden="true">
            <span class="font-medium">{{ $name ?? $label }}</span>
            <span class="text-foreground/70 tabular-nums">Step {{ $current }} of {{ $total }}</span>
        </div>
    @endif

    {{-- The segments are decorative; the progressbar role carries the "Step 2 of 4" for screen readers. --}}
    <div
        role="progressbar"
        aria-valuemin="1"
        aria-valuemax="{{ $total }}"
        aria-valuenow="{{ $current }}"
        aria-valuetext="{{ $text }}"
        aria-label="{{ $label ?? 'Progress' }}"
        class="flex w-full items-center gap-2"
    >
        @for ($step = 1; $step <= $total; $step++)
            <span
                aria-hidden="true"
                @class([
                    'h-2 min-w-0 flex-1 rounded-full transition-colors duration-300 motion-reduce:transition-none',
                    'bg-primary' => $step <= $current,
                    // With names on show, a ring marks "you are here".
                    'ring-primary/25 ring-2' => $step === $current && ($showLabel || $showSteps),
                    // Upcoming segments at 3:1 against the page, so the bar's length reads before any label does.
                    'bg-muted/75' => $step > $current,
                ])
            ></span>
        @endfor
    </div>

    @if ($showSteps && $steps !== [])
        <ol class="text-foreground/70 mt-2 hidden gap-2 text-xs sm:flex" aria-hidden="true">
            @foreach ($steps as $i => $step)
                <li @class(['min-w-0 flex-1 truncate', 'text-foreground font-medium' => $i + 1 === $current])>{{ $step }}</li>
            @endforeach
        </ol>
    @endif
</div>
