<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Filament\Resources\NotificationRules\Schemas;

use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Orion\NotifyRules\Engine\RecipientResolver;
use Orion\NotifyRules\Engine\RuleTester;
use Orion\NotifyRules\Engine\TemplateRenderer;
use Orion\NotifyRules\Enums\ConditionOperator;
use Orion\NotifyRules\Enums\RuleEvent;
use Orion\NotifyRules\Filament\Resources\NotificationRules\RuleFormOptions;
use Orion\NotifyRules\Models\NotificationRule;

final class NotificationRuleForm
{
    private const T = 'notify-rules::notify-rules.rules.form.';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::generalSection(),
                self::triggerSection(),
                self::conditionsSection(),
                self::recipientsSection(),
                self::templateSection(),
            ]);
    }

    private static function generalSection(): Section
    {
        return Section::make(__(self::T . 'general.heading'))
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label(__(self::T . 'general.name'))
                    ->required()
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label(__(self::T . 'general.is_active'))
                    ->default(true)
                    ->inline(false),
            ]);
    }

    private static function triggerSection(): Section
    {
        return Section::make(__(self::T . 'trigger.heading'))
            ->description(__(self::T . 'trigger.description'))
            ->columns(3)
            ->schema([
                Select::make('model_type')
                    ->label(__(self::T . 'trigger.model'))
                    ->options(fn (): array => self::options()->models())
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('watched_field', null);
                        $set('conditions.items', []);
                    }),
                Select::make('event')
                    ->label(__(self::T . 'trigger.event'))
                    ->options(RuleEvent::class)
                    ->required()
                    ->live(),
                Select::make('watched_field')
                    ->label(__(self::T . 'trigger.watched_field'))
                    ->options(fn (Get $get): array => self::options()->fields($get('model_type')))
                    ->visible(fn (Get $get): bool => self::event($get('event')) === RuleEvent::FieldChanged)
                    ->required(fn (Get $get): bool => self::event($get('event')) === RuleEvent::FieldChanged),
            ]);
    }

    private static function conditionsSection(): Section
    {
        return Section::make(__(self::T . 'conditions.heading'))
            ->description(__(self::T . 'conditions.description'))
            ->schema([
                ToggleButtons::make('conditions.match')
                    ->label(__(self::T . 'conditions.match'))
                    ->options([
                        NotificationRule::MATCH_ALL => __(self::T . 'conditions.match_all'),
                        NotificationRule::MATCH_ANY => __(self::T . 'conditions.match_any'),
                    ])
                    ->default(NotificationRule::MATCH_ALL)
                    ->inline()
                    ->grouped(),
                Repeater::make('conditions.items')
                    ->hiddenLabel()
                    ->columns(3)
                    ->defaultItems(0)
                    ->addActionLabel(__(self::T . 'conditions.add'))
                    ->schema([
                        Select::make('field')
                            ->label(__(self::T . 'conditions.field'))
                            ->options(fn (Get $get): array => self::options()->fields($get('../../../model_type')))
                            ->required(),
                        Select::make('operator')
                            ->label(__(self::T . 'conditions.operator'))
                            ->options(ConditionOperator::class)
                            ->required()
                            ->live(),
                        TextInput::make('value')
                            ->label(__(self::T . 'conditions.value'))
                            ->helperText(fn (Get $get): ?string => self::operator($get('operator')) === ConditionOperator::In
                                ? __(self::T . 'conditions.in_help')
                                : null)
                            ->visible(fn (Get $get): bool => self::operator($get('operator'))?->needsValue() ?? true)
                            ->required(fn (Get $get): bool => self::operator($get('operator'))?->needsValue() ?? false),
                    ]),
            ]);
    }

    private static function recipientsSection(): Section
    {
        return Section::make(__(self::T . 'recipients.heading'))
            ->description(__(self::T . 'recipients.description'))
            ->schema([
                Select::make('recipients.users')
                    ->label(__(self::T . 'recipients.users'))
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => self::options()->users())
                    ->getSearchResultsUsing(fn (string $search): array => self::options()->users($search))
                    ->getOptionLabelsUsing(fn (array $values): array => self::options()->userLabels($values))
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            $ids = array_map('strval', (array) $value);
                            $allowed = array_map('strval', array_keys(self::options()->userLabels($ids)));

                            if (array_diff($ids, $allowed) !== []) {
                                $fail(__(self::T . 'recipients.users_invalid'));
                            }
                        },
                    ]),
                TagsInput::make('recipients.roles')
                    ->label(__(self::T . 'recipients.roles'))
                    ->helperText(__(self::T . 'recipients.roles_help')),
                TagsInput::make('recipients.chat_ids')
                    ->label(__(self::T . 'recipients.chat_ids'))
                    ->helperText(__(self::T . 'recipients.chat_ids_help'))
                    ->nestedRecursiveRules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! is_string($value) || ! app(RecipientResolver::class)->isChatId(trim($value))) {
                                $fail(__(self::T . 'recipients.chat_id_invalid', ['value' => is_scalar($value) ? $value : '']));
                            }
                        },
                    ]),
            ]);
    }

    private static function templateSection(): Section
    {
        return Section::make(__(self::T . 'template.heading'))
            ->schema([
                Textarea::make('template')
                    ->label(__(self::T . 'template.template'))
                    ->helperText(__(self::T . 'template.help'))
                    ->required()
                    ->rows(6)
                    ->live(debounce: 500),
                TextEntry::make('variables')
                    ->label(__(self::T . 'template.variables'))
                    ->state(fn (Get $get): array => self::options()->variables($get('model_type')))
                    ->placeholder(__(self::T . 'template.choose_model'))
                    ->badge()
                    ->color('gray')
                    ->fontFamily('mono'),
                TextEntry::make('preview')
                    ->label(__(self::T . 'template.preview'))
                    ->state(fn (Get $get): ?HtmlString => self::preview($get))
                    ->placeholder(__(self::T . 'template.preview_empty'))
                    ->helperText(fn (Get $get): ?string => filled($get('model_type')) && self::options()->fields($get('model_type')) !== []
                        && app(RuleTester::class)->sampleRecord($get('model_type')) === null
                            ? __(self::T . 'template.no_records')
                            : null)
                    ->html(),
            ]);
    }

    /**
     * Builds an unsaved rule from the form state, for the preview and the test message.
     *
     * @param  array<string, mixed>  $state
     */
    public static function ruleFromState(array $state): ?NotificationRule
    {
        $modelType = $state['model_type'] ?? null;
        $template = $state['template'] ?? null;

        if (! is_string($modelType) || ! is_string($template) || trim($template) === ''
            || ! array_key_exists($modelType, self::options()->models())) {
            return null;
        }

        return new NotificationRule([
            'name' => (string) ($state['name'] ?? ''),
            'model_type' => $modelType,
            'template' => $template,
        ]);
    }

    private static function preview(Get $get): ?HtmlString
    {
        $rule = self::ruleFromState([
            'name' => $get('name'),
            'model_type' => $get('model_type'),
            'template' => $get('template'),
        ]);

        if ($rule === null) {
            return null;
        }

        $html = app(RuleTester::class)->preview($rule);

        return $html === '' ? null : app(TemplateRenderer::class)->toPanelHtml($html);
    }

    private static function options(): RuleFormOptions
    {
        return app(RuleFormOptions::class);
    }

    private static function event(mixed $state): ?RuleEvent
    {
        return $state instanceof RuleEvent ? $state : RuleEvent::tryFrom((string) $state);
    }

    private static function operator(mixed $state): ?ConditionOperator
    {
        return $state instanceof ConditionOperator ? $state : ConditionOperator::tryFrom((string) $state);
    }
}
