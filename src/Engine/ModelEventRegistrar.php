<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Engine;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens to Eloquent events of the allowlisted models only (no global "eloquent.*" listener).
 * Each model is registered once, even when several panels allow it.
 */
final class ModelEventRegistrar
{
    public const EVENTS = ['created', 'updated', 'deleted'];

    /** @var array<class-string<Model>, true> */
    private array $registered = [];

    /** @var array<class-string<Model>, string> Model => relationship to its tenant */
    private array $tenantRelationships = [];

    public function __construct(private readonly Dispatcher $events) {}

    /**
     * @param  array<int, class-string<Model>>  $models
     * @param  string|null  $tenantRelationship  The relationship from these models to the panel's tenant.
     */
    public function register(array $models, ?string $tenantRelationship = null): void
    {
        foreach ($models as $model) {
            if ($tenantRelationship !== null) {
                $this->tenantRelationships[$model] ??= $tenantRelationship;
            }

            if (isset($this->registered[$model])) {
                continue;
            }

            $this->registered[$model] = true;

            foreach (self::EVENTS as $event) {
                $this->events->listen(
                    "eloquent.{$event}: {$model}",
                    static function (Model $record) use ($event): void {
                        app(RuleEngine::class)->handle($event, $record);
                    },
                );
            }
        }
    }

    /**
     * @return array<int, class-string<Model>>
     */
    public function registered(): array
    {
        return array_keys($this->registered);
    }

    public function tenantRelationship(string $model): ?string
    {
        return $this->tenantRelationships[$model] ?? null;
    }
}
