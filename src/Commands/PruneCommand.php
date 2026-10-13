<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Commands;

use Illuminate\Console\Command;
use Orion\NotifyRules\Enums\DeliveryStatus;
use Orion\NotifyRules\Models\Delivery;

/**
 * Deletes old delivery log entries. Schedule it, e.g. Schedule::command('notify-rules:prune')->daily().
 */
final class PruneCommand extends Command
{
    private const CHUNK = 1000;

    protected $signature = 'notify-rules:prune
        {--days= : Delete deliveries older than this many days (defaults to notify-rules.prune_after_days)}';

    protected $description = 'Delete old entries from the Notify Rules delivery log';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('notify-rules.prune_after_days', 30);

        if (! is_numeric($days) || (int) $days < 1) {
            $this->components->error('--days must be a whole number of at least 1.');

            return self::INVALID;
        }

        $before = now()->subDays((int) $days);
        $deleted = 0;

        // Pending deliveries are kept: their job may still be waiting in the queue.
        $query = Delivery::query()
            ->where('created_at', '<', $before)
            ->where('status', '!=', DeliveryStatus::Pending);

        // Deleting in chunks keeps each query short on large logs.
        do {
            $ids = (clone $query)->orderBy('id')->limit(self::CHUNK)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : Delivery::query()->whereKey($ids)->delete();
        } while ($ids->count() === self::CHUNK);

        $this->components->info("Deleted {$deleted} deliveries older than {$days} days.");

        return self::SUCCESS;
    }
}
