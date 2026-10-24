@aware([
    'compact' => false,
])

@props([
    // Where the file is: opened in a new tab (an image) or downloaded. A web address or one on this site; anything else
    // (javascript:, data:…) is shown without a link.
    'href',
    // The file's name, shown on the card.
    'name',
    // Its size in bytes, shown in the app's locale ("2.4 MB").
    'size' => null,
    // A picture of it to show instead of the card, by URL: the image itself, or a thumbnail of it.
    'preview' => null,
    // What the picture shows, for screen readers. Defaults to the file's name.
    'alt' => null,
])

@php
    // Only a link a person can safely follow: http(s), or a path on this site. A javascript: or data: URL from data a
    // user supplied would run in the page when clicked. Checked as the browser reads it, without the control
    // characters and spaces it ignores ("java\tscript:" is javascript:).
    $address = preg_replace('/[\x00-\x20\x7F]+/', '', (string) $href);
    // An address PHP can't read at all isn't trusted either.
    $parts = parse_url($address);
    $linked = $address !== '' && $parts !== false && (! isset($parts['scheme']) || in_array(strtolower($parts['scheme']), ['http', 'https'], true));
    if (! $linked) {
        \Illuminate\Support\Facades\Log::warning('Bladewell chat.attachment: href must be a web address or a path on this site; shown without a link.', ['href' => $href]);
    }
    // Direction controls can make a name read as something else ("invoice‮fdp.exe" shows as "invoiceexe.pdf"): they go,
    // and the name sits in <bdi>, so it can't reorder what's around it either.
    $name = preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', (string) $name);

    $readable = null;
    if (is_numeric($size)) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $size > 0 ? min((int) floor(log((float) $size, 1024)), count($units) - 1) : 0;
        $amount = $size / 1024 ** $power;
        $decimals = $power === 0 || $amount >= 10 ? 0 : 1;
        // In the app's locale where intl is there ("2,4 MB" in German); plain digits where it isn't.
        $readable = (class_exists(\NumberFormatter::class)
            ? (function () use ($amount, $decimals): string {
                $formatter = new \NumberFormatter(str_replace('-', '_', app()->getLocale()), \NumberFormatter::DECIMAL);
                $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

                return (string) $formatter->format($amount);
            })()
            : number_format($amount, $decimals)).' '.$units[$power];
    }
    $ring = 'focus-visible:ring-primary outline-none focus-visible:ring-2';
@endphp

@if ($preview)
    @if ($linked)
        <a
            href="{{ $href }}"
            target="_blank"
            rel="noopener"
            {{ $attributes->class(['border-line block overflow-hidden rounded-xl border focus-visible:ring-offset-2 focus-visible:ring-offset-surface', $ring]) }}
        ><img src="{{ $preview }}" alt="{{ $alt ?? $name }}" class="max-h-56 w-auto max-w-full object-cover"><span class="sr-only"> (opens in a new tab)</span></a>
    @else
        <span {{ $attributes->class(['border-line block overflow-hidden rounded-xl border']) }}><img src="{{ $preview }}" alt="{{ $alt ?? $name }}" class="max-h-56 w-auto max-w-full object-cover"></span>
    @endif
@else
    <{{ $linked ? 'a' : 'div' }}
        @if ($linked) href="{{ $href }}" download @endif
        {{ $attributes->class([
            'border-line bg-surface text-foreground flex min-w-0 max-w-full items-center rounded-xl border text-start sm:max-w-72',
            'gap-3 p-2 pe-3' => ! $compact,
            'gap-2.5 p-1.5 pe-2.5' => $compact,
            'hover:bg-field transition-colors motion-reduce:transition-none '.$ring => $linked,
        ]) }}
    >
        <span aria-hidden="true" @class(['bg-field text-foreground/70 grid shrink-0 place-items-center rounded-lg', $compact ? 'size-8' : 'size-9'])>
            <x-widget.icon name="file-text" :class="$compact ? 'size-4' : 'size-4.5'" />
        </span>
        <span class="min-w-0 flex-1 text-sm leading-tight">
            <bdi class="block truncate font-medium">{{ $name }}</bdi>
            @if ($readable)
                <span class="text-foreground/70 block text-xs">{{ $readable }}</span>
            @endif
        </span>
        @if ($linked)
            <x-widget.icon name="download" class="text-foreground/70 size-4 shrink-0" />
        @endif
    </{{ $linked ? 'a' : 'div' }}>
@endif
