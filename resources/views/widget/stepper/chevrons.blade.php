@props([
    // Labels, or arrays: ['label' => 'Shipping', 'href' => …]. href makes a done step a link back to it.
    'steps' => [],
    // The step you're on, counting from 1; kept between the first and the last.
    'current' => 1,
    // The steps' name for screen readers.
    'label' => 'Progress',
])

@php
    // Each step is a label, or ['label' => …, 'href' => …]; href makes a completed step a link back to it.
    $steps = array_values(array_map(
        static fn (string|array $step): array => is_string($step) ? ['label' => $step] : $step,
        $steps,
    ));
    $current = max(1, min(max(1, count($steps)), (int) $current));
    // No steps (none set up yet): nothing is drawn, rather than an empty list.
    $hasSteps = $steps !== [];

    // Arrow-shaped segments: a notch cut into the start, a point on the end. The first has no notch, the last
    // no point. Mirrored in RTL (and the text flipped back) so the arrows point the way you read.
    $shape = static fn (bool $first, bool $last): string => match (true) {
        $first && $last => '',
        $first => '[clip-path:polygon(0_0,calc(100%-12px)_0,100%_50%,calc(100%-12px)_100%,0_100%)]',
        $last => '[clip-path:polygon(0_0,100%_0,100%_100%,0_100%,12px_50%)]',
        default => '[clip-path:polygon(0_0,calc(100%-12px)_0,100%_50%,calc(100%-12px)_100%,0_100%,12px_50%)]',
    };
    // Solid tints (mixed with the surface, not see-through) so the overlapping points stay clean on any background.
    $look = ['done' => 'bg-[color-mix(in_oklab,var(--color-primary)_15%,var(--color-surface))] text-primary', 'current' => 'bg-primary-fill text-on-primary', 'upcoming' => 'bg-field text-foreground/70'];
    $spoken = ['done' => 'completed', 'current' => 'current step', 'upcoming' => 'not started'];
@endphp

@if ($hasSteps)
<ol aria-label="{{ $label }}" {{ $attributes->class(['flex w-full']) }}>
    @foreach ($steps as $index => $step)
        @php
            $number = $index + 1;
            $state = match (true) {
                $number < $current => 'done',
                $number === $current => 'current',
                default => 'upcoming',
            };
            $link = $state === 'done' && ! empty($step['href']) ? $step['href'] : null;
        @endphp

        {{-- Segments overlap by the point's width so each point sits in the next one's notch. --}}
        <li @if ($state === 'current') aria-current="step" @endif @class(['min-w-0', 'flex-[2]' => $state === 'current', 'flex-1' => $state !== 'current', '-ms-2.5' => ! $loop->first])>
            <{{ $link ? 'a' : 'div' }}
                @if ($link) href="{{ $link }}" @endif
                @class([
                    $look[$state],
                    $shape($loop->first, $loop->last),
                    'flex h-10 items-center justify-center px-5 text-sm font-medium outline-none rtl:-scale-x-100',
                    'rounded-s-lg' => $loop->first,
                    'rounded-e-lg' => $loop->last,
                    'hover:bg-[color-mix(in_oklab,var(--color-primary)_25%,var(--color-surface))] focus-visible:underline' => $link,
                ])
            >
                <span class="truncate rtl:-scale-x-100">
                    {{-- On phones the others shrink to their number; the current step keeps its name. --}}
                    @if ($state === 'current')
                        {{ $step['label'] }}
                    @else
                        <span class="max-sm:sr-only">{{ $step['label'] }}</span><span class="sm:hidden" aria-hidden="true">{{ $number }}</span>
                    @endif
                    <span class="sr-only">, {{ $spoken[$state] }}</span>
                </span>
            </{{ $link ? 'a' : 'div' }}>
        </li>
    @endforeach
</ol>
@endif
