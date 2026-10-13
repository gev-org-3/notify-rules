<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\NotificationRules\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Actions\SendTestMessageAction;
use Orion\NotifyRules\Filament\Resources\NotificationRules\NotificationRuleResource;
use Orion\NotifyRules\Filament\Resources\NotificationRules\Pages\Concerns\NormalizesRuleData;

final class EditNotificationRule extends EditRecord
{
    use NormalizesRuleData;

    protected static string $resource = NotificationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendTestMessageAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeRuleData($data);
    }
}
