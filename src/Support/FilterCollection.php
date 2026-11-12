<?php

declare(strict_types=1);

namespace Bladewell\Support;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Filters::filter(): the conditions on rows already in memory, by the same rules as on a query, a row with nothing in
 * it included where SQL's would be. Each condition is prepared once (its dates' bounds, its value as stored), not
 * again for every row.
 */
final readonly class FilterCollection
{
    /** @var list<Closure(mixed): bool> one test a condition, each taking a row's value */
    private array $tests;

    /** @var list<string> each condition's column, read from a row with data_get() */
    private array $columns;

    public function __construct(Filters $filters, private FilterState $state)
    {
        $tests = [];
        $columns = [];
        foreach ($state->conditions as $condition) {
            if ($condition->field->using !== null) {
                throw new LogicException("Filter field [{$condition->field->key}] filters with using(), which only works on queries.");
            }
            $tests[] = $this->test($filters, $condition);
            $columns[] = $condition->field->columnName();
        }
        $this->tests = $tests;
        $this->columns = $columns;
    }

    /**
     * @template TRow
     *
     * @param  iterable<array-key, TRow>  $rows
     * @return Collection<int, TRow>
     */
    public function keep(iterable $rows): Collection
    {
        if ($this->tests === []) {
            return Collection::make($rows)->values();
        }

        return Collection::make($rows)->filter(function (mixed $row): bool {
            foreach ($this->tests as $index => $test) {
                $kept = $test(data_get($row, $this->columns[$index]));
                if ($kept === $this->state->any) {
                    // Any: one kept is enough. All: one left out is enough.
                    return $kept;
                }
            }

            return ! $this->state->any;
        })->values();
    }

    /** @return Closure(mixed): bool */
    private function test(Filters $filters, FilterCondition $condition): Closure
    {
        $field = $condition->field;
        $operator = $condition->operator;
        $isText = $field->type === FilterType::Text;
        $empty = static fn (mixed $actual): bool => $actual === null || ($isText && $actual === '');

        if ($operator === FilterOperator::Empty) {
            return $empty;
        }
        if ($operator === FilterOperator::NotEmpty) {
            return static fn (mixed $actual): bool => ! $empty($actual);
        }
        $matches = $this->matches($filters, $condition);
        $includesEmpty = $operator->includesEmpty();

        return static fn (mixed $actual): bool => $actual === null ? $includesEmpty : $matches($actual);
    }

    /** @return Closure(mixed): bool for a row's value that isn't null */
    private function matches(Filters $filters, FilterCondition $condition): Closure
    {
        $field = $condition->field;
        $operator = $condition->operator;
        $value = Filters::stored($condition);

        if ($field->type === FilterType::Text) {
            $wanted = mb_strtolower((string) (is_array($value) ? '' : $value));

            return match ($operator) {
                FilterOperator::Contains => static fn (mixed $actual): bool => str_contains(mb_strtolower((string) $actual), $wanted),
                FilterOperator::NotContains => static fn (mixed $actual): bool => ! str_contains(mb_strtolower((string) $actual), $wanted),
                FilterOperator::StartsWith => static fn (mixed $actual): bool => str_starts_with(mb_strtolower((string) $actual), $wanted),
                FilterOperator::EndsWith => static fn (mixed $actual): bool => str_ends_with(mb_strtolower((string) $actual), $wanted),
                FilterOperator::Is => static fn (mixed $actual): bool => mb_strtolower((string) $actual) === $wanted,
                FilterOperator::IsNot => static fn (mixed $actual): bool => mb_strtolower((string) $actual) !== $wanted,
                default => static fn (): bool => false,
            };
        }
        if ($field->type === FilterType::Number) {
            [$low, $high] = is_array($value) ? [(float) $value[0], (float) $value[1]] : [(float) $value, (float) $value];

            return match ($operator) {
                FilterOperator::Equals => static fn (mixed $actual): bool => (float) $actual == $low,
                FilterOperator::NotEquals => static fn (mixed $actual): bool => (float) $actual != $low,
                FilterOperator::Less => static fn (mixed $actual): bool => (float) $actual < $low,
                FilterOperator::LessOrEqual => static fn (mixed $actual): bool => (float) $actual <= $low,
                FilterOperator::Greater => static fn (mixed $actual): bool => (float) $actual > $low,
                FilterOperator::GreaterOrEqual => static fn (mixed $actual): bool => (float) $actual >= $low,
                FilterOperator::Between => static fn (mixed $actual): bool => (float) $actual >= $low && (float) $actual <= $high,
                default => static fn (): bool => false,
            };
        }
        if ($field->type === FilterType::Choice) {
            $chosen = array_map('strval', (array) $value);
            $any = $operator === FilterOperator::AnyOf;

            return static fn (mixed $actual): bool => in_array((string) $actual, $chosen, true) === $any;
        }
        if ($field->type === FilterType::Boolean) {
            $yes = $operator === FilterOperator::Yes;

            return static fn (mixed $actual): bool => (bool) $actual === $yes;
        }

        // Dates: the bounds apply() puts in SQL, compared as the same strings, or as the field stores them with storedAs().
        [$from, $to, $toExcluded] = $filters->storedBounds($condition, $this->state->timezone);
        $format = $field->type === FilterType::Date ? 'Y-m-d' : 'Y-m-d H:i:s';
        $timezone = (string) config('app.timezone', 'UTC');
        $converted = $field->storedAs !== null;

        return static function (mixed $actual) use ($from, $to, $toExcluded, $format, $timezone, $converted): bool {
            $stored = match (true) {
                $converted => $actual,
                $actual instanceof DateTimeInterface => CarbonImmutable::instance($actual)->setTimezone($timezone)->format($format),
                default => (string) $actual,
            };

            return ($from === null || $stored >= $from) && ($to === null || ($toExcluded ? $stored < $to : $stored <= $to));
        };
    }
}
