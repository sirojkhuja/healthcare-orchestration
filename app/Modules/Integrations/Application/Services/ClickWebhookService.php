<?php

namespace App\Modules\Integrations\Application\Services;

use App\Modules\Billing\Application\Contracts\PaymentGatewayRegistry;
use App\Modules\Billing\Infrastructure\Integrations\ClickPaymentGateway;
use App\Modules\Integrations\Application\Data\ClickWebhookResponseData;
use App\Modules\Integrations\Application\Data\ClickWebhookVerificationData;
use App\Modules\Integrations\Application\Exceptions\ClickWebhookException;
use Throwable;

final class ClickWebhookService
{
    public function __construct(
        private readonly PaymentGatewayRegistry $paymentGatewayRegistry,
        private readonly ClickPaymentResolver $clickPaymentResolver,
        private readonly ClickWebhookMutationService $clickWebhookMutationService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(string $rawPayload, array $payload): ClickWebhookResponseData
    {
        try {
            $request = $this->parseRequest($payload);
            $signature = $request['sign_string'];

            if (! $this->gateway()->verifyWebhookSignature($signature, $rawPayload !== '' ? $rawPayload : json_encode($request, JSON_THROW_ON_ERROR))) {
                throw new ClickWebhookException(-1, 'SIGN CHECK FAILED!');
            }

            if ($request['service_id'] !== $this->gateway()->configuredServiceId()) {
                throw new ClickWebhookException(-8, 'Error in request from click');
            }

            return new ClickWebhookResponseData(
                $request['action'] === 0
                    ? $this->clickWebhookMutationService->prepare($request)
                    : $this->clickWebhookMutationService->complete($request),
            );
        } catch (ClickWebhookException $exception) {
            $response = $this->errorBody($payload, $exception->clickCode, $exception->getMessage());
            $this->clickWebhookMutationService->storeFailure($this->methodName($payload), $payload, $response);

            return new ClickWebhookResponseData($response);
        } catch (Throwable) {
            $response = $this->errorBody($payload, -7, 'Failed to update user');
            $this->clickWebhookMutationService->storeFailure($this->methodName($payload), $payload, $response);

            return new ClickWebhookResponseData($response);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(string $rawPayload, array $payload): ClickWebhookVerificationData
    {
        try {
            $request = $this->parseRequest($payload);
            $signature = $request['sign_string'];
            $valid = $this->gateway()->configuredServiceId() === $request['service_id']
                && $this->gateway()->verifyWebhookSignature(
                    $signature,
                    $rawPayload !== '' ? $rawPayload : json_encode($request, JSON_THROW_ON_ERROR),
                );

            return new ClickWebhookVerificationData(
                providerKey: 'click',
                valid: $valid,
                action: $request['action'],
                merchantTransactionId: $request['merchant_trans_id'],
            );
        } catch (Throwable) {
            $action = is_numeric($payload['action'] ?? null) ? (int) $payload['action'] : null;
            $merchantTransactionId = isset($payload['merchant_trans_id']) && is_scalar($payload['merchant_trans_id'])
                ? (string) $payload['merchant_trans_id']
                : null;

            return new ClickWebhookVerificationData(
                providerKey: 'click',
                valid: false,
                action: $action,
                merchantTransactionId: $merchantTransactionId,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function errorBody(array $payload, int $error, string $errorNote): array
    {
        $response = [
            'error' => $error,
            'error_note' => $errorNote,
        ];

        foreach (['click_trans_id', 'merchant_trans_id'] as $field) {
            /** @psalm-suppress MixedAssignment */
            $value = $payload[$field] ?? null;

            if (is_scalar($value)) {
                $response[$field] = (string) $value;
            }
        }

        return $response;
    }

    private function gateway(): ClickPaymentGateway
    {
        $gateway = $this->paymentGatewayRegistry->resolve('click');

        if (! $gateway instanceof ClickPaymentGateway) {
            throw new \LogicException('The configured Click payment gateway is invalid.');
        }

        return $gateway;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function methodName(array $payload): string
    {
        return is_numeric($payload['action'] ?? null) && (int) $payload['action'] === 1
            ? 'complete'
            : 'prepare';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   action: int,
     *   click_trans_id: string,
     *   service_id: string,
     *   click_paydoc_id: string,
     *   merchant_trans_id: string,
     *   amount: string,
     *   error: string,
     *   error_note: string,
     *   sign_time: string,
     *   sign_string: string,
     *   merchant_prepare_id?: string
     * }
     */
    private function parseRequest(array $payload): array
    {
        $action = $this->clickPaymentResolver->requestedAction($payload);
        $fields = [
            'click_trans_id',
            'service_id',
            'click_paydoc_id',
            'merchant_trans_id',
            'amount',
            'error',
            'error_note',
            'sign_time',
            'sign_string',
        ];

        if ($action === 1) {
            $fields[] = 'merchant_prepare_id';
        }

        $request = ['action' => $action];

        foreach ($fields as $field) {
            $request[$field] = $this->clickPaymentResolver->requiredString($payload, $field);
        }

        /** @var array{
         *   action: int,
         *   click_trans_id: string,
         *   service_id: string,
         *   click_paydoc_id: string,
         *   merchant_trans_id: string,
         *   amount: string,
         *   error: string,
         *   error_note: string,
         *   sign_time: string,
         *   sign_string: string,
         *   merchant_prepare_id?: string
         * } $request
         */
        return $request;
    }
}
