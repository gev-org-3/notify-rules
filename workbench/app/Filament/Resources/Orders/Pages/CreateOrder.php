<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Orders\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\Orders\OrderResource;

final class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
}
