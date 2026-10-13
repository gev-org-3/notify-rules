<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Engine;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The only way rules read data from a model. Hidden and excluded attributes are never exposed,
 * and only stored attributes are read: no accessors with side effects, no arbitrary method calls.
 */
final class ModelFields
{
    private const SINGLE_RELATIONS = [
        BelongsTo::class,
        HasOne::class,
        HasOneThrough::class,
        MorphOne::class,
        MorphTo::class,
    ];

    public function isReadable(Model $model, string $field): bool
    {
        if ($field === '' || in_array($field, $model->getHidden(), true)) {
            return false;
        }

        $visible = $model->getVisible();

        if ($visible !== [] && ! in_array($field, $visible, true)) {
            return false;
        }

        return ! in_array($field, (array) config('notify-rules.excluded_fields', []), true);
    }

    public function has(Model $model, string $field): bool
    {
        return $this->isReadable($model, $field) && array_key_exists($field, $model->getAttributes());
    }

    public function value(Model $model, string $field): mixed
    {
        return $this->has($model, $field) ? $model->getAttribute($field) : null;
    }

    public function originalValue(Model $model, string $field): mixed
    {
        return $this->isReadable($model, $field) ? $model->getOriginal($field) : null;
    }

    public function wasChanged(Model $model, string $field): bool
    {
        return $this->isReadable($model, $field) && $model->wasChanged($field);
    }

    /**
     * Follows "relation.relation.field" through single-record relations (belongsTo, hasOne, morphTo...).
     */
    public function path(Model $model, string $path): mixed
    {
        $segments = explode('.', $path);
        $field = array_pop($segments);

        foreach ($segments as $relation) {
            $related = $this->related($model, $relation);

            if ($related === null) {
                return null;
            }

            $model = $related;
        }

        return $this->value($model, $field);
    }

    /**
     * Columns of the model's table that rules may use.
     *
     * @return array<int, string>
     */
    public function columns(Model $model): array
    {
        $columns = Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());

        return array_values(array_filter($columns, fn (string $column): bool => $this->isReadable($model, $column)));
    }

    /**
     * Single-record relations that templates can follow ("customer" in {{ customer.name }}).
     *
     * @return array<int, string>
     */
    public function singleRelations(Model $model): array
    {
        $relations = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();

            if ($this->isReadable($model, $name) && $this->isSingleRelation($model, $name)) {
                $relations[] = $name;
            }
        }

        sort($relations);

        return $relations;
    }

    private function related(Model $model, string $relation): ?Model
    {
        if (! $this->isReadable($model, $relation) || ! $this->isSingleRelation($model, $relation)) {
            return null;
        }

        $related = $model->getRelationValue($relation);

        return $related instanceof Model ? $related : null;
    }

    /**
     * Decided from the method signature alone: calling the method to see what it returns would run
     * whatever a template names ("delete", "restore"...). Relations must declare their return type.
     */
    private function isSingleRelation(Model $model, string $method): bool
    {
        if (! method_exists($model, $method) || method_exists(Model::class, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($model, $method);
        $type = $reflection->getReturnType();

        if (! $reflection->isPublic()
            || $reflection->isStatic()
            || $reflection->getNumberOfRequiredParameters() > 0
            || ! $type instanceof ReflectionNamedType
            || $type->isBuiltin()) {
            return false;
        }

        foreach (self::SINGLE_RELATIONS as $relation) {
            if (is_a($type->getName(), $relation, true)) {
                return true;
            }
        }

        return false;
    }
}
