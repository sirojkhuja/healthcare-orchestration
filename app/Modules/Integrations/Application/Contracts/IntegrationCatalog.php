<?php

namespace App\Modules\Integrations\Application\Contracts;

/**
 * @phpstan-type IntegrationCapability array{
 *   credentials: bool,
 *   health: bool,
 *   logs: bool,
 *   webhooks: bool,
 *   tokens: bool,
 *   test_connection: bool
 * }
 * @phpstan-type IntegrationCredentialField array{
 *   key: string,
 *   label: string,
 *   secret: bool,
 *   required: bool
 * }
 * @phpstan-type IntegrationWebhookConfig array{
 *   path: string,
 *   auth_mode: string,
 *   rotate_supported: bool
 * }
 * @phpstan-type IntegrationDefinition array{
 *   integration_key: string,
 *   name: string,
 *   category: string,
 *   default_enabled: bool,
 *   feature_flag: string|null,
 *   available: bool,
 *   supports: IntegrationCapability,
 *   credential_fields: list<IntegrationCredentialField>,
 *   webhook: IntegrationWebhookConfig|null
 * }
 */
interface IntegrationCatalog
{
    /**
     * @return list<IntegrationDefinition>
     */
    public function all(): array;

    /**
     * @return IntegrationDefinition|null
     */
    public function find(string $integrationKey): ?array;
}
