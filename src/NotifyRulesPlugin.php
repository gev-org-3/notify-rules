<?php

declare(strict_types=1);

namespace Orion\NotifyRules;

use Closure;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Orion\NotifyRules\Filament\Pages\TelegramConnection;

final class NotifyRulesPlugin implements Plugin
{
    /** @var array<int, class-string<Model>> */
    private array $models = [];

    private ?string $navigationGroup = null;

    private bool $hasDeliveryLog = false;

    private bool $hasConnectTelegramInUserMenu = false;

    private ?Closure $canManageRules = null;

    private ?string $tenantOwnershipRelationship = null;

    public function getId(): string
    {
        return 'notify-rules';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            TelegramConnection::class,
        ]);

        if ($this->hasConnectTelegramInUserMenu()) {
            $panel->userMenuItems([
                Action::make('notify-rules-telegram')
                    ->label(fn (): string => __('notify-rules::notify-rules.telegram.user_menu'))
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->url(fn (): string => TelegramConnection::getUrl()),
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    /**
     * @param  array<int, class-string<Model>>  $models
     */
    public function models(array $models): static
    {
        foreach ($models as $model) {
            if (! is_subclass_of($model, Model::class)) {
                throw new InvalidArgumentException("[{$model}] is not an Eloquent model.");
            }
        }

        $this->models = array_values(array_unique($models));

        return $this;
    }

    /**
     * @return array<int, class-string<Model>>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    public function navigationGroup(?string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return $this->navigationGroup;
    }

    public function deliveryLog(bool $condition = true): static
    {
        $this->hasDeliveryLog = $condition;

        return $this;
    }

    public function hasDeliveryLog(): bool
    {
        return $this->hasDeliveryLog;
    }

    public function connectTelegramInUserMenu(bool $condition = true): static
    {
        $this->hasConnectTelegramInUserMenu = $condition;

        return $this;
    }

    public function hasConnectTelegramInUserMenu(): bool
    {
        return $this->hasConnectTelegramInUserMenu;
    }

    public function canManageRules(?Closure $callback): static
    {
        $this->canManageRules = $callback;

        return $this;
    }

    public function userCanManageRules(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->canManageRules === null) {
            return true;
        }

        return (bool) app()->call($this->canManageRules, ['user' => $user]);
    }

    public function tenantOwnership(?string $relationship): static
    {
        $this->tenantOwnershipRelationship = $relationship;

        return $this;
    }

    public function getTenantOwnershipRelationship(): ?string
    {
        return $this->tenantOwnershipRelationship;
    }
}
