<?php

namespace App\Modules\Notifications\Application\Services;

use App\Modules\Notifications\Application\Contracts\SmsProviderRegistry;
use App\Modules\Notifications\Application\Data\SmsDeliveryAttemptData;
use App\Modules\Notifications\Application\Data\SmsDeliveryRequestData;
use App\Modules\Notifications\Application\Data\SmsDeliveryResultData;
use App\Modules\Notifications\Application\Exceptions\SmsProviderDeliveryException;
use Carbon\CarbonImmutable;
use Throwable;

final class SmsRoutingService
{
    public function __construct(
        private readonly SmsProviderRegistry $smsProviderRegistry,
        private readonly SmsRoutingPolicyService $smsRoutingPolicyService,
    ) {}

    /**
     * @param  list<string>|null  $providerOrder
     */
    public function send(
        SmsDeliveryRequestData $request,
        ?array $providerOrder = null,
        ?int $attemptBudget = null,
    ): SmsDeliveryResultData {
        $providers = $providerOrder ?? $this->smsRoutingPolicyService->providersForTenant(
            $request->tenantId,
            $request->messageType,
        );

        $attempts = [];
        $budget = $attemptBudget ?? count($providers);

        foreach ($providers as $providerKey) {
            if ($budget <= 0) {
                break;
            }

            $budget--;

            try {
                $attempt = $this->smsProviderRegistry->resolve($providerKey)->send($request);
            } catch (Throwable $exception) {
                $attempt = $this->failedAttempt($providerKey, $exception);
            }

            $attempts[] = $attempt;

            if ($attempt->status === 'sent') {
                return new SmsDeliveryResultData(
                    successful: true,
                    attempts: $attempts,
                    providerKey: $attempt->providerKey,
                    providerName: $attempt->providerName,
                    providerMessageId: $attempt->providerMessageId,
                    completedAt: $attempt->occurredAt,
                );
            }
        }

        $lastAttempt = $attempts === [] ? null : $attempts[array_key_last($attempts)];
        $lastErrorCode = $lastAttempt instanceof SmsDeliveryAttemptData ? $lastAttempt->errorCode : 'sms_delivery_failed';
        $lastErrorMessage = $lastAttempt instanceof SmsDeliveryAttemptData
            ? $lastAttempt->errorMessage
            : 'All configured SMS providers failed to deliver the message.';
        $completedAt = $lastAttempt instanceof SmsDeliveryAttemptData ? $lastAttempt->occurredAt : CarbonImmutable::now();

        return new SmsDeliveryResultData(
            successful: false,
            attempts: $attempts,
            providerKey: $lastAttempt?->providerKey,
            providerName: $lastAttempt?->providerName,
            lastErrorCode: $lastErrorCode,
            lastErrorMessage: $lastErrorMessage,
            completedAt: $completedAt,
        );
    }

    private function failedAttempt(string $providerKey, Throwable $exception): SmsDeliveryAttemptData
    {
        $resolvedName = $providerKey;

        foreach ($this->smsProviderRegistry->configuredProviders() as $configuredProvider) {
            if ($configuredProvider['key'] === $providerKey) {
                $resolvedName = $configuredProvider['name'];

                break;
            }
        }

        return new SmsDeliveryAttemptData(
            providerKey: $providerKey,
            providerName: $resolvedName,
            status: 'failed',
            occurredAt: CarbonImmutable::now(),
            errorCode: $exception instanceof SmsProviderDeliveryException
                ? $exception->errorCode()
                : 'sms_provider_error',
            errorMessage: $exception->getMessage(),
        );
    }
}
