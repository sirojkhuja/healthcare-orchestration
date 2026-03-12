<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Integrations\Application\Contracts\IntegrationLogRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationPluginWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Contracts\MyIdVerificationRepository;
use App\Modules\Integrations\Application\Data\MyIdVerificationData;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class MyIdVerificationService
{
    public function __construct(
        private readonly OptionalIdentityPluginGuard $optionalIdentityPluginGuard,
        private readonly MyIdVerificationRepository $myIdVerificationRepository,
        private readonly IntegrationPluginWebhookDeliveryRepository $integrationPluginWebhookDeliveryRepository,
        private readonly IntegrationLogRepository $integrationLogRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MyIdVerificationData
    {
        $context = $this->optionalIdentityPluginGuard->requireReadyForTenant('myid');
        $externalReference = $this->requiredString($attributes['external_reference'] ?? null, 'external_reference');
        $subject = $this->scalarMap($attributes['subject'] ?? null, 'subject', true);
        $metadata = $this->scalarMap($attributes['metadata'] ?? null, 'metadata');
        $providerReference = 'myid_'.bin2hex(random_bytes(12));
        $now = CarbonImmutable::now();
        $verification = $this->myIdVerificationRepository->create(
            $context->tenantId,
            $context->webhookId,
            $externalReference,
            $providerReference,
            $subject,
            $metadata,
            $now,
        );

        $this->integrationLogRepository->create(
            $context->tenantId,
            'myid',
            'info',
            'myid.verification_requested',
            'MyID verification session created.',
            [
                'verification_id' => $verification->id,
                'provider_reference' => $providerReference,
            ],
            $now,
        );
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'integrations.myid.verification_requested',
            objectType: 'myid_verification',
            objectId: $verification->id,
            after: [
                'status' => $verification->status,
                'external_reference' => $verification->externalReference,
            ],
            metadata: [
                'integration_key' => 'myid',
                'provider_reference' => $providerReference,
            ],
        ));

        return $verification;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, bool>
     */
    public function processWebhook(string $secret, string $rawPayload, array $payload): array
    {
        $webhookId = $this->requiredString($payload['webhook_id'] ?? null, 'MyID webhook payload must include webhook_id.');
        $deliveryId = $this->requiredString($payload['delivery_id'] ?? null, 'MyID webhook payload must include delivery_id.');
        $providerReference = $this->requiredString($payload['provider_reference'] ?? null, 'MyID webhook payload must include provider_reference.');
        $webhook = $this->optionalIdentityPluginGuard->resolveInboundWebhook('myid', $webhookId, $secret);

        if ($this->integrationPluginWebhookDeliveryRepository->findByReplayKey('myid', $webhookId, $deliveryId) !== null) {
            return ['ok' => true];
        }

        $verification = $this->myIdVerificationRepository->findByProviderReference($webhook->tenantId, $providerReference);

        if (! $verification instanceof MyIdVerificationData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $status = $this->terminalStatus($payload['status'] ?? null, ['verified', 'rejected', 'expired', 'failed'], 'MyID webhook status is invalid.');
        $resultPayload = $this->assocPayload($payload['result_payload'] ?? null);
        $metadata = $this->assocPayload($payload['metadata'] ?? null);
        $now = CarbonImmutable::now();
        $outcome = 'ignored';

        if ($verification->status === 'pending') {
            $verification = $this->myIdVerificationRepository->complete(
                $webhook->tenantId,
                $providerReference,
                $status,
                $resultPayload,
                $now,
                $now,
            ) ?? $verification;
            $outcome = 'processed';
            $this->integrationLogRepository->create(
                $webhook->tenantId,
                'myid',
                'info',
                'myid.webhook_processed',
                'MyID webhook processed.',
                [
                    'verification_id' => $verification->id,
                    'provider_reference' => $providerReference,
                    'status' => $verification->status,
                ] + $metadata,
                $now,
            );
            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'integrations.myid.webhook_processed',
                objectType: 'myid_verification',
                objectId: $verification->id,
                after: [
                    'status' => $verification->status,
                    'result_payload' => $verification->resultPayload,
                ],
                metadata: [
                    'integration_key' => 'myid',
                    'provider_reference' => $providerReference,
                    'delivery_id' => $deliveryId,
                ],
            ));
        }

        $this->integrationPluginWebhookDeliveryRepository->create([
            'integration_key' => 'myid',
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
    private function scalarMap(mixed $value, string $field, bool $required = false): array
    {
        if (! is_array($value)) {
            if ($required) {
                throw new UnprocessableEntityHttpException(sprintf('The %s field must be an object.', $field));
            }

            return [];
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (! is_string($key) || ($item !== null && ! is_scalar($item))) {
                throw new UnprocessableEntityHttpException(sprintf('The %s field must contain only scalar values.', $field));
            }

            $normalized[$key] = $item;
        }

        if ($required && $normalized === []) {
            throw new UnprocessableEntityHttpException(sprintf('The %s field must not be empty.', $field));
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
