@props([
    // Required: how many steps there are.
    'total',
    // The step you're on, counting from 1; kept between the first and the last.
    'current' => 1,
    // The dots' name for screen readers (Progress when left out), said before "Step 2 of 4".
    'label' => null,
])

@php
    // No steps (total 0): nothing is drawn, rather than one dot and "Step 1 of 1".
    $hasSteps = (int) $total > 0;
    $total = max(1, (int) $total);
    $current = max(1, min($total, (int) $current));
    $text = ($label ? $label.': ' : '').'Step '.$current.' of '.$total;
@endphp

@if ($hasSteps)
{{-- Small dots for short wizards and carousels; the current one stretches into a pill. Decorative, like the
     bars: the progressbar role carries "Step 2 of 4" for screen readers. --}}
<div
    role="progressbar"
    aria-valuemin="1"
    aria-valuemax="{{ $total }}"
    aria-valuenow="{{ $current }}"
    aria-valuetext="{{ $text }}"
    aria-label="{{ $label ?? 'Progress' }}"
    {{ $attributes->class(['flex items-center justify-center gap-1.5']) }}
>
    @for ($step = 1; $step <= $total; $step++)
        <span aria-hidden="true" @class([
            'h-2 rounded-full transition-all duration-300 motion-reduce:transition-none',
            'bg-primary w-6' => $step === $current,
            'bg-primary/40 w-2' => $step < $current,
            'bg-muted/75 w-2' => $step > $current,
        ])></span>
    @endfor
</div>
@endif
