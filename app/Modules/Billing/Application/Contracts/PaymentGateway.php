<?php

namespace App\Modules\Billing\Application\Contracts;

use App\Modules\Billing\Application\Data\PaymentData;
use App\Modules\Billing\Application\Data\PaymentGatewayInitiationRequestData;
use App\Modules\Billing\Application\Data\PaymentGatewaySnapshotData;

interface PaymentGateway
{
    public function providerKey(): string;

    public function supportsRefunds(): bool;

    public function initiatePayment(PaymentGatewayInitiationRequestData $request): PaymentGatewaySnapshotData;

    public function fetchPaymentStatus(PaymentData $payment): PaymentGatewaySnapshotData;

    public function capturePayment(PaymentData $payment): PaymentGatewaySnapshotData;

    public function cancelPayment(PaymentData $payment, ?string $reason = null): PaymentGatewaySnapshotData;

    public function refundPayment(PaymentData $payment, ?string $reason = null): PaymentGatewaySnapshotData;

    public function verifyWebhookSignature(string $signature, string $payload): bool;
}
