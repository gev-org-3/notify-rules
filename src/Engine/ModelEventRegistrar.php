<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Engine;

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

    public function __construct(private readonly Dispatcher $events) {}

    /**
     * @param  array<int, class-string<Model>>  $models
     */
    public function register(array $models): void
    {
        foreach ($models as $model) {
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
}
