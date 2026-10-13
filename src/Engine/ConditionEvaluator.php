<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Engine;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Orion\NotifyRules\Enums\ConditionOperator;
use Orion\NotifyRules\Models\NotificationRule;
use Throwable;
use UnitEnum;

/**
 * Checks a rule's conditions against a model. Called from Eloquent's "created", "updated" and "deleted"
 * events, when getChanges() holds the saved changes and getOriginal() still holds the previous values.
 */
final class ConditionEvaluator
{
    public function __construct(private readonly ModelFields $fields) {}

    public function passes(NotificationRule $rule, Model $model): bool
    {
        $conditions = $rule->conditionItems();

        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            $result = $this->check($condition, $model);

            if ($rule->matchesAll() && ! $result) {
                return false;
            }

            if (! $rule->matchesAll() && $result) {
                return true;
            }
        }

        return $rule->matchesAll();
    }

    /**
     * @param  array{field?: string, operator?: string, value?: mixed}  $condition
     */
    public function check(array $condition, Model $model): bool
    {
        $field = (string) ($condition['field'] ?? '');
        $operator = ConditionOperator::tryFrom((string) ($condition['operator'] ?? ''));
        $expected = $condition['value'] ?? null;

        // A condition that cannot be evaluated never matches: better to stay silent than to spam.
        if ($operator === null || ! $this->fields->isReadable($model, $field)) {
            return false;
        }

        if ($operator->needsChanges() && ! $this->fields->wasChanged($model, $field)) {
            return false;
        }

        $actual = $this->fields->value($model, $field);

        return match ($operator) {
            ConditionOperator::Equals => $this->equals($actual, $expected),
            ConditionOperator::NotEquals => ! $this->equals($actual, $expected),
            ConditionOperator::GreaterThan => $this->compare($actual, $expected) > 0,
            ConditionOperator::GreaterThanOrEqual => $this->compare($actual, $expected) >= 0,
            ConditionOperator::LessThan => $this->compare($actual, $expected, PHP_INT_MAX) < 0,
            ConditionOperator::LessThanOrEqual => $this->compare($actual, $expected, PHP_INT_MAX) <= 0,
            ConditionOperator::Contains => $this->contains($actual, $expected),
            ConditionOperator::In => $this->in($actual, $expected),
            ConditionOperator::IsEmpty => $this->isEmpty($actual),
            ConditionOperator::IsNotEmpty => ! $this->isEmpty($actual),
            ConditionOperator::Changed => true,
            ConditionOperator::ChangedTo => $this->equals($actual, $expected),
            ConditionOperator::ChangedFrom => $this->equals($this->fields->originalValue($model, $field), $expected),
        };
    }

    private function equals(mixed $actual, mixed $expected): bool
    {
        $actual = $this->normalize($actual);
        $expected = $this->normalize($expected);

        if ($actual === null || $expected === null) {
            return $this->isEmpty($actual) && $this->isEmpty($expected);
        }

        if (is_bool($actual) || is_bool($expected)) {
            return $this->toBool($actual) === $this->toBool($expected);
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        if ($actual instanceof DateTimeInterface) {
            $expected = $this->toDate($expected);

            return $expected !== null && $actual->getTimestamp() === $expected->getTimestamp();
        }

        if (is_array($actual) || is_array($expected)) {
            return $actual == $expected;
        }

        return (string) $actual === (string) $expected;
    }

    /**
     * Returns <0, 0 or >0. When the values cannot be compared, returns $incomparable,
     * which is chosen per operator so that the condition fails.
     */
    private function compare(mixed $actual, mixed $expected, int $incomparable = PHP_INT_MIN): int
    {
        $actual = $this->normalize($actual);
        $expected = $this->normalize($expected);

        if ($actual === null || $expected === null || $expected === '' || is_array($actual) || is_array($expected)) {
            return $incomparable;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual <=> (float) $expected;
        }

        if ($actual instanceof DateTimeInterface) {
            $expected = $this->toDate($expected);

            return $expected === null ? $incomparable : $actual->getTimestamp() <=> $expected->getTimestamp();
        }

        if (is_bool($actual) || is_bool($expected)) {
            return $incomparable;
        }

        return strcmp((string) $actual, (string) $expected);
    }

    private function contains(mixed $actual, mixed $expected): bool
    {
        $actual = $this->normalize($actual);
        $expected = $this->normalize($expected);

        if ($this->isEmpty($expected) || is_array($expected)) {
            return false;
        }

        if (is_array($actual)) {
            foreach ($actual as $item) {
                if ($this->equals($item, $expected)) {
                    return true;
                }
            }

            return false;
        }

        if ($actual === null || is_bool($actual)) {
            return false;
        }

        return mb_stripos((string) $actual, (string) $expected) !== false;
    }

    private function in(mixed $actual, mixed $expected): bool
    {
        $options = is_array($expected)
            ? $expected
            : array_map('trim', explode(',', (string) $this->normalize($expected)));

        foreach ($options as $option) {
            if ($option !== '' && $this->equals($actual, $option)) {
                return true;
            }
        }

        return false;
    }

    private function isEmpty(mixed $value): bool
    {
        return blank($this->normalize($value));
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum => $value->name,
            is_string($value) => trim($value),
            default => $value,
        };
    }

    private function toBool(mixed $value): bool
    {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }

    private function toDate(mixed $value): ?Carbon
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
