# Changelog

All notable changes to `orion-soft/notify-rules` are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - Unreleased

First release.

### Added

- Notification rules managed in the Filament panel: model and event (created, updated, deleted, field changed), conditions with all/any matching, recipients and a message template.
- Conditions: `=`, `!=`, `>`, `>=`, `<`, `<=`, `contains`, `in`, `is_empty`, `is_not_empty`, `changed`, `changed_to`, `changed_from`.
- Templates with `{{ field }}`, `{{ relation.field }}`, `{{ old.field }}`, `{{ url }}`, `{{ rule.name }}`, `{{ now }}` and the `|money` filter; HTML escaping with `<b>`, `<i>`, `<code>` and safe links allowed; 4,096 character limit.
- Live preview on the latest record and "Send test" to the current user's Telegram.
- One-click Telegram connection from the user menu through `/start` deep links, with webhook (`notify-rules:webhook`) and long polling (`notify-rules:poll`).
- Recipients: users with a connected Telegram, roles (spatie/laravel-permission or a custom `RoleUserResolver`), group and channel chat IDs; `/chatid` bot command.
- Queued sending after commit with retries, back-off, Telegram rate limits and automatic deactivation of blocked chats.
- Read-only delivery log with filters, message view and resending of failed deliveries; `notify-rules:prune`.
- Multi-tenancy through a tenant column and `tenantOwnership()`.
- `NotificationRulePolicy` and `DeliveryPolicy`, replaceable by the app.
- English and Russian translations.
- Support for Laravel 11, 12 and 13 and Filament 5.10+.

[Unreleased]: https://github.com/gev-org-3/notify-rules/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/gev-org-3/notify-rules/releases/tag/v1.0.0
