<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Modules\Billing\Application\Commands\CreateBillableServiceCommand;
use App\Modules\Billing\Application\Commands\DeleteBillableServiceCommand;
use App\Modules\Billing\Application\Commands\UpdateBillableServiceCommand;
use App\Modules\Billing\Application\Data\BillableServiceListCriteria;
use App\Modules\Billing\Application\Handlers\CreateBillableServiceCommandHandler;
use App\Modules\Billing\Application\Handlers\DeleteBillableServiceCommandHandler;
use App\Modules\Billing\Application\Handlers\ListBillableServicesQueryHandler;
use App\Modules\Billing\Application\Handlers\UpdateBillableServiceCommandHandler;
use App\Modules\Billing\Application\Queries\ListBillableServicesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class BillableServiceController
{
    public function create(Request $request, CreateBillableServiceCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $service = $handler->handle(new CreateBillableServiceCommand($validated));

        return response()->json([
            'status' => 'billable_service_created',
            'data' => $service->toArray(),
        ], 201);
    }

    public function delete(string $serviceId, DeleteBillableServiceCommandHandler $handler): JsonResponse
    {
        $service = $handler->handle(new DeleteBillableServiceCommand($serviceId));

        return response()->json([
            'status' => 'billable_service_deleted',
            'data' => $service->toArray(),
        ]);
    }

    public function list(Request $request, ListBillableServicesQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request);

        return response()->json([
            'data' => array_map(
                static fn ($service): array => $service->toArray(),
                $handler->handle(new ListBillableServicesQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function update(string $serviceId, Request $request, UpdateBillableServiceCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $service = $handler->handle(new UpdateBillableServiceCommand($serviceId, $validated));

        return response()->json([
            'status' => 'billable_service_updated',
            'data' => $service->toArray(),
        ]);
    }

    private function criteria(Request $request): BillableServiceListCriteria
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */

        return new BillableServiceListCriteria(
            query: $this->stringValue($validated, 'q'),
            category: $this->stringValue($validated, 'category'),
            isActive: array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null,
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 25,
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'code' => ['sometimes', 'filled', 'string', 'max:120'],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
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
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }
}
