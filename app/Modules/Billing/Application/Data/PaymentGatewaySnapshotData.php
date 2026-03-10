<?php

namespace App\Modules\Billing\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PaymentGatewaySnapshotData
{
    /**
     * @param  array<string, mixed>|null  $rawPayload
     */
    public function __construct(
        public string $status,
        public ?string $providerPaymentId = null,
        public ?string $providerStatus = null,
        public ?string $checkoutUrl = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public ?string $reason = null,
        public ?CarbonImmutable $occurredAt = null,
        public ?array $rawPayload = null,
    ) {}
}
