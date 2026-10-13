<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Commands;

use Illuminate\Console\Command;
use OrionSoft\NotifyRules\Telegram\TelegramClient;
use OrionSoft\NotifyRules\Telegram\TelegramException;
use OrionSoft\NotifyRules\Telegram\UpdateHandler;

/**
 * Long polling for local development, where Telegram cannot reach a webhook.
 */
final class PollCommand extends Command
{
    protected $signature = 'notify-rules:poll
        {--once : Process a single batch of updates and exit}
        {--timeout=30 : Long polling timeout in seconds}';

    protected $description = 'Receive Telegram bot updates by long polling (for local development)';

    public function handle(TelegramClient $telegram, UpdateHandler $handler): int
    {
        try {
            if (($telegram->getWebhookInfo()['url'] ?? '') !== '') {
                $this->components->error('A webhook is set, so Telegram will not deliver updates by polling. Run "php artisan notify-rules:webhook delete" first.');

                return self::FAILURE;
            }
        } catch (TelegramException $e) {
            $this->components->error("Telegram: {$e->getMessage()}");

            return self::FAILURE;
        }

        $timeout = max(0, (int) $this->option('timeout'));
        $offset = null;

        if (! $this->option('once')) {
            $this->components->info('Polling Telegram for updates. Press Ctrl+C to stop.');
        }

        do {
            try {
                $updates = $telegram->getUpdates($offset, $timeout);
            } catch (TelegramException $e) {
                $this->components->warn("Telegram: {$e->getMessage()}");

                if ($this->option('once')) {
                    return self::FAILURE;
                }

                sleep(min($e->retryAfter ?? 5, 60));

                continue;
            }

            foreach ($updates as $update) {
                $offset = (int) $update['update_id'] + 1;

                $handler->handle($update);
            }

            if (count($updates) > 0) {
                $this->components->twoColumnDetail('Updates processed', (string) count($updates));
            }
        } while (! $this->option('once'));

        // Confirm the processed updates so Telegram does not deliver them again.
        if ($offset !== null) {
            $telegram->getUpdates($offset, 0);
        }

        return self::SUCCESS;
    }
}
