<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Engine;

use BackedEnum;
use DateTimeInterface;
use Filament\Facades\Filament;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use JsonSerializable;
use Orion\NotifyRules\Models\NotificationRule;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * Renders rule templates into Telegram HTML (parse_mode=HTML).
 *
 * Templates are written by panel users, so this is deliberately not Blade: placeholders are looked up,
 * never executed. Everything is escaped; only <b>, <i>, <code> and <a href="..."> written in the template
 * itself survive, and only when they are balanced. Values from the model are always escaped.
 */
final class TemplateRenderer
{
    /** Telegram's limit for message text, counted after entity parsing in UTF-16 code units. */
    public const MAX_LENGTH = 4096;

    public const DATE_FORMAT = 'Y-m-d H:i';

    private const PLACEHOLDER = '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*)\s*\}\}/';

    private const TAG = '/<(\/?)(b|i|code|a)(?:\s[^>]*)?>/';

    public function __construct(private readonly ModelFields $fields) {}

    public function render(string $template, Model $model, NotificationRule $rule): string
    {
        $values = [];

        // Placeholders are swapped for markers first, so that their values never pass through the tag filter.
        $marked = (string) preg_replace_callback(
            self::PLACEHOLDER,
            function (array $match) use (&$values, $model, $rule): string {
                $values[] = $this->escape($this->format($this->resolve($match[1], $model, $rule)));

                return "\x00" . (count($values) - 1) . "\x00";
            },
            str_replace("\x00", '', $template),
        );

        $html = (string) preg_replace_callback(
            "/\x00(\\d+)\x00/",
            static fn (array $match): string => $values[(int) $match[1]] ?? '',
            $this->sanitize($marked),
        );

        return $this->truncate(trim($this->unwrapUnsafeLinks($html)));
    }

    /**
     * Escapes the template and restores the allowed tags.
     */
    public function sanitize(string $template): string
    {
        $escaped = $this->escape($template);

        $html = (string) preg_replace(
            [
                '/&lt;(\/?)(b|i|code)&gt;/i',
                '/&lt;a\s+href=(?:&quot;|&#039;)([^<>]*?)(?:&quot;|&#039;)\s*&gt;/i',
                '/&lt;\/a&gt;/i',
            ],
            [
                '<$1$2>',
                '<a href="$1">',
                '</a>',
            ],
            $escaped,
        );

        $html = (string) preg_replace_callback('/<(\/?)(B|I|CODE)>/', static fn (array $m): string => '<' . $m[1] . strtolower($m[2]) . '>', $html);

        return $this->isBalanced($html) ? $html : $escaped;
    }

    /**
     * Makes stored Telegram HTML safe to show in the panel: only exact <b>, <i>, <code> and http(s)/tg
     * links are kept, any other tag is escaped. Line breaks become <br>.
     */
    public function toPanelHtml(string $telegramHtml): HtmlString
    {
        $html = (string) preg_replace_callback(
            '/<[^>]*>?/',
            static fn (array $tag): string => preg_match('/^(<\/?(b|i|code)>|<a href="(https?|tg):\/\/[^"<>]*">|<\/a>)$/', $tag[0]) === 1
                ? $tag[0]
                : htmlspecialchars($tag[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $telegramHtml,
        );

        return new HtmlString(nl2br($html, false));
    }

    public function truncate(string $html, int $limit = self::MAX_LENGTH): string
    {
        $tokens = preg_split('/(<[^>]*>|&#?[A-Za-z0-9]+;)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        $length = 0;

        foreach ($tokens as $token) {
            $length += $this->visibleLength($token);
        }

        if ($length <= $limit) {
            return $html;
        }

        $budget = $limit - 1; // Room for the ellipsis.
        $output = '';
        $open = [];

        foreach ($tokens as $token) {
            if (preg_match(self::TAG, $token, $tag) === 1) {
                $output .= $token;

                if ($tag[1] === '/') {
                    array_pop($open);
                } else {
                    $open[] = $tag[2];
                }

                continue;
            }

            if (str_starts_with($token, '&') && str_ends_with($token, ';')) {
                if ($budget < 1) {
                    break;
                }

                $output .= $token;
                $budget--;

                continue;
            }

            foreach (mb_str_split($token) as $char) {
                $width = $this->utf16Length($char);

                if ($width > $budget) {
                    break 2;
                }

                $output .= $char;
                $budget -= $width;
            }
        }

        $output .= '…';

        foreach (array_reverse($open) as $tag) {
            $output .= "</{$tag}>";
        }

        return $output;
    }

    private function resolve(string $path, Model $model, NotificationRule $rule): mixed
    {
        return match (true) {
            $path === 'url' => $this->url($model),
            $path === 'now' => now(),
            $path === 'rule.name' => $rule->name,
            str_starts_with($path, 'old.') => substr_count($path, '.') === 1
                ? $this->fields->originalValue($model, substr($path, 4))
                : null,
            default => $this->fields->path($model, $path),
        };
    }

    private function format(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => __('notify-rules::notify-rules.template.' . ($value ? 'yes' : 'no')),
            $value instanceof DateTimeInterface => Carbon::instance($value)
                ->setTimezone((string) config('app.timezone', 'UTC'))
                ->format(self::DATE_FORMAT),
            $value instanceof HasLabel && $value instanceof UnitEnum => (string) $value->getLabel(),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Stringable => (string) $value,
            is_array($value), $value instanceof JsonSerializable => (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }

    /**
     * A link to the record in the first panel that uses this plugin and has a resource for the model.
     */
    private function url(Model $model): string
    {
        if (! $model->exists) {
            return '';
        }

        foreach (Filament::getPanels() as $panel) {
            if (! $panel->hasPlugin('notify-rules')) {
                continue;
            }

            $resource = $panel->getModelResource($model);

            if ($resource === null) {
                continue;
            }

            foreach (['view', 'edit'] as $page) {
                if (! $resource::hasPage($page)) {
                    continue;
                }

                try {
                    return $resource::getUrl($page, ['record' => $model], panel: $panel->getId());
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return '';
    }

    /**
     * Keeps the text of links whose URL is not http(s):// or tg:// (javascript:, data:, empty...).
     * The rendered HTML is also shown in the panel as a preview. Links are never nested, so the next
     * closing tag belongs to the link.
     */
    private function unwrapUnsafeLinks(string $html): string
    {
        return (string) preg_replace('/<a href="(?!(?:https?|tg):\/\/)[^"]*">(.*?)<\/a>/is', '$1', $html);
    }

    private function isBalanced(string $html): bool
    {
        preg_match_all(self::TAG, $html, $tags, PREG_SET_ORDER);

        $open = [];

        foreach ($tags as $tag) {
            if ($tag[1] !== '/') {
                // Telegram does not allow links inside links.
                if ($tag[2] === 'a' && in_array('a', $open, true)) {
                    return false;
                }

                $open[] = $tag[2];

                continue;
            }

            if (array_pop($open) !== $tag[2]) {
                return false;
            }
        }

        return $open === [];
    }

    private function visibleLength(string $token): int
    {
        if (str_starts_with($token, '<') && str_ends_with($token, '>')) {
            return 0;
        }

        if (str_starts_with($token, '&') && str_ends_with($token, ';')) {
            return 1;
        }

        return $this->utf16Length($token);
    }

    private function utf16Length(string $text): int
    {
        return intdiv(strlen((string) mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
