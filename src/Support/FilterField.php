<?php

declare(strict_types=1);

namespace Bladewell\Support;

use Closure;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * One field people can filter by. Its key is what the URL carries; the column (the key, unless set) is what the query
 * uses, so a column name never comes from the request. Immutable: column(), storedAs() and using() return a changed copy.
 */
final readonly class FilterField
{
    /**
     * @param  array<string, string>  $options  with Choice: value => label, the only values accepted
     * @param  (Closure(Builder, FilterOperator, mixed): mixed)|null  $using
     * @param  (Closure(string|float): (string|float|int))|null  $storedAs
     */
    private function __construct(
        public string $key,
        public string $label,
        public FilterType $type,
        public array $options = [],
        public ?string $column = null,
        public ?Closure $using = null,
        public ?Closure $storedAs = null,
        public bool $whole = false,
    ) {}

    public static function text(string $key, string $label): self
    {
        return new self($key, $label, FilterType::Text);
    }

    public static function number(string $key, string $label): self
    {
        return new self($key, $label, FilterType::Number);
    }

    /**
     * A date column (Y-m-d). For a date and time column (created_at), use dateTime(): compared with a day, a time on
     * it is after it, so "on" and "before" would leave the day's own rows out.
     */
    public static function date(string $key, string $label): self
    {
        return new self($key, $label, FilterType::Date);
    }

    /** A date and time column, stored in the app's time zone: days are turned into ranges in it. */
    public static function dateTime(string $key, string $label): self
    {
        return new self($key, $label, FilterType::DateTime);
    }

    /** @param  array<string, string>  $options  value => label: only these values are accepted */
    public static function choice(string $key, string $label, array $options): self
    {
        if ($options === []) {
            throw new \InvalidArgumentException("Filter field [{$key}] is a choice with no options: give it at least one, or leave it out.");
        }

        return new self($key, $label, FilterType::Choice, $options);
    }

    public static function boolean(string $key, string $label): self
    {
        return new self($key, $label, FilterType::Boolean);
    }

    /** The column it filters, when it isn't named like the key: "created_at", "orders.total". */
    public function column(string $column): self
    {
        return new self($this->key, $this->label, $this->type, $this->options, $column, $this->using, $this->storedAs, $this->whole);
    }

    /**
     * How a value people enter is stored, when that differs: an amount in pounds kept in pence is
     * ->storedAs(fn ($pounds) => (int) round($pounds * 100)). Applied to each value, after it's checked. On a date
     * field it gets each end of the range ("2026-11-11" for a date column, "2026-11-11 05:00:00" for a date-time):
     * a Unix timestamp column is ->storedAs(fn ($moment) => strtotime($moment)). The moment is in the app's time zone,
     * which Laravel makes PHP's, so strtotime() reads it right; don't add ' UTC' unless the app's zone is UTC.
     *
     * @param  Closure(string|float): (string|float|int)  $storedAs
     */
    public function storedAs(Closure $storedAs): self
    {
        return new self($this->key, $this->label, $this->type, $this->options, $this->column, $this->using, $storedAs, $this->whole);
    }

    /**
     * Filters the query yourself, for a relation or a computed value: fn ($query, $operator, $value) =>
     * $query->whereHas('customer', ...). Handle every condition the field's type offers, as $operator says which:
     * people can pick "doesn't contain" as well as "contains". The value is already checked. Only for queries, not
     * collections.
     *
     * @param  Closure(Builder, FilterOperator, mixed): mixed  $using
     */
    public function using(Closure $using): self
    {
        return new self($this->key, $this->label, $this->type, $this->options, $this->column, $using, $this->storedAs, $this->whole);
    }

    /**
     * Whole numbers only, for a column that holds them (a count, a quantity): 12.5 is turned down with "Enter a whole
     * number." and the box takes no decimals. PostgreSQL won't compare an integer column with a decimal at all.
     */
    public function whole(): self
    {
        return new self($this->key, $this->label, $this->type, $this->options, $this->column, $this->using, $this->storedAs, true);
    }

    public function columnName(): string
    {
        return $this->column ?? $this->key;
    }

    /**
     * The value controls a condition on this field shows, in order: none (is empty), the field's own list (any of),
     * a number of days, or its type's one or two. The builder renders them and its script swaps them from this alone.
     *
     * @return list<string>
     */
    public function parts(FilterOperator $operator): array
    {
        $arity = $operator->arity();

        return match (true) {
            $arity === 'none' => [],
            $arity === 'list' => ['choice:'.$this->key],
            $operator === FilterOperator::LastDays => ['days'],
            $this->type === FilterType::Number && $this->whole => $arity === 'two' ? ['whole', 'whole-to'] : ['whole'],
            $this->type === FilterType::Number => $arity === 'two' ? ['number', 'number-to'] : ['number'],
            $this->type->isDate() => $arity === 'two' ? ['date', 'date-to'] : ['date'],
            default => ['text'],
        };
    }
}
