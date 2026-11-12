<?php

declare(strict_types=1);

namespace Bladewell\Support;

/**
 * One checked condition: a field from your list, an operator its type offers, and a value of the right kind. Only
 * Filters::read() makes them, so a condition is always safe to apply.
 */
final readonly class FilterCondition
{
    /**
     * @param  string|float|int|list<string>|array{0: string|float, 1: string|float}|null  $value  text, a number, a
     *                                                                                             Y-m-d day, a count of days, two of them, a list of choices, or none
     */
    public function __construct(
        public FilterField $field,
        public FilterOperator $operator,
        public string|float|int|array|null $value,
    ) {}

    /** @return array{field: string, op: string, value: string|float|int|list<string>|array{0: string|float, 1: string|float}|null} */
    public function toArray(): array
    {
        return ['field' => $this->field->key, 'op' => $this->operator->value, 'value' => $this->value];
    }
}
