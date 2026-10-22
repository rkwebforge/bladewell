@props([
    // Parts of one whole: [['label' => 'Photos', 'value' => 18.4, 'text' => '18.4 GB'], …]. text is optional;
    // color is optional too (primary, link, warning, error, muted) and otherwise follows that order.
    'segments' => [],
    // The whole the parts are measured against, e.g. 50 for a 50 GB plan. What's left over shows as free space.
    'max' => 100,
    // The heading above the bar, and its name for screen readers.
    'label' => 'Usage',
    // A summary beside the label, e.g. "37.5 GB of 50 GB used".
    'summary' => null,
    // Lists each part's colour, name and amount under the bar; false leaves it out.
    'legend' => true,
    // Legend name for the space left over; null leaves it out.
    'rest' => 'Free',
])

@php
    // Written out in full so Tailwind finds them.
    $colors = ['primary' => 'bg-primary', 'link' => 'bg-link', 'warning' => 'bg-warning', 'error' => 'bg-error', 'muted' => 'bg-muted'];
    $palette = array_keys($colors);
    $max = max((float) $max, 0.0) ?: 100.0;

    $used = 0.0;
    $parts = [];
    foreach (array_values($segments) as $i => $segment) {
        // Parts can't add up to more than the whole, so later ones are trimmed to what's left.
        $value = max(0.0, min((float) ($segment['value'] ?? 0), $max - $used));
        $used += $value;
        $percent = round($value / $max * 100, 2);
        $parts[] = [
            'label' => (string) ($segment['label'] ?? ''),
            'percent' => $percent,
            'text' => (string) ($segment['text'] ?? round($percent).'%'),
            'color' => $colors[$segment['color'] ?? $palette[$i % count($palette)]] ?? $colors['primary'],
        ];
    }
    $restPercent = round(max(0.0, 100 - $used / $max * 100), 2);

    // The bar is a picture of the legend, so it's named with the same words.
    $description = collect($parts)->map(fn (array $part): string => "{$part['label']} {$part['text']}")
        ->when($rest !== null && $restPercent > 0, fn ($items) => $items->push($rest.' '.round($restPercent).'%'))
        ->implode(', ');
@endphp

<div {{ $attributes->class(['w-full']) }}>
    <div class="text-foreground mb-2 flex items-baseline justify-between gap-2 text-xs">
        <span class="font-medium">{{ $label }}</span>
        @if ($summary)
            <span class="text-foreground/70 tabular-nums">{{ $summary }}</span>
        @endif
    </div>

    <div role="img" aria-label="{{ $label }}: {{ $description }}" class="bg-field flex h-3 w-full gap-0.5 overflow-hidden rounded-full">
        @foreach ($parts as $part)
            @if ($part['percent'] > 0)
                {{-- Whole percents from the ranges at the end of base.css; min-w-0.5 keeps a sliver under 1% visible. --}}
                <div class="{{ $part['color'] }} w-[{{ max(0, min(100, (int) round($part['percent']))) }}%] h-full min-w-0.5 transition-[width] duration-500 motion-reduce:transition-none"></div>
            @endif
        @endforeach
    </div>

    @if ($legend)
        <ul class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-xs" aria-hidden="true">
            @foreach ($parts as $part)
                <li class="flex items-center gap-1.5">
                    <span class="{{ $part['color'] }} size-2.5 shrink-0 rounded-full"></span>
                    <span class="text-foreground">{{ $part['label'] }}</span>
                    <span class="text-foreground/70 tabular-nums">{{ $part['text'] }}</span>
                </li>
            @endforeach
            @if ($rest !== null && $restPercent > 0)
                <li class="flex items-center gap-1.5">
                    <span class="bg-field border-line size-2.5 shrink-0 rounded-full border"></span>
                    <span class="text-foreground">{{ $rest }}</span>
                    <span class="text-foreground/70 tabular-nums">{{ round($restPercent) }}%</span>
                </li>
            @endif
        </ul>
    @endif
</div>
