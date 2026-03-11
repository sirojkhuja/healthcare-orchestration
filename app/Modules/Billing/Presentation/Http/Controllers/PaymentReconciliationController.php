<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Modules\Billing\Application\Commands\ReconcilePaymentsCommand;
use App\Modules\Billing\Application\Handlers\GetPaymentReconciliationRunQueryHandler;
use App\Modules\Billing\Application\Handlers\ListPaymentReconciliationRunsQueryHandler;
use App\Modules\Billing\Application\Handlers\ReconcilePaymentsCommandHandler;
use App\Modules\Billing\Application\Queries\GetPaymentReconciliationRunQuery;
use App\Modules\Billing\Application\Queries\ListPaymentReconciliationRunsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentReconciliationController
{
    public function get(string $runId, GetPaymentReconciliationRunQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetPaymentReconciliationRunQuery($runId))->toArray(),
        ]);
    }

    public function list(Request $request, ListPaymentReconciliationRunsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'provider_key' => ['sometimes', 'nullable', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        /** @var mixed $providerKeyValue */
        $providerKeyValue = $validated['provider_key'] ?? null;

        return response()->json([
            'data' => array_map(
                static fn ($run): array => $run->toArray(),
                $handler->handle(new ListPaymentReconciliationRunsQuery(
                    providerKey: is_string($providerKeyValue) && trim($providerKeyValue) !== '' ? trim($providerKeyValue) : null,
                    limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                        ? (int) $validated['limit']
                        : 25,
                )),
            ),
        ]);
    }

    public function reconcile(Request $request, ReconcilePaymentsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'provider_key' => ['required', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'payment_ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'payment_ids.*' => ['required', 'uuid', 'distinct'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        $paymentIds = [];

        if (array_key_exists('payment_ids', $validated) && is_array($validated['payment_ids'])) {
            /** @var list<string> $paymentIds */
            $paymentIds = array_values(array_filter(
                $validated['payment_ids'],
                static fn (mixed $paymentId): bool => is_string($paymentId),
            ));
        }

        /** @psalm-suppress MixedAssignment */
        $providerKeyValue = $validated['provider_key'] ?? null;
        $providerKey = is_string($providerKeyValue) ? trim($providerKeyValue) : '';
        $run = $handler->handle(new ReconcilePaymentsCommand(
            providerKey: $providerKey,
            paymentIds: $paymentIds,
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 50,
        ));

        return response()->json([
            'status' => 'payments_reconciled',
            'data' => $run->toArray(),
        ]);
    }
}
