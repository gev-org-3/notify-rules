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
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Orion\NotifyRules\Filament\Pages\TelegramConnection;
use Orion\NotifyRules\Filament\Resources\Deliveries\DeliveryResource;
use Orion\NotifyRules\Filament\Resources\NotificationRules\NotificationRuleResource;
use Orion\NotifyRules\Support\Tenancy;

final class NotifyRulesPlugin implements Plugin
{
    /** @var array<class-string<Model>, string|null> Model class => custom label */
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

        $panel->resources(array_values(array_filter([
            NotificationRuleResource::class,
            $this->hasDeliveryLog() ? DeliveryResource::class : null,
        ])));

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
        if (! $panel->hasTenancy()) {
            return;
        }

        // Without these, rules of one tenant could be shown or sent to another.
        if (! app(Tenancy::class)->enabled()) {
            throw new LogicException("The [{$panel->getId()}] panel uses tenancy: set notify-rules.tenancy.column before running the Notify Rules migration.");
        }

        if ($this->tenantOwnershipRelationship === null) {
            throw new LogicException("The [{$panel->getId()}] panel uses tenancy: call ->tenantOwnership('relationship') on NotifyRulesPlugin with the relationship from your models to the tenant.");
        }
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
     * Models rules can be created for, optionally with a label: [Order::class, Lead::class => 'Leads'].
     *
     * @param  array<int|class-string<Model>, class-string<Model>|string>  $models
     */
    public function models(array $models): static
    {
        $this->models = [];

        foreach ($models as $key => $value) {
            [$model, $label] = is_int($key) ? [$value, null] : [$key, $value];

            if (! is_subclass_of($model, Model::class)) {
                throw new InvalidArgumentException("[{$model}] is not an Eloquent model.");
            }

            // Rules on the package's own models would trigger themselves.
            if (str_starts_with($model, 'Orion\\NotifyRules\\Models\\')) {
                throw new InvalidArgumentException("[{$model}] belongs to Notify Rules and cannot have rules.");
            }

            $this->models[$model] = $label;
        }

        return $this;
    }

    /**
     * @return array<int, class-string<Model>>
     */
    public function getModels(): array
    {
        return array_keys($this->models);
    }

    public function hasModel(string $model): bool
    {
        return array_key_exists($model, $this->models);
    }

    public function getModelLabel(string $model): string
    {
        return $this->models[$model] ?? Str::headline(class_basename($model));
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
