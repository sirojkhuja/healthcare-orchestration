<?php

namespace App\Modules\Integrations\Infrastructure\Config;

use App\Modules\Integrations\Application\Contracts\IntegrationCatalog;

/**
 * @phpstan-import-type IntegrationCapability from IntegrationCatalog
 * @phpstan-import-type IntegrationCredentialField from IntegrationCatalog
 * @phpstan-import-type IntegrationDefinition from IntegrationCatalog
 * @phpstan-import-type IntegrationWebhookConfig from IntegrationCatalog
 */
final class ConfigIntegrationCatalog implements IntegrationCatalog
{
    #[\Override]
    /**
     * @return list<IntegrationDefinition>
     */
    public function all(): array
    {
        $catalog = config('integrations.catalog', []);

        if (! is_array($catalog)) {
            return [];
        }

        $definitions = [];

        foreach ($catalog as $integrationKey => $definition) {
            if (! is_string($integrationKey) || ! is_array($definition)) {
                continue;
            }

            $definitions[] = $this->normalizeDefinition($integrationKey, $definition);
        }

        usort(
            $definitions,
            static fn (array $left, array $right): int => [$left['category'], $left['integration_key']]
                <=> [$right['category'], $right['integration_key']],
        );

        return $definitions;
    }

    #[\Override]
    /**
     * @return IntegrationDefinition|null
     */
    public function find(string $integrationKey): ?array
    {
        /** @var mixed $definition */
        $definition = config('integrations.catalog.'.$integrationKey);

        return is_array($definition)
            ? $this->normalizeDefinition($integrationKey, $definition)
            : null;
    }

    /**
     * @param  array<array-key, mixed>  $definition
     * @return IntegrationDefinition
     */
    private function normalizeDefinition(string $integrationKey, array $definition): array
    {
        $featureFlag = $this->nullableTrimmedString($definition['feature_flag'] ?? null);
        $name = $this->trimmedStringOrDefault($definition['name'] ?? null, $integrationKey);
        $category = $this->trimmedStringOrDefault($definition['category'] ?? null, 'other');

        return [
            'integration_key' => $integrationKey,
            'name' => $name,
            'category' => $category,
            'default_enabled' => (bool) ($definition['default_enabled'] ?? false),
            'feature_flag' => $featureFlag,
            'available' => $featureFlag === null ? true : (bool) config('integrations.feature_flags.'.$featureFlag, false),
            'supports' => $this->normalizeSupports($definition['supports'] ?? null),
            'credential_fields' => $this->normalizeCredentialFields($definition['credential_fields'] ?? null),
            'webhook' => $this->normalizeWebhook($definition['webhook'] ?? null),
        ];
    }

    /**
     * @return IntegrationCapability
     */
    private function normalizeSupports(mixed $value): array
    {
        $supports = is_array($value) ? $value : [];

        return [
            'credentials' => (bool) ($supports['credentials'] ?? false),
            'health' => (bool) ($supports['health'] ?? false),
            'logs' => (bool) ($supports['logs'] ?? false),
            'webhooks' => (bool) ($supports['webhooks'] ?? false),
            'tokens' => (bool) ($supports['tokens'] ?? false),
            'test_connection' => (bool) ($supports['test_connection'] ?? false),
        ];
    }

    /**
     * @return list<IntegrationCredentialField>
     */
    private function normalizeCredentialFields(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $fields = [];

        foreach ($value as $field) {
            if (! is_array($field)) {
                continue;
            }

            $key = $this->trimmedStringOrDefault($field['key'] ?? null, '');

            if ($key === '') {
                continue;
            }

            $label = $this->trimmedStringOrDefault($field['label'] ?? null, $key);

            $fields[] = [
                'key' => $key,
                'label' => $label,
                'secret' => (bool) ($field['secret'] ?? false),
                'required' => (bool) ($field['required'] ?? false),
            ];
        }

        return $fields;
    }

    /**
     * @return IntegrationWebhookConfig|null
     */
    private function normalizeWebhook(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $path = $this->trimmedStringOrDefault($value['path'] ?? null, '');

        if ($path === '') {
            return null;
        }

        $authMode = $this->trimmedStringOrDefault($value['auth_mode'] ?? null, 'none');

        return [
            'path' => $path,
            'auth_mode' => $authMode,
            'rotate_supported' => (bool) ($value['rotate_supported'] ?? false),
        ];
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function trimmedStringOrDefault(mixed $value, string $default): string
    {
        return $this->nullableTrimmedString($value) ?? $default;
    }
}
