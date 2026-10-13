<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Enums;

use Filament\Support\Contracts\HasLabel;

enum ConditionOperator: string implements HasLabel
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case Contains = 'contains';
    case In = 'in';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';
    case Changed = 'changed';
    case ChangedTo = 'changed_to';
    case ChangedFrom = 'changed_from';

    public function getLabel(): string
    {
        return __('notify-rules::notify-rules.operators.' . $this->name);
    }

    public function needsValue(): bool
    {
        return ! in_array($this, [self::IsEmpty, self::IsNotEmpty, self::Changed], true);
    }

    /**
     * Operators that compare against the previous value only match on updates.
     */
    public function needsChanges(): bool
    {
        return in_array($this, [self::Changed, self::ChangedTo, self::ChangedFrom], true);
    }
}
