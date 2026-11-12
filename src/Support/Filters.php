<?php

declare(strict_types=1);

namespace Bladewell\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The fields people can filter by, and the one place a filter builder's request is checked and applied. read() turns
 * the request into conditions, refusing any field, operator or value your list doesn't allow; apply() puts them on a
 * query with every value bound, and filter() on a collection with the same rules. A column name, an operator or a
 * piece of SQL never comes from the request.
 */
final readonly class Filters
{
    /** Conditions read from one request; the rest are left out, and said so. */
    public const MAX_CONDITIONS = 10;

    /** Characters of text to match. */
    public const MAX_TEXT = 200;

    /** Choices an "any of" can take. */
    public const MAX_CHOICES = 50;

    /** Days "in the last … days" can go back. */
    public const MAX_DAYS = 3650;

    /**
     * Bytes of the request's filters field; more is refused unread. Room for ten conditions of many long choices, and
     * within the 16 KB a URL can be through Cloudflare and most servers.
     */
    public const MAX_INPUT = 16000;

    /** @param  array<string, FilterField>  $fields  by key */
    private function __construct(public array $fields) {}

    /** @param  list<FilterField>  $fields */
    public static function make(array $fields): self
    {
        $byKey = [];
        foreach ($fields as $field) {
            if (isset($byKey[$field->key])) {
                throw new InvalidArgumentException("Two filter fields share the key [{$field->key}]: give each its own.");
            }
            $byKey[$field->key] = $field;
        }

        return new self($byKey);
    }

    /**
     * The types the fields have, each once: the builder keeps one list of conditions for each.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return array_values(array_unique(array_map(static fn (FilterField $field): string => $field->type->value, $this->fields)));
    }

    /**
     * Every value control any field's conditions can show, each once: the builder keeps one copy of each.
     *
     * @return list<string>
     */
    public function parts(): array
    {
        $parts = [];
        foreach ($this->fields as $field) {
            foreach ($field->type->operators() as $operator) {
                array_push($parts, ...$field->parts($operator));
            }
        }

        return array_values(array_unique($parts));
    }

    /**
     * What the builder's script needs: each field's type and label, the controls each of its conditions shows, and the
     * limits read() holds values to, so a row it would turn down is marked before it's sent rather than quietly left out.
     *
     * @return array{types: array<string, string>, labels: array<string, string>, parts: array<string, array<string, list<string>>>, limits: array{text: int, choices: int, days: int}}
     */
    public function forScript(): array
    {
        $parts = [];
        foreach ($this->fields as $key => $field) {
            foreach ($field->type->operators() as $operator) {
                $parts[$key][$operator->value] = $field->parts($operator);
            }
        }

        return [
            'types' => array_map(static fn (FilterField $field): string => $field->type->value, $this->fields),
            'labels' => array_map(static fn (FilterField $field): string => $field->label, $this->fields),
            'parts' => $parts,
            'limits' => ['text' => self::MAX_TEXT, 'choices' => self::MAX_CHOICES, 'days' => self::MAX_DAYS],
        ];
    }

    /**
     * The request's filters, as the builder sends them (a JSON string) or as Livewire binds them (an array). It never
     * throws: what it can't use is left out, with the reason in the state for the builder to show.
     *
     * @param  ?string  $timezone  the person's, for which day a date means; the app's by default
     */
    public function read(mixed $input, ?string $timezone = null): FilterState
    {
        $timezone = self::timezone($timezone);
        if ($input === null || $input === '' || $input === []) {
            return new FilterState($this, timezone: $timezone);
        }
        // What was sent, for the builder to tell a state it sent from one your code set (see FilterState::$sent).
        $sent = is_string($input) && strlen($input) <= self::MAX_INPUT ? $input : '';
        if (is_string($input)) {
            $input = strlen($input) <= self::MAX_INPUT ? json_decode($input, true, 6) : null;
        }
        $rules = is_array($input) ? ($input['rules'] ?? []) : null;
        if (! is_array($rules) || ! array_is_list($rules)) {
            return new FilterState($this, error: 'These filters couldn\'t be read.', timezone: $timezone, sent: $sent);
        }

        $conditions = [];
        $rows = [];
        foreach (array_slice($rules, 0, self::MAX_CONDITIONS) as $rule) {
            [$condition, $row] = $this->readRule(is_array($rule) ? $rule : []);
            $rows[] = $row;
            if ($condition !== null) {
                $conditions[] = $condition;
            }
        }

        return new FilterState(
            $this,
            conditions: $conditions,
            any: ($input['match'] ?? 'all') === 'any',
            rows: $rows,
            error: count($rules) > self::MAX_CONDITIONS ? 'Only the first '.self::MAX_CONDITIONS.' filters are used.' : null,
            timezone: $timezone,
            sent: $sent,
        );
    }

    /**
     * @param  array<array-key, mixed>  $rule
     * @return array{0: ?FilterCondition, 1: array{field: ?string, op: ?string, value: string|list<string>|null, error: ?string}}
     */
    private function readRule(array $rule): array
    {
        $key = is_string($rule['field'] ?? null) ? $rule['field'] : null;
        $field = $key !== null ? ($this->fields[$key] ?? null) : null;
        $operator = is_string($rule['op'] ?? null) ? FilterOperator::tryFrom($rule['op']) : null;
        $row = ['field' => $field?->key, 'op' => null, 'value' => self::shown($rule['value'] ?? null), 'error' => null];
        if ($field === null) {
            // A saved filter's field you've since removed: named, so people see what it was rather than another field.
            $error = $key !== null && $key !== '' ? '“'.mb_substr($key, 0, 60).'” can\'t be filtered by. Choose a field.' : 'Choose a field.';

            return [null, [...$row, 'error' => $error]];
        }
        if ($operator === null || ! in_array($operator, $field->type->operators(), true)) {
            return [null, [...$row, 'error' => 'Choose a condition.']];
        }
        $row['op'] = $operator->value;
        [$value, $error] = $this->value($field, $operator, $rule['value'] ?? null);

        return $error === null
            ? [new FilterCondition($field, $operator, $value), $row]
            : [null, [...$row, 'error' => $error]];
    }

    /**
     * The value, checked and in the form apply() uses, or why it can't be.
     *
     * @return array{0: string|float|int|list<string>|array{0: string|float, 1: string|float}|null, 1: ?string}
     */
    private function value(FilterField $field, FilterOperator $operator, mixed $value): array
    {
        $type = $field->type;
        $arity = $operator->arity();
        if ($arity === 'none') {
            return [null, null];
        }
        if ($arity === 'list') {
            $chosen = is_array($value) ? array_values(array_unique(array_filter($value, 'is_string'))) : [];
            if ($chosen === []) {
                return [null, 'Choose at least one.'];
            }
            if (count($chosen) > self::MAX_CHOICES) {
                return [null, 'Choose up to '.self::MAX_CHOICES.'.'];
            }
            $unknown = array_diff($chosen, array_map('strval', array_keys($field->options)));
            if ($unknown !== []) {
                // An option you've since removed, or one this person may no longer see: named, as the list can't show it.
                return [null, 'Not in the list: “'.implode('”, “', array_map(static fn (string $one): string => mb_substr($one, 0, 60), array_slice($unknown, 0, 5))).'”. Choose from the list.'];
            }

            return [$chosen, null];
        }
        if ($arity === 'two') {
            $ends = is_array($value) && array_is_list($value) && count($value) === 2 ? array_map(fn (mixed $end): string|float|int|null => $this->one($field, $end), $value) : [null, null];
            if ($ends[0] === null || $ends[1] === null) {
                return [null, match (true) {
                    $type->isDate() => 'Choose both dates.',
                    $field->whole => 'Enter both as whole numbers.',
                    default => 'Enter both numbers.',
                }];
            }
            if ($ends[0] > $ends[1]) {
                return [null, $type->isDate() ? 'Choose the earlier date first.' : 'Enter the smaller number first.'];
            }

            return [[$ends[0], $ends[1]], null];
        }
        if ($operator === FilterOperator::LastDays) {
            $days = is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;

            return $days >= 1 && $days <= self::MAX_DAYS ? [$days, null] : [null, 'Enter a number of days from 1 to '.self::MAX_DAYS.'.'];
        }
        $one = $this->one($field, $value);

        return $one !== null ? [$one, null] : [null, match ($type) {
            FilterType::Number => $field->whole ? 'Enter a whole number.' : 'Enter a number.',
            FilterType::Date, FilterType::DateTime => 'Choose a date.',
            default => 'Enter something to match, up to '.self::MAX_TEXT.' characters.',
        }];
    }

    /**
     * One value of the field's type, or null: text (trimmed, not empty, not too long), a number (a whole one, sent as
     * an integer, with whole()), or a real Y-m-d day from 1000 to 9999, the years every database stores.
     */
    private function one(FilterField $field, mixed $value): string|float|int|null
    {
        $type = $field->type;
        if ($type === FilterType::Number) {
            $number = (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) && is_finite((float) $value) && abs((float) $value) < 1e15 ? (float) $value : null;
            if ($number === null || ! $field->whole) {
                return $number;
            }

            // A column of whole numbers can't be compared with 12.5: PostgreSQL refuses it outright.
            return floor($number) === $number ? (int) $number : null;
        }
        if (! is_string($value)) {
            return null;
        }
        if ($type->isDate()) {
            $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return $day !== false && $day->format('Y-m-d') === $value && $value >= '1000-01-01' && $value <= '9999-12-31' ? $value : null;
        }
        $text = trim($value);

        return $text !== '' && mb_strlen($text) <= self::MAX_TEXT ? $text : null;
    }

    /**
     * A row's value as sent, made safe to show back: strings, or a short list of them.
     *
     * @return string|list<string>|null
     */
    private static function shown(mixed $value): string|array|null
    {
        if (is_array($value)) {
            return array_values(array_map(static fn (mixed $item): string => mb_substr(is_scalar($item) ? (string) $item : '', 0, self::MAX_TEXT), array_slice(array_filter($value, 'is_scalar'), 0, self::MAX_CHOICES)));
        }

        return is_scalar($value) ? mb_substr((string) $value, 0, self::MAX_TEXT) : null;
    }

    // --- On a query -----------------------------------------------------------------------------------------------

    /**
     * Puts the conditions on the query, grouped in brackets so they never loosen the query's own: all of them, or any
     * with "match any". Your query's other constraints, such as the person's own records, still hold.
     *
     * @template TBuilder of EloquentBuilder<*>|QueryBuilder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function apply(EloquentBuilder|QueryBuilder $query, FilterState $state): EloquentBuilder|QueryBuilder
    {
        if ($state->isEmpty()) {
            return $query;
        }
        $query->where(function (EloquentBuilder|QueryBuilder $group) use ($state): void {
            foreach ($state->conditions as $condition) {
                $group->where(fn (EloquentBuilder|QueryBuilder $one) => $this->constrain($one, $condition, $state->timezone), boolean: $state->any ? 'or' : 'and');
            }
        });

        return $query;
    }

    /** @param  EloquentBuilder<*>|QueryBuilder  $query */
    private function constrain(EloquentBuilder|QueryBuilder $query, FilterCondition $condition, string $timezone): void
    {
        $field = $condition->field;
        if ($field->using !== null) {
            ($field->using)($query, $condition->operator, $condition->value);

            return;
        }

        // Rows with nothing in them count for "doesn't contain", "is not", "none of" and "is no", as people expect.
        if ($condition->operator->includesEmpty()) {
            $query->whereNull($field->columnName())->orWhere(fn (EloquentBuilder|QueryBuilder $set) => $this->match($set, $condition, $timezone));

            return;
        }
        $this->match($query, $condition, $timezone);
    }

    /** @param  EloquentBuilder<*>|QueryBuilder  $query */
    private function match(EloquentBuilder|QueryBuilder $query, FilterCondition $condition, string $timezone): void
    {
        $field = $condition->field;
        $column = $field->columnName();
        $value = self::stored($condition);
        $wrapped = self::grammar($query)->wrap($column);
        // Case-insensitive on every database, and % and _ typed in match themselves. SQLite's LOWER() only changes A to Z,
        // so there "é" doesn't match "É"; MySQL and PostgreSQL lower every letter.
        $like = static fn (mixed $pattern): string => str_replace(['!', '%', '_'], ['!!', '!%', '!_'], (string) $pattern);

        if ($field->type->isDate() && $condition->operator->arity() !== 'none') {
            [$from, $to, $toExcluded] = $this->storedBounds($condition, $timezone);
            $query->when($from !== null, fn ($within) => $within->where($column, '>=', $from))
                ->when($to !== null, fn ($within) => $within->where($column, $toExcluded ? '<' : '<=', $to));

            return;
        }

        match ($condition->operator) {
            FilterOperator::Empty => $field->type === FilterType::Text
                ? $query->where(fn (EloquentBuilder|QueryBuilder $empty) => $empty->whereNull($column)->orWhere($column, ''))
                : $query->whereNull($column),
            FilterOperator::NotEmpty => $field->type === FilterType::Text
                ? $query->whereNotNull($column)->where($column, '<>', '')
                : $query->whereNotNull($column),
            FilterOperator::Contains => $query->whereRaw("LOWER({$wrapped}) LIKE LOWER(?) ESCAPE '!'", ['%'.$like($value).'%']),
            FilterOperator::NotContains => $query->whereRaw("LOWER({$wrapped}) NOT LIKE LOWER(?) ESCAPE '!'", ['%'.$like($value).'%']),
            FilterOperator::StartsWith => $query->whereRaw("LOWER({$wrapped}) LIKE LOWER(?) ESCAPE '!'", [$like($value).'%']),
            FilterOperator::EndsWith => $query->whereRaw("LOWER({$wrapped}) LIKE LOWER(?) ESCAPE '!'", ['%'.$like($value)]),
            FilterOperator::Is => $query->whereRaw("LOWER({$wrapped}) = LOWER(?)", [(string) $value]),
            FilterOperator::IsNot => $query->whereRaw("LOWER({$wrapped}) <> LOWER(?)", [(string) $value]),
            FilterOperator::Equals => $query->where($column, '=', $value),
            FilterOperator::NotEquals => $query->where($column, '<>', $value),
            FilterOperator::Less => $query->where($column, '<', $value),
            FilterOperator::LessOrEqual => $query->where($column, '<=', $value),
            FilterOperator::Greater => $query->where($column, '>', $value),
            FilterOperator::GreaterOrEqual => $query->where($column, '>=', $value),
            FilterOperator::Between => $query->whereBetween($column, (array) $value),
            FilterOperator::AnyOf => $query->whereIn($column, (array) $value),
            FilterOperator::NoneOf => $query->whereNotIn($column, (array) $value),
            FilterOperator::Yes => $query->where($column, true),
            FilterOperator::No => $query->where($column, false),
            default => null,
        };
    }

    /**
     * The condition's value as the column stores it: through the field's storedAs(), each value of it.
     *
     * @return string|float|int|list<string|float|int>|null
     */
    public static function stored(FilterCondition $condition): string|float|int|array|null
    {
        $convert = $condition->field->storedAs;
        $value = $condition->value;
        if ($convert === null || $value === null || $condition->operator === FilterOperator::LastDays) {
            return $value;
        }

        return is_array($value) ? array_map(static fn (string|float $one): string|float|int => $convert($one), $value) : $convert($value);
    }

    /**
     * Where a date condition starts and ends, as the column stores it: from (included), to, and whether to is excluded;
     * null for no end. A date column compares days as they are. A date-time column runs from the first day's start to
     * the last day's end in the person's time zone, as instants in the app's: a day in Tokyo isn't the same 24 hours as
     * in London. Ranges, never DATE(column), so the column's index still works. "In the last 7 days" is today and the 6
     * before it.
     *
     * @return array{0: ?string, 1: ?string, 2: bool}
     */
    public function bounds(FilterCondition $condition, string $timezone): array
    {
        $value = $condition->value;
        $day = static fn (string $iso): CarbonImmutable => CarbonImmutable::createFromFormat('!Y-m-d', $iso, $timezone) ?: CarbonImmutable::now($timezone);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        [$first, $last] = match ($condition->operator) {
            FilterOperator::On => [(string) $value, (string) $value],
            FilterOperator::Before => [null, $day((string) $value)->subDay()->format('Y-m-d')],
            FilterOperator::After => [$day((string) $value)->addDay()->format('Y-m-d'), null],
            FilterOperator::Between => [(string) ((array) $value)[0], (string) ((array) $value)[1]],
            FilterOperator::LastDays => [$today->subDays((int) $value - 1)->format('Y-m-d'), $today->format('Y-m-d')],
            default => [null, null],
        };
        if ($condition->field->type === FilterType::Date) {
            return [$first, $last, false];
        }
        $stored = (string) config('app.timezone', 'UTC');
        $start = static fn (string $iso): string => $day($iso)->setTimezone($stored)->format('Y-m-d H:i:s');

        return [
            $first === null ? null : $start($first),
            $last === null ? null : $start($day($last)->addDay()->format('Y-m-d')),
            true,
        ];
    }

    /**
     * bounds() as the column stores them: through the field's storedAs(), as for a date kept as a Unix timestamp,
     * ->storedAs(fn ($moment) => strtotime($moment)).
     *
     * @return array{0: string|float|int|null, 1: string|float|int|null, 2: bool}
     */
    public function storedBounds(FilterCondition $condition, string $timezone): array
    {
        [$from, $to, $toExcluded] = $this->bounds($condition, $timezone);
        $convert = $condition->field->storedAs;

        return $convert === null ? [$from, $to, $toExcluded] : [
            $from === null ? null : $convert($from),
            $to === null ? null : $convert($to),
            $toExcluded,
        ];
    }

    /**
     * The time zone dates are read in: the one given if PHP knows it, else the app's. A person's saved setting can be
     * anything, and a filter mustn't fail on it.
     */
    private static function timezone(?string $timezone): string
    {
        $app = (string) config('app.timezone', 'UTC');
        if ($timezone === null || $timezone === '') {
            return $app;
        }
        try {
            new \DateTimeZone($timezone);

            return $timezone;
        } catch (\Exception) {
            return $app;
        }
    }

    /** @param  EloquentBuilder<*>|QueryBuilder  $query */
    private static function grammar(EloquentBuilder|QueryBuilder $query): Grammar
    {
        return $query instanceof EloquentBuilder ? $query->getQuery()->getGrammar() : $query->getGrammar();
    }

    // --- On a collection --------------------------------------------------------------------------------------------

    /**
     * The rows the conditions keep, by the same rules as apply(): for data already in memory, an API's answer, a demo.
     * Each row's value is read with data_get(), by the field's column. See FilterCollection.
     *
     * @template TRow
     *
     * @param  iterable<array-key, TRow>  $rows
     * @return Collection<int, TRow>
     */
    public function filter(iterable $rows, FilterState $state): Collection
    {
        return (new FilterCollection($this, $state))->keep($rows);
    }
}
