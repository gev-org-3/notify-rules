<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
