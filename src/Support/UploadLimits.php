<?php

declare(strict_types=1);

namespace Bladewell\Support;

/**
 * What an <x-widget.file-upload.*> accepts, read once from its props: the largest file in bytes and the types,
 * plus the hint that says both in words ("PDF or PNG, up to 5 MB"). The browser checks the same limits as the
 * user picks files; the Form Request must still validate them (see the widget's usage notes).
 */
final readonly class UploadLimits
{
    private const array UNITS = ['KB' => 1024, 'MB' => 1024 ** 2, 'GB' => 1024 ** 3];

    /**
     * @param  list<string>  $accept  the accept attribute's entries: ".pdf", "image/png", "image/*"
     */
    private function __construct(
        public ?int $maxBytes,
        public array $accept,
    ) {}

    /**
     * @param  int|string|null  $maxSize  "5MB", "500 KB", "2GB", or a number of kilobytes, as Laravel's max: rule counts
     */
    public static function from(int|string|null $maxSize, ?string $accept): self
    {
        return new self(self::bytes($maxSize), array_values(array_filter(array_map('trim', explode(',', (string) $accept)))));
    }

    /** "PDF, PNG or JPG, up to 5 MB"; null when there is nothing to say. */
    public function hint(): ?string
    {
        $types = array_values(array_unique(array_map(self::typeName(...), $this->accept)));
        $parts = array_filter([
            match (count($types)) {
                0 => null,
                1 => $types[0],
                default => implode(', ', array_slice($types, 0, -1)).' or '.end($types),
            },
            $this->maxBytes !== null ? 'up to '.self::size($this->maxBytes) : null,
        ]);

        return $parts === [] ? null : ucfirst(implode(', ', $parts));
    }

    /**
     * 5242880 → "5 MB", 512000 → "500 KB". The number is written as the locale writes it ("2,5 MB" in German), the app's
     * by default, where the intl extension is installed; plainly ("2.5 MB") where it isn't.
     */
    public static function size(int $bytes, ?string $locale = null): string
    {
        foreach (array_reverse(self::UNITS, true) as $unit => $factor) {
            if ($bytes >= $factor) {
                return self::number($bytes / $factor, $locale).' '.$unit;
            }
        }

        return self::number($bytes, $locale).' bytes';
    }

    /** One decimal at most, rounded half up, as resources/js/widget/file-upload writes it with Intl. */
    private static function number(float|int $number, ?string $locale): string
    {
        $plain = rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
        if (! class_exists(\NumberFormatter::class)) {
            return $plain;
        }
        $formatter = new \NumberFormatter(str_replace('-', '_', $locale ?? app()->getLocale()), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 1);
        $formatter->setAttribute(\NumberFormatter::ROUNDING_MODE, \NumberFormatter::ROUND_HALFUP);

        return $formatter->format($number) ?: $plain;
    }

    private static function bytes(int|string|null $maxSize): ?int
    {
        if ($maxSize === null || $maxSize === '') {
            return null;
        }
        if (is_int($maxSize) || ctype_digit($maxSize)) {
            return (int) $maxSize * 1024;
        }
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*(KB|MB|GB)\s*$/i', $maxSize, $match) !== 1) {
            throw new \InvalidArgumentException("max-size=\"{$maxSize}\" isn't a size. Use 500KB, 5MB, 2GB, or kilobytes as a number.");
        }

        return (int) round((float) $match[1] * self::UNITS[strtoupper($match[2])]);
    }

    /** ".pdf" → "PDF", "image/png" → "PNG", "image/svg+xml" → "SVG", "image/*" → "images". */
    private static function typeName(string $entry): string
    {
        if (str_starts_with($entry, '.')) {
            return strtoupper(substr($entry, 1));
        }
        [$group, $kind] = array_pad(explode('/', $entry, 2), 2, '*');

        // image/svg+xml → SVG: the +xml (or +json) suffix is how the type is written down, not what people call it.
        return $kind === '*' ? "{$group}s" : strtoupper((string) preg_replace('/\+\w+$/', '', $kind));
    }
}
