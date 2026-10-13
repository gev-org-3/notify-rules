<?php

declare(strict_types=1);

use OrionSoft\NotifyRules\Engine\RecipientResolver;
use OrionSoft\NotifyRules\Engine\Roles\RoleUserResolver;
use OrionSoft\NotifyRules\Models\NotificationRule;
use OrionSoft\NotifyRules\Models\TelegramLink;
use OrionSoft\NotifyRules\Telegram\TelegramLinkManager;
use OrionSoft\NotifyRules\Tests\Fixtures\Models\User;

function linkTelegram(User $user, string $chatId): TelegramLink
{
    $links = app(TelegramLinkManager::class);

    return $links->complete($links->createLinkToken($user), $chatId, null);
}

function recipientsOf(array $recipients): array
{
    return app(RecipientResolver::class)->resolve(new NotificationRule(['recipients' => $recipients]));
}

it('resolves the active Telegram links of users', function (): void {
    $anna = User::factory()->create();
    $boris = User::factory()->create();
    $notLinked = User::factory()->create();
    $blocked = User::factory()->create();

    linkTelegram($anna, '111');
    linkTelegram($boris, '222');
    linkTelegram($blocked, '333')->update(['is_active' => false]);

    expect(recipientsOf(['users' => [$anna->id, $boris->id, $notLinked->id, $blocked->id]]))
        ->toEqualCanonicalizing(['111', '222']);
});

it('adds valid chat IDs entered directly', function (): void {
    expect(recipientsOf(['chat_ids' => ['-1001234567890', ' 42 ', '@my_channel', 'not a chat', '', '12ab']]))
        ->toBe(['-1001234567890', '42', '@my_channel']);
});

it('resolves users by role through the adapter', function (): void {
    $manager = User::factory()->create();
    linkTelegram($manager, '555');

    app()->instance(RoleUserResolver::class, new class($manager->id) implements RoleUserResolver
    {
        public function __construct(private int $managerId) {}

        public function userIds(array $roles): array
        {
            return $roles === ['manager'] ? [$this->managerId] : [];
        }
    });

    expect(recipientsOf(['roles' => ['manager']]))->toBe(['555'])
        ->and(recipientsOf(['roles' => ['guest']]))->toBe([]);
});

it('resolves no users by role without spatie/laravel-permission', function (): void {
    expect(recipientsOf(['roles' => ['admin']]))->toBe([]);
});

it('removes duplicates', function (): void {
    $anna = User::factory()->create();
    linkTelegram($anna, '111');

    expect(recipientsOf(['users' => [$anna->id, (string) $anna->id], 'chat_ids' => ['111', '111']]))->toBe(['111']);
});

it('handles empty or malformed recipients', function (): void {
    expect(recipientsOf([]))->toBe([])
        ->and(recipientsOf(['users' => 'oops', 'chat_ids' => [['nested']]]))->toBe([]);
});
