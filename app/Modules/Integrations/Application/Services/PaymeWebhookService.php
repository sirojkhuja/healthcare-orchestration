<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentGatewayRegistry;
use App\Modules\Integrations\Application\Contracts\PaymentWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Data\PaymeJsonRpcResponseData;
use App\Modules\Integrations\Application\Data\PaymentWebhookDeliveryData;
use App\Modules\Integrations\Application\Data\PaymeWebhookVerificationData;
use App\Modules\Integrations\Application\Exceptions\PaymeJsonRpcException;
use Throwable;

final class PaymeWebhookService
{
    private const PROVIDER_KEY = 'payme';

    public function __construct(
        private readonly PaymentGatewayRegistry $paymentGatewayRegistry,
        private readonly PaymentWebhookDeliveryRepository $paymentWebhookDeliveryRepository,
        private readonly PaymePaymentResolver $paymePaymentResolver,
        private readonly PaymeTransactionViewBuilder $paymeTransactionViewBuilder,
        private readonly PaymeWebhookMutationService $paymeWebhookMutationService,
    ) {}

    public function process(string $authorization, string $rawPayload, mixed $payload): PaymeJsonRpcResponseData
    {
        /** @var string|int|null $requestId */
        $requestId = $this->requestId($payload);

        try {
            $request = $this->parseRequest($payload);

            if (! $this->gateway()->verifyWebhookSignature($authorization, $rawPayload)) {
                throw new PaymeJsonRpcException(-32504, 'Insufficient privileges to execute the method.');
            }

            $result = match ($request['method']) {
                'CancelTransaction' => $this->paymeWebhookMutationService->cancelTransaction($request, $authorization),
                'CheckPerformTransaction' => $this->checkPerformTransaction($request),
                'CheckTransaction' => $this->checkTransaction($request),
                'CreateTransaction' => $this->paymeWebhookMutationService->createTransaction($request, $authorization),
                'GetStatement' => $this->getStatement($request),
                'PerformTransaction' => $this->paymeWebhookMutationService->performTransaction($request, $authorization),
                default => throw new PaymeJsonRpcException(-32601, 'Method not found.'),
            };

            return PaymeJsonRpcResponseData::result($request['id'], $result);
        } catch (PaymeJsonRpcException $exception) {
            return PaymeJsonRpcResponseData::error(
                $requestId,
                $exception->paymeCode,
                $exception->getMessage(),
                $exception->errorData,
            );
        } catch (Throwable) {
            return PaymeJsonRpcResponseData::error($requestId, -32400, 'System error.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(string $authorization, string $rawPayload, array $payload): PaymeWebhookVerificationData
    {
        $body = $rawPayload !== '' ? $rawPayload : json_encode($payload, JSON_THROW_ON_ERROR);

        return new PaymeWebhookVerificationData(
            providerKey: self::PROVIDER_KEY,
            valid: $this->gateway()->verifyWebhookSignature($authorization, $body),
        );
    }

    /**
     * @param  array{id: mixed, method: string, params: array<string, mixed>}  $request
     * @return array{allow: bool}
     */
    private function checkPerformTransaction(array $request): array
    {
        $payment = $this->paymePaymentResolver->paymentByAccount($request['params']);
        $this->paymePaymentResolver->assertAmountMatches($payment, $request['params']);

        if ($payment->status !== 'initiated' || $payment->providerPaymentId !== null) {
            throw new PaymeJsonRpcException(-31008, 'The transaction cannot be performed in the current state.');
        }

        return ['allow' => true];
    }

    /**
     * @param  array{id: mixed, method: string, params: array<string, mixed>}  $request
     * @return array<string, mixed>
     */
    private function checkTransaction(array $request): array
    {
        $providerTransactionId = $this->paymePaymentResolver->providerTransactionId($request['params']);
        $payment = $this->paymePaymentResolver->paymentByProviderTransactionId($providerTransactionId);
        $createDelivery = $this->paymentWebhookDeliveryRepository->findByReplayKey(self::PROVIDER_KEY, 'CreateTransaction', $providerTransactionId);

        if (! $createDelivery instanceof PaymentWebhookDeliveryData) {
            throw new PaymeJsonRpcException(-31003, 'The transaction was not found.');
        }

        $cancelDelivery = $this->paymentWebhookDeliveryRepository->findByReplayKey(self::PROVIDER_KEY, 'CancelTransaction', $providerTransactionId);

        return $this->paymeTransactionViewBuilder->buildCheckResult($payment, $createDelivery, $cancelDelivery);
    }

    /**
     * @param  array{id: mixed, method: string, params: array<string, mixed>}  $request
     * @return array{transactions: list<array<string, mixed>>}
     */
    private function getStatement(array $request): array
    {
        $from = $this->paymePaymentResolver->requiredInt($request['params']['from'] ?? null, 'from');
        $to = $this->paymePaymentResolver->requiredInt($request['params']['to'] ?? null, 'to');

        if ($from > $to) {
            throw new PaymeJsonRpcException(-32602, 'The from field must be less than or equal to the to field.');
        }

        $transactions = [];

        foreach ($this->paymentWebhookDeliveryRepository->listByProviderMethodAndTimeRange(self::PROVIDER_KEY, 'CreateTransaction', $from, $to) as $delivery) {
            if ($delivery->paymentId === null) {
                continue;
            }

            $payment = $this->paymePaymentResolver->paymentByAccount([
                'account' => ['payment_id' => $delivery->paymentId],
            ]);
            $cancelDelivery = $delivery->providerTransactionId !== null
                ? $this->paymentWebhookDeliveryRepository->findByReplayKey(self::PROVIDER_KEY, 'CancelTransaction', $delivery->providerTransactionId)
                : null;

            $transactions[] = $this->paymeTransactionViewBuilder->buildStatementTransaction(
                $payment,
                $delivery,
                $cancelDelivery,
            );
        }

        return ['transactions' => $transactions];
    }

    /**
     * @return array{id: mixed, method: string, params: array<string, mixed>}
     */
    private function parseRequest(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new PaymeJsonRpcException(-32600, 'Invalid JSON-RPC request.');
        }

        $method = $payload['method'] ?? null;
        $params = $payload['params'] ?? null;

        if (! is_string($method) || ! is_array($params)) {
            throw new PaymeJsonRpcException(-32600, 'Invalid JSON-RPC request.');
        }

        /** @var array<string, mixed> $normalizedParams */
        $normalizedParams = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($params as $key => $value) {
            if (is_string($key)) {
                $normalizedParams[$key] = $value;
            }
        }

        return [
            'id' => $payload['id'] ?? null,
            'method' => $method,
            'params' => $normalizedParams,
        ];
    }

    private function gateway(): \App\Modules\Billing\Application\Contracts\PaymentGateway
    {
        return $this->paymentGatewayRegistry->resolve(self::PROVIDER_KEY);
    }

    private function requestId(mixed $payload): mixed
    {
        return is_array($payload) ? ($payload['id'] ?? null) : null;
    }
}
