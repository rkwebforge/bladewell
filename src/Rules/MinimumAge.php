<?php

declare(strict_types=1);

namespace Bladewell\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Server-side twin of the date picker's `birthday` limit: the person must have reached
 * `$years` on their own local today.
 */
final class MinimumAge implements ValidationRule
{
    public function __construct(private readonly int $years = 18) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $birthDate = LocalToday::parse($value);

        if ($birthDate === null) {
            $fail('The :attribute must be a valid date.');

            return;
        }

        // Without overflow, like the browser: on 29 Feb, 18 years back is 28 Feb when that year has no 29th, so someone
        // born on 1 Mar waits until tomorrow. Someone born on 29 Feb still comes of age on 1 Mar in other years.
        $latestAllowed = LocalToday::latest()->subYearsNoOverflow($this->years);

        if ($birthDate->greaterThan($latestAllowed)) {
            $fail("You must be at least {$this->years} years old.");
        }
    }
}
