<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\Deliveries\Pages;

use Filament\Resources\Pages\ListRecords;
use Orion\NotifyRules\Filament\Resources\Deliveries\DeliveryResource;

final class ListDeliveries extends ListRecords
{
    protected static string $resource = DeliveryResource::class;
}
