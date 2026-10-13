<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DeliveryStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __("notify-rules::notify-rules.deliveries.statuses.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
