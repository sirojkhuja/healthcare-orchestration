<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Integrations\Application\Contracts\IntegrationCatalog;
use App\Modules\Integrations\Application\Contracts\IntegrationCredentialRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationStateRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationTokenRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationWebhookRepository;
use App\Modules\Integrations\Application\Data\InboundIntegrationWebhookData;
use App\Modules\Integrations\Application\Data\OptionalIntegrationContextData;
use App\Shared\Application\Contracts\TenantContext;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @phpstan-import-type IntegrationDefinition from IntegrationCatalog
 */
final class OptionalIdentityPluginGuard
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IntegrationCatalog $integrationCatalog,
        private readonly IntegrationStateRepository $integrationStateRepository,
        private readonly IntegrationCredentialRepository $integrationCredentialRepository,
        private readonly IntegrationTokenRepository $integrationTokenRepository,
        private readonly IntegrationWebhookRepository $integrationWebhookRepository,
    ) {}

    public function requireReadyForTenant(string $integrationKey): OptionalIntegrationContextData
    {
        $definition = $this->definitionOrFail($integrationKey);

        if (! $definition['available']) {
            throw new ConflictHttpException('The requested integration is disabled by feature flag.');
        }

        $tenantId = $this->tenantContext->requireTenantId();

        if (! $this->enabled($tenantId, $definition, $integrationKey)) {
            throw new ConflictHttpException('The requested integration is disabled for the current tenant.');
        }

        $credentials = $this->integrationCredentialRepository->get($tenantId, $integrationKey);

        if ($credentials === null || $credentials->configuredFields === []) {
            throw new ConflictHttpException('Managed credentials must be configured before using this integration.');
        }

        if ($definition['supports']['tokens']) {
            $token = $this->integrationTokenRepository->latestActive($tenantId, $integrationKey);

            if ($token !== null && $token->status() === 'expired') {
                throw new ConflictHttpException('The requested integration is failing and must be repaired before use.');
            }
        }

        foreach ($this->integrationWebhookRepository->list($tenantId, $integrationKey) as $webhook) {
            if ($webhook->status === 'active' && $webhook->secretConfigured) {
                return new OptionalIntegrationContextData($tenantId, $integrationKey, $webhook->id);
            }
        }

        throw new ConflictHttpException('An active managed webhook with a configured secret is required for this integration.');
    }

    public function resolveInboundWebhook(string $integrationKey, string $webhookId, string $secret): InboundIntegrationWebhookData
    {
        $webhook = $this->integrationWebhookRepository->findInboundTarget($integrationKey, $webhookId);

        if (! $webhook instanceof InboundIntegrationWebhookData || $webhook->status !== 'active') {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $secretHash = $webhook->secretHash;

        if ($secretHash === null || hash('sha256', trim($secret)) !== $secretHash) {
            throw new UnauthorizedHttpException('', 'The integration webhook secret is invalid.');
        }

        return $webhook;
    }

    /**
     * @return IntegrationDefinition
     */
    private function definitionOrFail(string $integrationKey): array
    {
        $definition = $this->integrationCatalog->find($integrationKey);

        if ($definition === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $definition;
    }

    /**
     * @param  IntegrationDefinition  $definition
     */
    private function enabled(string $tenantId, array $definition, string $integrationKey): bool
    {
        $state = $this->integrationStateRepository->get($tenantId, $integrationKey);

        if ($state !== null) {
            return $state->enabled;
        }

        return $definition['available'] && $definition['default_enabled'];
    }
}
