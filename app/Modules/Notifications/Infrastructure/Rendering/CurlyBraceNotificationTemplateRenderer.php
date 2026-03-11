<?php

namespace App\Modules\Notifications\Infrastructure\Rendering;

use App\Modules\Notifications\Application\Contracts\NotificationTemplateRenderer;
use App\Modules\Notifications\Application\Data\NotificationTemplateData;
use App\Modules\Notifications\Application\Data\RenderedNotificationTemplateData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CurlyBraceNotificationTemplateRenderer implements NotificationTemplateRenderer
{
    private const PLACEHOLDER_PATTERN = '/{{\s*([A-Za-z0-9_.-]+)\s*}}/';

    #[\Override]
    public function placeholders(?string $subjectTemplate, string $bodyTemplate): array
    {
        $placeholders = [];

        foreach ([$subjectTemplate, $bodyTemplate] as $template) {
            if (! is_string($template) || $template === '') {
                continue;
            }

            preg_match_all(self::PLACEHOLDER_PATTERN, $template, $matches);

            /** @var list<non-empty-string> $matchedPlaceholders */
            $matchedPlaceholders = $matches[1];

            foreach ($matchedPlaceholders as $match) {
                $placeholders[] = $match;
            }
        }

        $unique = array_values(array_unique($placeholders));
        sort($unique);

        return $unique;
    }

    #[\Override]
    public function render(NotificationTemplateData $template, array $variables): RenderedNotificationTemplateData
    {
        return new RenderedNotificationTemplateData(
            templateId: $template->templateId,
            code: $template->code,
            channel: $template->channel,
            currentVersion: $template->currentVersion,
            placeholders: $template->placeholders,
            variables: $variables,
            renderedSubject: $this->renderString($template->subjectTemplate, $variables),
            renderedBody: $this->renderRequiredString($template->bodyTemplate, $variables),
            renderedAt: CarbonImmutable::now(),
        );
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function renderRequiredString(string $template, array $variables): string
    {
        return $this->renderString($template, $variables) ?? '';
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function renderString(?string $template, array $variables): ?string
    {
        if ($template === null) {
            return null;
        }

        $rendered = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            fn (array $matches): string => $this->stringifyResolvedValue(
                $this->resolvedValue($matches[1], $variables),
                $matches[1],
            ),
            $template,
        );

        return is_string($rendered) ? $rendered : $template;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function resolvedValue(string $path, array $variables): mixed
    {
        $cursor = $variables;

        foreach (explode('.', $path) as $segment) {
            /** @psalm-suppress MixedAssignment */
            $cursor = $this->resolvedSegmentValue($cursor, $segment, $path);
        }

        return $cursor;
    }

    private function resolvedSegmentValue(mixed $cursor, string $segment, string $path): mixed
    {
        if (! is_array($cursor) || ! array_key_exists($segment, $cursor)) {
            throw new UnprocessableEntityHttpException(
                'The variables payload is missing a value for placeholder '.$path.'.',
            );
        }

        return $cursor[$segment];
    }

    private function stringifyResolvedValue(mixed $value, string $path): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new UnprocessableEntityHttpException(
            'The placeholder '.$path.' must resolve to a scalar or null value.',
        );
    }
}
