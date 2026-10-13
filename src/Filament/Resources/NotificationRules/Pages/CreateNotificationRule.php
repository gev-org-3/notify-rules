<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\NotificationRules\Pages;

use Filament\Resources\Pages\CreateRecord;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Actions\SendTestMessageAction;
use Orion\NotifyRules\Filament\Resources\NotificationRules\NotificationRuleResource;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\Concerns\NormalizesRuleData;

final class CreateNotificationRule extends CreateRecord
{
    use NormalizesRuleData;

    protected static string $resource = NotificationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendTestMessageAction::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeRuleData($data);
    }
}
