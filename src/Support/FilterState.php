<?php

declare(strict_types=1);

namespace Bladewell\Support;

/**
 * What Filters::read() made of a request: the conditions that passed, whether any or all must match, and every row as
 * sent with what was wrong with it, so the builder shows people their filters back with the reason next to each. It
 * keeps the Filters that read it, so the builder needs only the state.
 */
final readonly class FilterState
{
    /**
     * @param  list<FilterCondition>  $conditions  the ones that passed, to apply
     * @param  list<array{field: ?string, op: ?string, value: string|list<string>|null, error: ?string}>  $rows  every row as
     *                                                                                                           sent, cleaned up for showing back
     * @param  ?string  $error  about the filters as a whole: unreadable, or too many
     * @param  string  $sent  the filters as sent, when a string: in Livewire, the builder compares it with what it sent
     *                        itself, and shows these rows instead when your component set the filters (a saved one loaded)
     */
    public function __construct(
        public Filters $filters,
        public array $conditions = [],
        public bool $any = false,
        public array $rows = [],
        public ?string $error = null,
        public string $timezone = 'UTC',
        public string $sent = '',
    ) {}

    /**
     * Each row as the builder shows it back: its field, its condition, the value controls that condition shows with
     * what was sent in them, and the reason it was turned down with the control it's about. A field that isn't yours
     * (one since removed) shows none chosen, not another in its place, and so does a condition its type doesn't offer:
     * the row can't be sent until people choose. Without a chosen field, `field` is the first, for its conditions.
     *
     * @return list<array{field: FilterField, fieldChosen: bool, operator: ?FilterOperator, parts: array<string, string|list<string>|null>, error: ?string, errorOn: ?string}>
     */
    public function shownRows(): array
    {
        return array_map($this->shown(...), $this->rows);
    }

    /**
     * A new row, as the builder's template: the first field, its first condition, nothing in it.
     *
     * @return array{field: FilterField, fieldChosen: bool, operator: ?FilterOperator, parts: array<string, string|list<string>|null>, error: ?string, errorOn: ?string}
     */
    public function blankRow(): array
    {
        $field = $this->filters->fields[array_key_first($this->filters->fields)];

        return $this->shown(['field' => $field->key, 'op' => $field->type->operators()[0]->value, 'value' => null, 'error' => null]);
    }

    /**
     * @param  array{field: ?string, op: ?string, value: string|list<string>|null, error: ?string}  $row
     * @return array{field: FilterField, fieldChosen: bool, operator: ?FilterOperator, parts: array<string, string|list<string>|null>, error: ?string, errorOn: ?string}
     */
    private function shown(array $row): array
    {
        $fields = $this->filters->fields;
        $chosen = $fields[(string) $row['field']] ?? null;
        $field = $chosen ?? $fields[array_key_first($fields)];
        $operator = $chosen === null ? null : FilterOperator::tryFrom((string) $row['op']);
        $operator = $operator !== null && in_array($operator, $field->type->operators(), true) ? $operator : null;
        $value = $row['value'];
        $parts = $operator === null ? [] : $field->parts($operator);

        return [
            'field' => $field,
            'fieldChosen' => $chosen !== null,
            'operator' => $operator,
            // One value goes to the first control; two, one each; a list, to the choice.
            'parts' => array_combine($parts, match (true) {
                count($parts) === 2 => array_slice(array_pad(array_values((array) $value), 2, null), 0, 2),
                $parts === [] => [],
                default => [str_starts_with($parts[0], 'choice:') ? (array) $value : (is_array($value) ? null : $value)],
            }),
            'error' => $row['error'],
            // Which control the reason is about: the field if it wasn't one of yours, the condition if its type doesn't
            // offer it (or it takes no value), else the first value.
            'errorOn' => match (true) {
                $row['error'] === null => null,
                $row['field'] === null => 'field',
                $row['op'] === null || $parts === [] => 'operator',
                default => $parts[0],
            },
        ];
    }

    public function isEmpty(): bool
    {
        return $this->conditions === [];
    }

    public function hasErrors(): bool
    {
        return $this->error !== null || array_filter(array_column($this->rows, 'error')) !== [];
    }

    /** What the builder's field holds: the conditions that passed, so a link never carries one that didn't. */
    public function toJson(): string
    {
        if ($this->conditions === []) {
            return '';
        }

        return (string) json_encode([
            'match' => $this->any ? 'any' : 'all',
            'rules' => array_map(static fn (FilterCondition $condition): array => $condition->toArray(), $this->conditions),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
