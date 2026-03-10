<?php

namespace App\Modules\Lab\Presentation\Http\Controllers;

use App\Modules\Lab\Application\Commands\CancelLabOrderCommand;
use App\Modules\Lab\Application\Commands\MarkLabOrderCompleteCommand;
use App\Modules\Lab\Application\Commands\MarkSpecimenCollectedCommand;
use App\Modules\Lab\Application\Commands\MarkSpecimenReceivedCommand;
use App\Modules\Lab\Application\Commands\ReconcileLabOrdersCommand;
use App\Modules\Lab\Application\Commands\SendLabOrderCommand;
use App\Modules\Lab\Application\Handlers\CancelLabOrderCommandHandler;
use App\Modules\Lab\Application\Handlers\MarkLabOrderCompleteCommandHandler;
use App\Modules\Lab\Application\Handlers\MarkSpecimenCollectedCommandHandler;
use App\Modules\Lab\Application\Handlers\MarkSpecimenReceivedCommandHandler;
use App\Modules\Lab\Application\Handlers\ReconcileLabOrdersCommandHandler;
use App\Modules\Lab\Application\Handlers\SendLabOrderCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LabOrderWorkflowController
{
    public function cancel(string $orderId, Request $request, CancelLabOrderCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string} $validated */
        $order = $handler->handle(new CancelLabOrderCommand($orderId, trim($validated['reason'])));

        return response()->json([
            'status' => 'lab_order_canceled',
            'data' => $order->toArray(),
        ]);
    }

    public function complete(string $orderId, MarkLabOrderCompleteCommandHandler $handler): JsonResponse
    {
        $order = $handler->handle(new MarkLabOrderCompleteCommand($orderId));

        return response()->json([
            'status' => 'lab_order_completed',
            'data' => $order->toArray(),
        ]);
    }

    public function markCollected(string $orderId, MarkSpecimenCollectedCommandHandler $handler): JsonResponse
    {
        $order = $handler->handle(new MarkSpecimenCollectedCommand($orderId));

        return response()->json([
            'status' => 'lab_order_specimen_collected',
            'data' => $order->toArray(),
        ]);
    }

    public function markReceived(string $orderId, MarkSpecimenReceivedCommandHandler $handler): JsonResponse
    {
        $order = $handler->handle(new MarkSpecimenReceivedCommand($orderId));

        return response()->json([
            'status' => 'lab_order_specimen_received',
            'data' => $order->toArray(),
        ]);
    }

    public function reconcile(Request $request, ReconcileLabOrdersCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'lab_provider_key' => ['required', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'order_ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'uuid', 'distinct'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        $orderIds = [];

        if (array_key_exists('order_ids', $validated) && is_array($validated['order_ids'])) {
            /** @var list<string> $orderIds */
            $orderIds = array_values(array_filter(
                $validated['order_ids'],
                static fn (mixed $orderId): bool => is_string($orderId),
            ));
        }

        /** @psalm-suppress MixedAssignment */
        $labProviderKeyValue = $validated['lab_provider_key'] ?? null;
        $labProviderKey = is_string($labProviderKeyValue) ? $labProviderKeyValue : '';

        $result = $handler->handle(new ReconcileLabOrdersCommand(
            labProviderKey: $labProviderKey,
            orderIds: $orderIds,
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 50,
        ));

        return response()->json([
            'status' => 'lab_orders_reconciled',
            'data' => $result->toArray(),
        ]);
    }

    public function send(string $orderId, SendLabOrderCommandHandler $handler): JsonResponse
    {
        $order = $handler->handle(new SendLabOrderCommand($orderId));

        return response()->json([
            'status' => 'lab_order_sent',
            'data' => $order->toArray(),
        ]);
    }
}
