<?php

declare(strict_types=1);

namespace OrionSoft\NotifyRules\Filament\Resources\NotificationRules;

use Filament\Facades\Filament;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use OrionSoft\NotifyRules\Engine\ModelFields;
use OrionSoft\NotifyRules\Models\TelegramLink;
use OrionSoft\NotifyRules\NotifyRulesPlugin;

/**
 * Options for the rule form, all limited to the models allowed in the current panel.
 */
final class RuleFormOptions
{
    private const USER_OPTIONS_LIMIT = 50;

    public function __construct(private readonly ModelFields $fields) {}

    /**
     * @return array<class-string<Model>, string>
     */
    public function models(): array
    {
        $plugin = NotifyRulesPlugin::get();
        $options = [];

        foreach ($plugin->getModels() as $model) {
            $options[$model] = $plugin->getModelLabel($model);
        }

        return $options;
    }

    public function modelLabel(?string $modelType): string
    {
        return $modelType === null ? '' : NotifyRulesPlugin::get()->getModelLabel($modelType);
    }

    /**
     * @return array<string, string>
     */
    public function fields(?string $modelType): array
    {
        $model = $this->model($modelType);

        if ($model === null) {
            return [];
        }

        $columns = $this->fields->columns($model);

        return array_combine($columns, $columns);
    }

    /**
     * Placeholders available in templates for the model.
     *
     * @return array<int, string>
     */
    public function variables(?string $modelType): array
    {
        $model = $this->model($modelType);

        if ($model === null) {
            return [];
        }

        $columns = $this->fields->columns($model);

        return [
            ...array_map(static fn (string $column): string => "{{ {$column} }}", $columns),
            ...array_map(static fn (string $relation): string => "{{ {$relation}.… }}", $this->fields->singleRelations($model)),
            '{{ …|money }}',
            '{{ old.… }}',
            '{{ url }}',
            '{{ rule.name }}',
            '{{ now }}',
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public function users(?string $search = null): array
    {
        $query = $this->userQuery();

        if ($query === null) {
            return [];
        }

        $title = $this->userTitleAttribute();

        if (filled($search)) {
            $query->where($title, 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $search) . '%');
        }

        return $this->labelUsers($query->orderBy($title)->limit(self::USER_OPTIONS_LIMIT)->get());
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int|string, string>
     */
    public function userLabels(array $ids): array
    {
        $query = $this->userQuery();

        if ($query === null || $ids === []) {
            return [];
        }

        return $this->labelUsers($query->whereKey($ids)->get());
    }

    /**
     * @param  Collection<int, Model>  $users
     * @return array<int|string, string>
     */
    private function labelUsers(Collection $users): array
    {
        // In a tenant panel only the tenant's users may be notified.
        $tenant = Filament::getTenant();

        if ($tenant !== null) {
            $users = $users->filter(static fn (Model $user): bool => ! $user instanceof HasTenants || $user->canAccessTenant($tenant));
        }

        if ($users->isEmpty()) {
            return [];
        }

        /** @var Model $first */
        $first = $users->first();

        $connected = TelegramLink::query()
            ->where('notifiable_type', $first->getMorphClass())
            ->whereIn('notifiable_id', $users->modelKeys())
            ->where('is_active', true)
            ->whereNotNull('chat_id')
            ->pluck('notifiable_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $title = $this->userTitleAttribute();

        return $users->mapWithKeys(fn (Model $user): array => [
            $user->getKey() => (string) $user->getAttribute($title) . ' — ' . __(in_array((string) $user->getKey(), $connected, true)
                ? 'notify-rules::notify-rules.rules.form.recipients.telegram_connected'
                : 'notify-rules::notify-rules.rules.form.recipients.telegram_not_connected'),
        ])->all();
    }

    /**
     * @return Builder<Model>|null
     */
    private function userQuery(): ?Builder
    {
        $userModel = config('notify-rules.user_model');

        if (! is_string($userModel) || ! is_subclass_of($userModel, Model::class)) {
            return null;
        }

        return $userModel::query();
    }

    private function userTitleAttribute(): string
    {
        return (string) config('notify-rules.user_title_attribute', 'name');
    }

    private function model(?string $modelType): ?Model
    {
        if ($modelType === null || ! NotifyRulesPlugin::get()->hasModel($modelType)) {
            return null;
        }

        return new $modelType;
    }
}
