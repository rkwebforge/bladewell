<?php

declare(strict_types=1);

namespace Bladewell\Support;

/**
 * What a filterable field holds, which decides the conditions it offers and how its values are checked.
 *
 * A value stored differently from how people enter it (money in pence, a duration in seconds) needs no new type:
 * FilterField::number(...)->storedAs(fn ($value) => ...). A new type takes four places, all in your copies:
 *  - here: the case, and the conditions it offers in operators();
 *  - FilterField: a constructor like number(), and in parts() the value controls its conditions show;
 *  - Filters: in value(), how its values are checked, and in match(), the SQL each condition is (FilterCollection the
 *    same for collections);
 *  - the builder's part.blade.php: a case rendering its value control. The script needs nothing: it swaps the controls
 *    parts() names, and reads a select, a date picker, a number or a text field.
 */
enum FilterType: string
{
    case Text = 'text';
    case Number = 'number';
    // A date column (Y-m-d): compared as it is.
    case Date = 'date';
    // A date and time column: a day the person picks is that day in their time zone, turned into the stored one.
    case DateTime = 'datetime';
    case Choice = 'choice';
    case Boolean = 'boolean';

    /**
     * The conditions it offers, in the order they're listed.
     *
     * @return list<FilterOperator>
     */
    public function operators(): array
    {
        return match ($this) {
            self::Text => [FilterOperator::Contains, FilterOperator::NotContains, FilterOperator::Is, FilterOperator::IsNot, FilterOperator::StartsWith, FilterOperator::EndsWith, FilterOperator::Empty, FilterOperator::NotEmpty],
            self::Number => [FilterOperator::Equals, FilterOperator::NotEquals, FilterOperator::Less, FilterOperator::LessOrEqual, FilterOperator::Greater, FilterOperator::GreaterOrEqual, FilterOperator::Between, FilterOperator::Empty, FilterOperator::NotEmpty],
            self::Date, self::DateTime => [FilterOperator::On, FilterOperator::Before, FilterOperator::After, FilterOperator::Between, FilterOperator::LastDays, FilterOperator::Empty, FilterOperator::NotEmpty],
            self::Choice => [FilterOperator::AnyOf, FilterOperator::NoneOf, FilterOperator::Empty, FilterOperator::NotEmpty],
            self::Boolean => [FilterOperator::Yes, FilterOperator::No],
        };
    }

    /** Dates and date-times take the same values (Y-m-d days) and show the same picker. */
    public function isDate(): bool
    {
        return $this === self::Date || $this === self::DateTime;
    }
}
