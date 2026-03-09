<?php

namespace App\Modules\Provider\Presentation\Http\Controllers;

use App\Modules\Provider\Application\Commands\CreateProviderTimeOffCommand;
use App\Modules\Provider\Application\Commands\DeleteProviderTimeOffCommand;
use App\Modules\Provider\Application\Commands\UpdateProviderTimeOffCommand;
use App\Modules\Provider\Application\Handlers\CreateProviderTimeOffCommandHandler;
use App\Modules\Provider\Application\Handlers\DeleteProviderTimeOffCommandHandler;
use App\Modules\Provider\Application\Handlers\ListProviderTimeOffQueryHandler;
use App\Modules\Provider\Application\Handlers\UpdateProviderTimeOffCommandHandler;
use App\Modules\Provider\Application\Queries\ListProviderTimeOffQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ProviderTimeOffController
{
    public function create(
        string $providerId,
        Request $request,
        CreateProviderTimeOffCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $timeOff = $handler->handle(new CreateProviderTimeOffCommand($providerId, $validated));

        return response()->json([
            'status' => 'provider_time_off_created',
            'data' => $timeOff->toArray(),
        ], 201);
    }

    public function delete(
        string $providerId,
        string $timeOffId,
        DeleteProviderTimeOffCommandHandler $handler,
    ): JsonResponse {
        $timeOff = $handler->handle(new DeleteProviderTimeOffCommand($providerId, $timeOffId));

        return response()->json([
            'status' => 'provider_time_off_deleted',
            'data' => $timeOff->toArray(),
        ]);
    }

    public function list(string $providerId, ListProviderTimeOffQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($timeOff): array => $timeOff->toArray(),
                $handler->handle(new ListProviderTimeOffQuery($providerId)),
            ),
        ]);
    }

    public function update(
        string $providerId,
        string $timeOffId,
        Request $request,
        UpdateProviderTimeOffCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $timeOff = $handler->handle(new UpdateProviderTimeOffCommand($providerId, $timeOffId, $validated));

        return response()->json([
            'status' => 'provider_time_off_updated',
            'data' => $timeOff->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'specific_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:5000'],
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
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'specific_date' => ['sometimes', 'date_format:Y-m-d'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
