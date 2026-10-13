<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Engine;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache as CacheFacade;
use OrionSoft\NotifyRules\Models\NotificationRule;

/**
 * Caches the active rules of each model, so that saving a model does not query the rules table.
 * The cache is cleared whenever a rule is saved or deleted.
 */
final class RuleRepository
{
    /**
     * @return Collection<int, NotificationRule>
     */
    public function activeFor(string $modelType): Collection
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->cache()->rememberForever(
            $this->key($modelType),
            static fn (): array => NotificationRule::query()
                ->where('model_type', $modelType)
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->map(static fn (NotificationRule $rule): array => $rule->getAttributes())
                ->all(),
        );

        // Raw attributes are cached instead of models: they survive any cache serializer and package upgrade.
        return NotificationRule::hydrate($rows);
    }

    public function forget(string $modelType): void
    {
        $this->cache()->forget($this->key($modelType));
    }

    private function key(string $modelType): string
    {
        return 'notify-rules:rules:' . sha1($modelType);
    }

    private function cache(): Cache
    {
        return CacheFacade::store(config('notify-rules.cache.store'));
    }
}
