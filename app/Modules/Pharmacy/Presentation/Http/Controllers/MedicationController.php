<?php

namespace App\Modules\Pharmacy\Presentation\Http\Controllers;

use App\Modules\Pharmacy\Application\Commands\CreateMedicationCommand;
use App\Modules\Pharmacy\Application\Commands\DeleteMedicationCommand;
use App\Modules\Pharmacy\Application\Commands\UpdateMedicationCommand;
use App\Modules\Pharmacy\Application\Data\MedicationListCriteria;
use App\Modules\Pharmacy\Application\Handlers\CreateMedicationCommandHandler;
use App\Modules\Pharmacy\Application\Handlers\DeleteMedicationCommandHandler;
use App\Modules\Pharmacy\Application\Handlers\GetMedicationQueryHandler;
use App\Modules\Pharmacy\Application\Handlers\ListMedicationsQueryHandler;
use App\Modules\Pharmacy\Application\Handlers\SearchMedicationsQueryHandler;
use App\Modules\Pharmacy\Application\Handlers\UpdateMedicationCommandHandler;
use App\Modules\Pharmacy\Application\Queries\GetMedicationQuery;
use App\Modules\Pharmacy\Application\Queries\ListMedicationsQuery;
use App\Modules\Pharmacy\Application\Queries\SearchMedicationsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class MedicationController
{
    public function create(Request $request, CreateMedicationCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $medication = $handler->handle(new CreateMedicationCommand($validated));

        return response()->json([
            'status' => 'medication_created',
            'data' => $medication->toArray(),
        ], 201);
    }

    public function delete(string $medId, DeleteMedicationCommandHandler $handler): JsonResponse
    {
        $medication = $handler->handle(new DeleteMedicationCommand($medId));

        return response()->json([
            'status' => 'medication_deleted',
            'data' => $medication->toArray(),
        ]);
    }

    public function list(Request $request, ListMedicationsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request);

        return response()->json([
            'data' => array_map(
                static fn ($medication): array => $medication->toArray(),
                $handler->handle(new ListMedicationsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function search(Request $request, SearchMedicationsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request);

        return response()->json([
            'data' => array_map(
                static fn ($medication): array => $medication->toArray(),
                $handler->handle(new SearchMedicationsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $medId, GetMedicationQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetMedicationQuery($medId))->toArray(),
        ]);
    }

    public function update(string $medId, Request $request, UpdateMedicationCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $medication = $handler->handle(new UpdateMedicationCommand($medId, $validated));

        return response()->json([
            'status' => 'medication_updated',
            'data' => $medication->toArray(),
        ]);
    }

    private function criteria(Request $request): MedicationListCriteria
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */

        return new MedicationListCriteria(
            query: $this->stringValue($validated, 'q'),
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
            'generic_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'form' => ['sometimes', 'nullable', 'string', 'max:64'],
            'strength' => ['sometimes', 'nullable', 'string', 'max:120'],
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
            'generic_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'form' => ['sometimes', 'nullable', 'string', 'max:64'],
            'strength' => ['sometimes', 'nullable', 'string', 'max:120'],
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
