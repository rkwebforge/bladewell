<?php

declare(strict_types=1);

namespace Bladewell\Support;

/**
 * A condition a filter can put on a field. The value is what the URL carries; the label is what people read, in plain
 * English: edit it here.
 */
enum FilterOperator: string
{
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Is = 'is';
    case IsNot = 'is_not';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case Equals = 'eq';
    case NotEquals = 'neq';
    case Less = 'lt';
    case LessOrEqual = 'lte';
    case Greater = 'gt';
    case GreaterOrEqual = 'gte';
    case Between = 'between';
    case On = 'on';
    case Before = 'before';
    case After = 'after';
    case LastDays = 'last_days';
    case AnyOf = 'any_of';
    case NoneOf = 'none_of';
    case Yes = 'yes';
    case No = 'no';
    case Empty = 'empty';
    case NotEmpty = 'not_empty';

    public function label(): string
    {
        return match ($this) {
            self::Contains => 'contains',
            self::NotContains => 'doesn\'t contain',
            self::Is => 'is',
            self::IsNot => 'is not',
            self::StartsWith => 'starts with',
            self::EndsWith => 'ends with',
            self::Equals => 'is',
            self::NotEquals => 'is not',
            self::Less => 'is less than',
            self::LessOrEqual => 'is at most',
            self::Greater => 'is more than',
            self::GreaterOrEqual => 'is at least',
            self::Between => 'is between',
            self::On => 'is on',
            self::Before => 'is before',
            self::After => 'is after',
            self::LastDays => 'is in the last … days',
            self::AnyOf => 'is any of',
            self::NoneOf => 'is none of',
            self::Yes => 'is yes',
            self::No => 'is no',
            self::Empty => 'is empty',
            self::NotEmpty => 'isn\'t empty',
        };
    }

    /** How many values it takes: none (is empty, is yes), two (between), a list (any of), or one. */
    public function arity(): string
    {
        return match ($this) {
            self::Empty, self::NotEmpty, self::Yes, self::No => 'none',
            self::Between => 'two',
            self::AnyOf, self::NoneOf => 'list',
            default => 'one',
        };
    }

    /**
     * Whether a row with no value matches: "doesn't contain x" includes rows with nothing in them, as people expect,
     * though SQL's NOT LIKE alone would leave them out.
     */
    public function includesEmpty(): bool
    {
        return in_array($this, [self::NotContains, self::IsNot, self::NotEquals, self::NoneOf, self::No], true);
    }
}
