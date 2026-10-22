@props([
    // Labels, or arrays: ['label' => 'Payment', 'description' => …, 'href' => …, 'optional' => true, 'locked' => true,
    // 'error' => true]. href makes a done step, or one with an error, a link back to it; description shows when vertical;
    // locked dims a step not reached yet and adds a lock; error marks one that needs attention.
    'steps' => [],
    // The step you're on, counting from 1; kept between the first and the last.
    'current' => 1,
    // The steps' name for screen readers.
    'label' => 'Progress',
    // horizontal: a row that shows only the current step's name on phones. vertical: stacked, with descriptions.
    'orientation' => 'horizontal',
])

@php
    // Each step is a label, or an array: ['label' => …, 'description' => …, 'href' => …, 'locked' => bool,
    // 'error' => bool, 'optional' => bool]. `locked` marks a step that can't be reached yet (lock icon, dimmed);
    // `href` makes a completed step a link back to it; `error` flags a step that needs attention.
    $steps = array_values(array_map(
        static fn (string|array $step): array => is_string($step) ? ['label' => $step] : $step,
        $steps,
    ));
    $current = max(1, min(max(1, count($steps)), (int) $current));
    // A typo fails loudly, naming the values that work, instead of quietly rendering something else.
    if (! in_array($orientation, ['horizontal', 'vertical'], true)) {
        throw new \InvalidArgumentException("Unknown orientation [{$orientation}] for <x-widget.stepper.labelled>. Use one of: horizontal, vertical.");
    }
    $vertical = $orientation === 'vertical';

    $stateOf = static fn (int $number, array $step): string => match (true) {
        ! empty($step['error']) => 'error',
        $number < $current => 'done',
        $number === $current => 'current',
        ! empty($step['locked']) => 'locked',
        default => 'upcoming',
    };
    // Outer ring, inner disc, and label, per state. The white gap between ring and disc is the padding.
    $ring = ['done' => 'border-foreground', 'current' => 'border-foreground', 'upcoming' => 'border-muted', 'locked' => 'border-line-strong', 'error' => 'border-error'];
    $disc = ['done' => 'bg-primary-fill text-on-primary', 'current' => 'bg-foreground text-surface', 'upcoming' => 'bg-muted text-surface', 'locked' => 'bg-line-strong text-surface', 'error' => 'bg-error-fill text-white'];
    $text = ['done' => 'text-foreground', 'current' => 'text-foreground', 'upcoming' => 'text-foreground', 'locked' => 'text-muted', 'error' => 'text-error'];
    $spoken = ['done' => 'completed', 'current' => 'current step', 'upcoming' => 'not started', 'locked' => 'locked', 'error' => 'needs attention'];
@endphp

<div {{ $attributes->class(['w-full']) }}>
    <ol aria-label="{{ $label }}" @class(['flex w-full', 'flex-col' => $vertical])>
        @foreach ($steps as $index => $step)
            @php
                $number = $index + 1;
                $state = $stateOf($number, $step);
                // A step that needs attention can be linked back to as well, so it can be fixed.
                $link = in_array($state, ['done', 'error'], true) && $number !== $current && ! empty($step['href']) ? $step['href'] : null;
                // The line leads up to the current step: dark after steps that are behind it.
                $lineDone = $number < $current;
            @endphp

            <li @if ($number === $current) aria-current="step" @endif @class([
                'relative flex min-w-0',
                'flex-1 flex-col items-center' => ! $vertical,
                'gap-3 pb-6 last:pb-0' => $vertical,
            ])>
                @unless ($loop->last)
                    {{-- Connector to the next step, from this circle's edge to the next one's (circles are 28px wide). --}}
                    <span aria-hidden="true" @class([
                        'absolute transition-colors duration-300',
                        'top-[13px] right-[calc(-50%+14px)] left-[calc(50%+14px)] h-0.5 rtl:right-[calc(50%+14px)] rtl:left-[calc(-50%+14px)]' => ! $vertical,
                        'start-[13px] top-8 bottom-1 w-0.5' => $vertical,
                        'bg-foreground' => $lineDone,
                        'bg-line-strong' => ! $lineDone,
                    ])></span>
                @endunless

                <{{ $link ? 'a' : 'div' }} @if ($link) href="{{ $link }}" @endif @class([
                    'flex max-w-full rounded-md outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface',
                    'flex-col items-center gap-2' => ! $vertical,
                    'items-start gap-3' => $vertical,
                    'group/step cursor-pointer' => $link,
                ])>
                    <span aria-hidden="true" class="{{ $ring[$state] }} bg-surface relative grid size-7 shrink-0 place-items-center rounded-full border-2 p-0.5 transition-colors duration-300">
                        <span class="{{ $disc[$state] }} grid size-full place-items-center rounded-full text-xs font-bold transition-colors duration-300 group-hover/step:opacity-80">
                            @if ($state === 'done')
                                <x-widget.icon name="check" class="size-3.5 stroke-[3]" />
                            @elseif ($state === 'error')
                                <x-widget.icon name="circle-alert" class="size-3.5" />
                            @elseif ($state === 'locked')
                                <x-widget.icon name="lock" class="size-3" />
                            @else
                                {{ $number }}
                            @endif
                        </span>
                    </span>

                    {{-- On phones a row only has room for the circles; the names stay for screen readers, and the line below names the current step. --}}
                    <span @class([
                        'flex min-w-0 flex-col',
                        'items-center px-1 text-center' => ! $vertical,
                        'max-sm:sr-only' => ! $vertical,
                        'pt-1' => $vertical,
                    ])>
                        <span class="{{ $text[$state] }} text-sm font-medium break-words">
                            {{ $step['label'] }}<span class="sr-only">, {{ $spoken[$state] }}</span>
                        </span>
                        @if (! empty($step['optional']))
                            <span class="text-muted text-xs">Optional</span>
                        @endif
                        @if ($vertical && ! empty($step['description']))
                            <span class="text-foreground/70 mt-0.5 text-sm">{{ $step['description'] }}</span>
                        @endif
                    </span>
                </{{ $link ? 'a' : 'div' }}>
            </li>
        @endforeach
    </ol>

    @unless ($vertical)
        {{-- Phones: where you are, in words, since the other names are hidden. Screen readers already have the list. --}}
        <p class="mt-3 flex flex-col items-center gap-0.5 text-center sm:hidden" aria-hidden="true">
            <span class="text-foreground text-sm font-medium">{{ $steps[$current - 1]['label'] ?? '' }}</span>
            <span class="text-foreground/70 text-xs tabular-nums">Step {{ $current }} of {{ count($steps) }}</span>
        </p>
    @endunless
</div>
