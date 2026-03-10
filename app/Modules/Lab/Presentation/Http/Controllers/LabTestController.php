<?php

namespace App\Modules\Lab\Presentation\Http\Controllers;

use App\Modules\Lab\Application\Commands\CreateLabTestCommand;
use App\Modules\Lab\Application\Commands\DeleteLabTestCommand;
use App\Modules\Lab\Application\Commands\UpdateLabTestCommand;
use App\Modules\Lab\Application\Data\LabTestListCriteria;
use App\Modules\Lab\Application\Handlers\CreateLabTestCommandHandler;
use App\Modules\Lab\Application\Handlers\DeleteLabTestCommandHandler;
use App\Modules\Lab\Application\Handlers\ListLabTestsQueryHandler;
use App\Modules\Lab\Application\Handlers\UpdateLabTestCommandHandler;
use App\Modules\Lab\Application\Queries\ListLabTestsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class LabTestController
{
    public function create(Request $request, CreateLabTestCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $labTest = $handler->handle(new CreateLabTestCommand($validated));

        return response()->json([
            'status' => 'lab_test_created',
            'data' => $labTest->toArray(),
        ], 201);
    }

    public function delete(string $testId, DeleteLabTestCommandHandler $handler): JsonResponse
    {
        $labTest = $handler->handle(new DeleteLabTestCommand($testId));

        return response()->json([
            'status' => 'lab_test_deleted',
            'data' => $labTest->toArray(),
        ]);
    }

    public function list(Request $request, ListLabTestsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request);

        return response()->json([
            'data' => array_map(
                static fn ($labTest): array => $labTest->toArray(),
                $handler->handle(new ListLabTestsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function update(string $testId, Request $request, UpdateLabTestCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $labTest = $handler->handle(new UpdateLabTestCommand($testId, $validated));

        return response()->json([
            'status' => 'lab_test_updated',
            'data' => $labTest->toArray(),
        ]);
    }

    private function criteria(Request $request): LabTestListCriteria
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'lab_provider_key' => ['nullable', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'is_active' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */

        return new LabTestListCriteria(
            query: $this->stringValue($validated, 'q'),
            labProviderKey: $this->stringValue($validated, 'lab_provider_key'),
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
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'specimen_type' => ['required', 'string', 'max:64'],
            'result_type' => ['required', 'string', 'in:numeric,text,boolean,json'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'reference_range' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lab_provider_key' => ['required', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'external_test_code' => ['sometimes', 'nullable', 'string', 'max:120'],
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
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'specimen_type' => ['sometimes', 'filled', 'string', 'max:64'],
            'result_type' => ['sometimes', 'filled', 'string', 'in:numeric,text,boolean,json'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'reference_range' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lab_provider_key' => ['sometimes', 'filled', 'string', 'regex:/^[a-z0-9._-]+$/'],
            'external_test_code' => ['sometimes', 'nullable', 'string', 'max:120'],
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
