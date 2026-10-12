<?php

declare(strict_types=1);

namespace Orion\NotifyRules;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Orion\NotifyRules\Jobs\SendTelegramMessage;
use Orion\NotifyRules\Telegram\HttpTelegramClient;
use Orion\NotifyRules\Telegram\TelegramClient;
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
    }

    public function packageBooted(): void
    {
        // The plugin ships no CSS/JS of its own: UI is built from Filament's Blade components,
        // so nothing is registered with FilamentAsset here.

        // Telegram allows ~30 messages per second overall and ~1 per second per chat.
        RateLimiter::for(SendTelegramMessage::RATE_LIMITER, fn (SendTelegramMessage $job): array => [
            Limit::perSecond(25)->by('global'),
            Limit::perSecond(1)->by('chat:' . $job->delivery->chat_id),
        ]);
    }
}
