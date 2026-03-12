<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Integrations\Application\Contracts\EImzoSignRequestRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationLogRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationPluginWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Data\EImzoSignRequestData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class EImzoSigningService
{
    public function __construct(
        private readonly OptionalIdentityPluginGuard $optionalIdentityPluginGuard,
        private readonly EImzoSignRequestRepository $eImzoSignRequestRepository,
        private readonly IntegrationPluginWebhookDeliveryRepository $integrationPluginWebhookDeliveryRepository,
        private readonly IntegrationLogRepository $integrationLogRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): EImzoSignRequestData
    {
        $context = $this->optionalIdentityPluginGuard->requireReadyForTenant('eimzo');
        $externalReference = $this->requiredString($attributes['external_reference'] ?? null, 'external_reference');
        $documentHash = $this->requiredString($attributes['document_hash'] ?? null, 'document_hash');
        $documentName = $this->requiredString($attributes['document_name'] ?? null, 'document_name');
        $signer = $this->scalarMap($attributes['signer'] ?? null, 'signer');
        $metadata = $this->scalarMap($attributes['metadata'] ?? null, 'metadata');
        $providerReference = 'eimzo_'.bin2hex(random_bytes(12));
        $now = CarbonImmutable::now();
        $signRequest = $this->eImzoSignRequestRepository->create(
            $context->tenantId,
            $context->webhookId,
            $externalReference,
            $providerReference,
            $documentHash,
            $documentName,
            $signer,
            $metadata,
            $now,
        );

        $this->integrationLogRepository->create(
            $context->tenantId,
            'eimzo',
            'info',
            'eimzo.sign_requested',
            'E-IMZO sign request created.',
            [
                'sign_request_id' => $signRequest->id,
                'provider_reference' => $providerReference,
            ],
            $now,
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.eimzo.sign_requested',
            objectType: 'eimzo_sign_request',
            objectId: $signRequest->id,
            after: [
                'status' => $signRequest->status,
                'external_reference' => $signRequest->externalReference,
            ],
            metadata: [
                'integration_key' => 'eimzo',
                'provider_reference' => $providerReference,
            ],
        ));

        return $signRequest;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, bool>
     */
    public function processWebhook(string $secret, string $rawPayload, array $payload): array
    {
        $webhookId = $this->requiredString($payload['webhook_id'] ?? null, 'E-IMZO webhook payload must include webhook_id.');
        $deliveryId = $this->requiredString($payload['delivery_id'] ?? null, 'E-IMZO webhook payload must include delivery_id.');
        $providerReference = $this->requiredString($payload['provider_reference'] ?? null, 'E-IMZO webhook payload must include provider_reference.');
        $webhook = $this->optionalIdentityPluginGuard->resolveInboundWebhook('eimzo', $webhookId, $secret);

        if ($this->integrationPluginWebhookDeliveryRepository->findByReplayKey('eimzo', $webhookId, $deliveryId) !== null) {
            return ['ok' => true];
        }

        $signRequest = $this->eImzoSignRequestRepository->findByProviderReference($webhook->tenantId, $providerReference);

        if (! $signRequest instanceof EImzoSignRequestData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $status = $this->terminalStatus($payload['status'] ?? null, ['signed', 'canceled', 'expired', 'failed'], 'E-IMZO webhook status is invalid.');
        $signaturePayload = $this->assocPayload($payload['signature_payload'] ?? null);
        $metadata = $this->assocPayload($payload['metadata'] ?? null);
        $now = CarbonImmutable::now();
        $outcome = 'ignored';

        if ($signRequest->status === 'pending') {
            $signRequest = $this->eImzoSignRequestRepository->complete(
                $webhook->tenantId,
                $providerReference,
                $status,
                $signaturePayload,
                $now,
                $now,
            ) ?? $signRequest;
            $outcome = 'processed';
            $this->integrationLogRepository->create(
                $webhook->tenantId,
                'eimzo',
                'info',
                'eimzo.webhook_processed',
                'E-IMZO webhook processed.',
                [
                    'sign_request_id' => $signRequest->id,
                    'provider_reference' => $providerReference,
                    'status' => $signRequest->status,
                ] + $metadata,
                $now,
            );
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'integrations.eimzo.webhook_processed',
                objectType: 'eimzo_sign_request',
                objectId: $signRequest->id,
                after: [
                    'status' => $signRequest->status,
                    'signature_payload' => $signRequest->signaturePayload,
                ],
                metadata: [
                    'integration_key' => 'eimzo',
                    'provider_reference' => $providerReference,
                    'delivery_id' => $deliveryId,
                ],
            ));
        }

        $this->integrationPluginWebhookDeliveryRepository->create([
            'integration_key' => 'eimzo',
            'webhook_id' => $webhookId,
            'resolved_tenant_id' => $webhook->tenantId,
            'delivery_id' => $deliveryId,
            'provider_reference' => $providerReference,
            'event_type' => $status,
            'payload_hash' => hash('sha256', $rawPayload !== '' ? $rawPayload : json_encode($payload, JSON_THROW_ON_ERROR)),
            'secret_hash' => hash('sha256', trim($secret)),
            'outcome' => $outcome,
            'error_code' => null,
            'error_message' => null,
            'processed_at' => $now,
            'payload' => $payload,
            'response' => ['ok' => true],
        ]);

        return ['ok' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function assocPayload(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalized[$key] = $item;
            }
        }

        return $normalized;
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $field));
        }

        return trim($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function scalarMap(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || ($item !== null && ! is_scalar($item))) {
                throw new UnprocessableEntityHttpException(sprintf('The %s field must contain only scalar values.', $field));
            }

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function terminalStatus(mixed $value, array $allowed, string $message): string
    {
        if (! is_string($value) || ! in_array(trim($value), $allowed, true)) {
            throw new UnprocessableEntityHttpException($message);
        }

        return trim($value);
    }
}
