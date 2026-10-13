<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules;

use Filament\Facades\Filament;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use OrionSoft\NotifyRules\Commands\PollCommand;
use OrionSoft\NotifyRules\Commands\PruneCommand;
use OrionSoft\NotifyRules\Commands\WebhookCommand;
use OrionSoft\NotifyRules\Engine\ModelEventRegistrar;
use OrionSoft\NotifyRules\Engine\Roles\RoleUserResolver;
use OrionSoft\NotifyRules\Engine\Roles\SpatieRoleUserResolver;
use OrionSoft\NotifyRules\Jobs\SendTelegramMessage;
use OrionSoft\NotifyRules\Models\Delivery;
use OrionSoft\NotifyRules\Models\NotificationRule;
use OrionSoft\NotifyRules\Policies\DeliveryPolicy;
use OrionSoft\NotifyRules\Policies\NotificationRulePolicy;
use OrionSoft\NotifyRules\Telegram\HttpTelegramClient;
use OrionSoft\NotifyRules\Telegram\TelegramClient;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class NotifyRulesServiceProvider extends PackageServiceProvider
{
    public static string $name = 'notify-rules';

    public function configurePackage(Package $package): void
    {
        $package->name(self::$name)
            ->hasConfigFile()
            ->hasMigrations(['create_notify_rules_tables'])
            ->hasTranslations()
            ->hasViews(self::$name)
            ->hasCommands([
                WebhookCommand::class,
                PollCommand::class,
                PruneCommand::class,
            ])
            ->hasInstallCommand(function (InstallCommand $command): void {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageRegistered(): void
    {
        $this->app->bind(TelegramClient::class, fn (): TelegramClient => new HttpTelegramClient(
            config('notify-rules.telegram.bot_token'),
            config('notify-rules.telegram.api_url', 'https://api.telegram.org'),
        ));

        $this->app->singleton(ModelEventRegistrar::class);
        $this->app->bindIf(RoleUserResolver::class, SpatieRoleUserResolver::class);
    }

    public function packageBooted(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/notify-rules.php');

        // The plugin ships no CSS/JS of its own: UI is built from Filament's Blade components,
        // so nothing is registered with FilamentAsset here.

        // Telegram allows ~30 messages per second overall and ~1 per second per chat.
        RateLimiter::for(SendTelegramMessage::RATE_LIMITER, fn (SendTelegramMessage $job): array => [
            Limit::perSecond(25)->by('global'),
            Limit::perSecond(1)->by('chat:' . $job->delivery->chat_id),
        ]);

        // An app policy registered with Gate::policy() replaces these, whichever provider runs first.
        foreach ([NotificationRule::class => NotificationRulePolicy::class, Delivery::class => DeliveryPolicy::class] as $model => $policy) {
            if (! array_key_exists($model, Gate::policies())) {
                Gate::policy($model, $policy);
            }
        }

        // Panels are configured in their providers, so the allowlists are only known once the app has booted.
        // Resolving the panels here (not in the plugin's boot()) makes rules work in queue workers and CLI too.
        $this->app->booted(function (): void {
            $registrar = $this->app->make(ModelEventRegistrar::class);

            foreach (Filament::getPanels() as $panel) {
                if ($panel->hasPlugin('notify-rules')) {
                    /** @var NotifyRulesPlugin $plugin */
                    $plugin = $panel->getPlugin('notify-rules');
                    $registrar->register($plugin->getModels(), $panel->hasTenancy() ? $plugin->getTenantOwnershipRelationship() : null);
                }
            }
        });
    }
}
