<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Commands;

use Illuminate\Console\Command;
use Orion\NotifyRules\Telegram\TelegramClient;
use Orion\NotifyRules\Telegram\TelegramException;

final class WebhookCommand extends Command
{
    protected $signature = 'notify-rules:webhook
        {action=info : set, delete or info}
        {--url= : Webhook URL (defaults to the package route on APP_URL)}
        {--drop-pending : Drop updates Telegram has queued (set and delete)}';

    protected $description = 'Manage the Telegram bot webhook';

    public function handle(TelegramClient $telegram): int
    {
        try {
            return match ($this->argument('action')) {
                'set' => $this->set($telegram),
                'delete' => $this->delete($telegram),
                'info' => $this->showInfo($telegram),
                default => $this->invalidAction(),
            };
        } catch (TelegramException $e) {
            $this->components->error("Telegram: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function set(TelegramClient $telegram): int
    {
        $secret = config('notify-rules.telegram.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            $this->components->error('Set NOTIFY_RULES_TELEGRAM_WEBHOOK_SECRET first: the webhook rejects requests without it.');

            return self::FAILURE;
        }

        if (preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret) !== 1) {
            $this->components->error('The webhook secret may only contain A-Z, a-z, 0-9, "_" and "-" (up to 256 characters).');

            return self::FAILURE;
        }

        $url = $this->option('url') ?: route('notify-rules.telegram.webhook');

        if (! str_starts_with($url, 'https://')) {
            $this->components->warn("Telegram only accepts HTTPS webhooks; [{$url}] will be rejected. Use notify-rules:poll for local development.");
        }

        if ($this->option('drop-pending')) {
            $telegram->deleteWebhook(dropPendingUpdates: true);
        }

        $telegram->setWebhook($url, $secret);

        $this->components->info("Webhook set to [{$url}].");

        return self::SUCCESS;
    }

    private function delete(TelegramClient $telegram): int
    {
        $telegram->deleteWebhook((bool) $this->option('drop-pending'));

        $this->components->info('Webhook deleted.');

        return self::SUCCESS;
    }

    private function showInfo(TelegramClient $telegram): int
    {
        $info = $telegram->getWebhookInfo();

        $this->components->twoColumnDetail('URL', ($info['url'] ?? '') ?: '<fg=yellow>not set</>');
        $this->components->twoColumnDetail('Pending updates', (string) ($info['pending_update_count'] ?? 0));

        if (isset($info['last_error_date'])) {
            $this->components->twoColumnDetail('Last error', date('Y-m-d H:i:s', (int) $info['last_error_date']) . ' ' . ($info['last_error_message'] ?? ''));
        }

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->components->error('The action must be one of: set, delete, info.');

        return self::INVALID;
    }
}
