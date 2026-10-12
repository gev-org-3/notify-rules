<?php

declare(strict_types=1);

namespace Orion\NotifyRules;

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

    public function packageBooted(): void
    {
        // The plugin ships no CSS/JS of its own: UI is built from Filament's Blade components,
        // so nothing is registered with FilamentAsset here.
    }
}
