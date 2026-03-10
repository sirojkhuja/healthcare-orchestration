<?php

namespace App\Modules\Lab\Presentation\Http\Controllers;

use App\Modules\Lab\Application\Commands\CreateLabOrderCommand;
use App\Modules\Lab\Application\Commands\DeleteLabOrderCommand;
use App\Modules\Lab\Application\Commands\UpdateLabOrderCommand;
use App\Modules\Lab\Application\Data\LabOrderSearchCriteria;
use App\Modules\Lab\Application\Handlers\CreateLabOrderCommandHandler;
use App\Modules\Lab\Application\Handlers\DeleteLabOrderCommandHandler;
use App\Modules\Lab\Application\Handlers\ExportLabOrdersQueryHandler;
use App\Modules\Lab\Application\Handlers\GetLabOrderQueryHandler;
use App\Modules\Lab\Application\Handlers\ListLabOrdersQueryHandler;
use App\Modules\Lab\Application\Handlers\SearchLabOrdersQueryHandler;
use App\Modules\Lab\Application\Handlers\UpdateLabOrderCommandHandler;
use App\Modules\Lab\Application\Queries\ExportLabOrdersQuery;
use App\Modules\Lab\Application\Queries\GetLabOrderQuery;
use App\Modules\Lab\Application\Queries\ListLabOrdersQuery;
use App\Modules\Lab\Application\Queries\SearchLabOrdersQuery;
use App\Modules\Lab\Domain\LabOrders\LabOrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class LabOrderController
{
    public function create(Request $request, CreateLabOrderCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $order = $handler->handle(new CreateLabOrderCommand($validated));

        return response()->json([
            'status' => 'lab_order_created',
            'data' => $order->toArray(),
        ], 201);
    }

    public function delete(string $orderId, DeleteLabOrderCommandHandler $handler): JsonResponse
    {
        $order = $handler->handle(new DeleteLabOrderCommand($orderId));

        return response()->json([
            'status' => 'lab_order_deleted',
            'data' => $order->toArray(),
        ]);
    }

    public function export(Request $request, ExportLabOrdersQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 1000, 1000);
        $validated = $request->validate($this->exportRules(1000));
        /** @var array<string, mixed> $validated */
        $format = array_key_exists('format', $validated) && is_string($validated['format'])
            ? $validated['format']
            : 'csv';
        $export = $handler->handle(new ExportLabOrdersQuery($criteria, $format));

        return response()->json([
            'status' => 'lab_order_export_created',
            'data' => $export->toArray(),
        ]);
    }

    public function list(Request $request, ListLabOrdersQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($order): array => $order->toArray(),
                $handler->handle(new ListLabOrdersQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function search(Request $request, SearchLabOrdersQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($order): array => $order->toArray(),
                $handler->handle(new SearchLabOrdersQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $orderId, GetLabOrderQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetLabOrderQuery($orderId))->toArray(),
        ]);
    }

    public function update(string $orderId, Request $request, UpdateLabOrderCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $order = $handler->handle(new UpdateLabOrderCommand($orderId, $validated));

        return response()->json([
            'status' => 'lab_order_updated',
            'data' => $order->toArray(),
        ]);
    }

    private function criteria(Request $request, int $defaultLimit, int $maxLimit): LabOrderSearchCriteria
    {
        $validated = $request->validate($this->listRules($maxLimit));
        /** @var array<string, mixed> $validated */
        $this->assertDateRange($validated, 'ordered_from', 'ordered_to', 'ordered_at');
        $this->assertDateRange($validated, 'created_from', 'created_to', 'created_at');

        return new LabOrderSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            status: $this->stringValue($validated, 'status'),
            patientId: $this->stringValue($validated, 'patient_id'),
            providerId: $this->stringValue($validated, 'provider_id'),
            encounterId: $this->stringValue($validated, 'encounter_id'),
            labTestId: $this->stringValue($validated, 'lab_test_id'),
            labProviderKey: $this->stringValue($validated, 'lab_provider_key'),
            orderedFrom: $this->stringValue($validated, 'ordered_from'),
            orderedTo: $this->stringValue($validated, 'ordered_to'),
            createdFrom: $this->stringValue($validated, 'created_from'),
            createdTo: $this->stringValue($validated, 'created_to'),
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : $defaultLimit,
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'provider_id' => ['required', 'uuid'],
            'encounter_id' => ['sometimes', 'nullable', 'uuid'],
            'treatment_item_id' => ['sometimes', 'nullable', 'uuid'],
            'lab_test_id' => ['required', 'uuid'],
            'lab_provider_key' => ['required', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'ordered_at' => ['required', 'date'],
            'timezone' => ['required', 'timezone:all'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function exportRules(int $maxLimit): array
    {
        return $this->listRules($maxLimit) + [
            'format' => ['sometimes', 'string', 'in:csv'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function listRules(int $maxLimit): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:'.implode(',', LabOrderStatus::all())],
            'patient_id' => ['nullable', 'uuid'],
            'provider_id' => ['nullable', 'uuid'],
            'encounter_id' => ['nullable', 'uuid'],
            'lab_test_id' => ['nullable', 'uuid'],
            'lab_provider_key' => ['nullable', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'ordered_from' => ['nullable', 'date'],
            'ordered_to' => ['nullable', 'date'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'patient_id' => ['sometimes', 'filled', 'uuid'],
            'provider_id' => ['sometimes', 'filled', 'uuid'],
            'encounter_id' => ['sometimes', 'nullable', 'uuid'],
            'treatment_item_id' => ['sometimes', 'nullable', 'uuid'],
            'lab_test_id' => ['sometimes', 'filled', 'uuid'],
            'lab_provider_key' => ['sometimes', 'filled', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'ordered_at' => ['sometimes', 'filled', 'date'],
            'timezone' => ['sometimes', 'filled', 'timezone:all'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function assertNonEmptyPatch(array $validated): void
    {
        if ($validated === []) {
            throw ValidationException::withMessages([
                'payload' => ['At least one updatable field is required.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertDateRange(array $validated, string $fromKey, string $toKey, string $errorKey): void
    {
        $from = $this->stringValue($validated, $fromKey);
        $to = $this->stringValue($validated, $toKey);

        if ($from !== null && $to !== null && CarbonImmutable::parse($from)->greaterThan(CarbonImmutable::parse($to))) {
            throw ValidationException::withMessages([
                $errorKey => ['The end date must be on or after the start date.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }
}
