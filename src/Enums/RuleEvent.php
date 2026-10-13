<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Enums;

use Filament\Support\Contracts\HasLabel;

enum RuleEvent: string implements HasLabel
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
    case FieldChanged = 'field_changed';

    public function getLabel(): string
    {
        return __("notify-rules::notify-rules.events.{$this->value}");
    }

    /**
     * The Eloquent event a rule with this event listens to.
     */
    public function eloquentEvent(): string
    {
        return match ($this) {
            self::Created => 'created',
            self::Updated, self::FieldChanged => 'updated',
            self::Deleted => 'deleted',
        };
    }
}
