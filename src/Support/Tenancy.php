<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Rules and deliveries store the tenant key in the column set in notify-rules.tenancy.column.
 * A rule without a tenant is global: it was created in a panel without tenancy.
 */
final class Tenancy
{
    public function column(): ?string
    {
        $column = config('notify-rules.tenancy.column');

        return is_string($column) && $column !== '' ? $column : null;
    }

    public function enabled(): bool
    {
        return $this->column() !== null;
    }

    /**
     * The key of the panel's current tenant, or null outside a tenant panel.
     */
    public function currentKey(): int | string | null
    {
        if (! $this->enabled()) {
            return null;
        }

        $tenant = Filament::getTenant();

        return $tenant?->getKey();
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeToCurrent(Builder $query): Builder
    {
        $key = $this->currentKey();

        if ($key === null) {
            return $query;
        }

        return $query->where($query->qualifyColumn((string) $this->column()), $key);
    }

    public function belongsToCurrent(Model $record): bool
    {
        $key = $this->currentKey();

        return $key === null || (string) $record->getAttribute((string) $this->column()) === (string) $key;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function withCurrentKey(array $data): array
    {
        $key = $this->currentKey();

        if ($key !== null) {
            $data[(string) $this->column()] = $key;
        }

        return $data;
    }

    public function keyOf(Model $rule): int | string | null
    {
        $column = $this->column();

        if ($column === null) {
            return null;
        }

        $key = $rule->getAttribute($column);

        return is_int($key) || is_string($key) ? $key : null;
    }

    /**
     * The tenant a record belongs to, through the relationship named in the plugin's tenantOwnership().
     */
    public function keyOfRecord(Model $record, string $relationship): int | string | null
    {
        if (! method_exists($record, $relationship)) {
            return null;
        }

        $relation = $record->{$relationship}();

        if (! $relation instanceof Relation) {
            return null;
        }

        if ($relation instanceof BelongsTo) {
            $key = $record->getAttribute($relation->getForeignKeyName());

            return is_int($key) || is_string($key) ? $key : null;
        }

        $tenant = $record->getRelationValue($relationship);

        return $tenant instanceof Model ? $tenant->getKey() : null;
    }
}
