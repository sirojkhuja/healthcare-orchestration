<?php

namespace App\Modules\Lab\Presentation\Http\Controllers;

use App\Modules\Lab\Application\Commands\BulkUpdateLabOrdersCommand;
use App\Modules\Lab\Application\Handlers\BulkUpdateLabOrdersCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class LabOrderBulkController
{
    public function update(Request $request, BulkUpdateLabOrdersCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1', 'max:100'],
            'order_ids.*' => ['required', 'uuid', 'distinct'],
            'changes' => ['required', 'array'],
            'changes.encounter_id' => ['sometimes', 'nullable', 'uuid'],
            'changes.treatment_item_id' => ['sometimes', 'nullable', 'uuid'],
            'changes.lab_test_id' => ['sometimes', 'filled', 'uuid'],
            'changes.lab_provider_key' => ['sometimes', 'filled', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'changes.ordered_at' => ['sometimes', 'filled', 'date'],
            'changes.timezone' => ['sometimes', 'filled', 'timezone:all'],
            'changes.notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $changes = $this->filterAllowedChanges($validated['changes'] ?? []);

        if ($changes === []) {
            throw ValidationException::withMessages([
                'changes' => ['At least one updatable field is required.'],
            ]);
        }

        $orderIds = $this->normalizeOrderIds($validated['order_ids'] ?? null);
        $result = $handler->handle(new BulkUpdateLabOrdersCommand($orderIds, $changes));

        return response()->json([
            'status' => 'lab_orders_bulk_updated',
            'data' => $result->toArray(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterAllowedChanges(mixed $changes): array
    {
        if (! is_array($changes)) {
            return [];
        }

        $normalized = [];

        foreach ([
            'encounter_id',
            'treatment_item_id',
            'lab_test_id',
            'lab_provider_key',
            'ordered_at',
            'timezone',
            'notes',
        ] as $key) {
            if (array_key_exists($key, $changes)) {
                /** @psalm-suppress MixedAssignment */
                $normalized[$key] = $changes[$key];
            }
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function normalizeOrderIds(mixed $orderIds): array
    {
        if (! is_array($orderIds)) {
            return [];
        }

        $normalized = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($orderIds as $orderId) {
            if (is_string($orderId)) {
                $normalized[] = $orderId;
            }
        }

        return $normalized;
    }
}
