<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Modules\Billing\Application\Commands\CancelPaymentCommand;
use App\Modules\Billing\Application\Commands\CapturePaymentCommand;
use App\Modules\Billing\Application\Commands\RefundPaymentCommand;
use App\Modules\Billing\Application\Handlers\CancelPaymentCommandHandler;
use App\Modules\Billing\Application\Handlers\CapturePaymentCommandHandler;
use App\Modules\Billing\Application\Handlers\RefundPaymentCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentWorkflowController
{
    public function cancel(string $paymentId, Request $request, CancelPaymentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $payment = $handler->handle(new CancelPaymentCommand(
            paymentId: $paymentId,
            reason: $this->stringValue($validated, 'reason'),
        ));

        return response()->json([
            'status' => 'payment_canceled',
            'data' => $payment->toArray(),
        ]);
    }

    public function capture(string $paymentId, CapturePaymentCommandHandler $handler): JsonResponse
    {
        $payment = $handler->handle(new CapturePaymentCommand($paymentId));

        return response()->json([
            'status' => 'payment_captured',
            'data' => $payment->toArray(),
        ]);
    }

    public function refund(string $paymentId, Request $request, RefundPaymentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $payment = $handler->handle(new RefundPaymentCommand(
            paymentId: $paymentId,
            reason: $this->stringValue($validated, 'reason'),
        ));

        return response()->json([
            'status' => 'payment_refunded',
            'data' => $payment->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && trim($validated[$key]) !== ''
            ? trim($validated[$key])
            : null;
    }
}
