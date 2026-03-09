<?php

namespace App\Modules\Provider\Presentation\Http\Controllers;

use App\Modules\Provider\Application\Commands\CreateSpecialtyCommand;
use App\Modules\Provider\Application\Commands\DeleteSpecialtyCommand;
use App\Modules\Provider\Application\Commands\UpdateSpecialtyCommand;
use App\Modules\Provider\Application\Handlers\CreateSpecialtyCommandHandler;
use App\Modules\Provider\Application\Handlers\DeleteSpecialtyCommandHandler;
use App\Modules\Provider\Application\Handlers\ListSpecialtiesQueryHandler;
use App\Modules\Provider\Application\Handlers\UpdateSpecialtyCommandHandler;
use App\Modules\Provider\Application\Queries\ListSpecialtiesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SpecialtyController
{
    public function create(Request $request, CreateSpecialtyCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $specialty = $handler->handle(new CreateSpecialtyCommand($validated));

        return response()->json([
            'status' => 'specialty_created',
            'data' => $specialty->toArray(),
        ], 201);
    }

    public function delete(string $specialtyId, DeleteSpecialtyCommandHandler $handler): JsonResponse
    {
        $specialty = $handler->handle(new DeleteSpecialtyCommand($specialtyId));

        return response()->json([
            'status' => 'specialty_deleted',
            'data' => $specialty->toArray(),
        ]);
    }

    public function list(ListSpecialtiesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($specialty): array => $specialty->toArray(),
                $handler->handle(new ListSpecialtiesQuery),
            ),
        ]);
    }

    public function update(
        string $specialtyId,
        Request $request,
        UpdateSpecialtyCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => ['sometimes', 'filled', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $specialty = $handler->handle(new UpdateSpecialtyCommand($specialtyId, $validated));

        return response()->json([
            'status' => 'specialty_updated',
            'data' => $specialty->toArray(),
        ]);
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
}
