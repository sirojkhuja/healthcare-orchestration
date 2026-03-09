<?php

namespace App\Modules\Provider\Presentation\Http\Controllers;

use App\Modules\Provider\Application\Commands\SetProviderSpecialtiesCommand;
use App\Modules\Provider\Application\Handlers\ListProviderSpecialtiesQueryHandler;
use App\Modules\Provider\Application\Handlers\SetProviderSpecialtiesCommandHandler;
use App\Modules\Provider\Application\Queries\ListProviderSpecialtiesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProviderSpecialtyController
{
    public function list(string $providerId, ListProviderSpecialtiesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($specialty): array => $specialty->toArray(),
                $handler->handle(new ListProviderSpecialtiesQuery($providerId)),
            ),
        ]);
    }

    public function update(
        string $providerId,
        Request $request,
        SetProviderSpecialtiesCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'specialties' => ['required', 'array', 'max:20'],
            'specialties.*.specialty_id' => ['required', 'uuid', 'distinct'],
            'specialties.*.is_primary' => ['sometimes', 'boolean'],
        ]);
        /** @var array<string, mixed> $validated */
        $specialties = $handler->handle(new SetProviderSpecialtiesCommand($providerId, $validated));

        return response()->json([
            'status' => 'provider_specialties_updated',
            'data' => array_map(
                static fn ($specialty): array => $specialty->toArray(),
                $specialties,
            ),
        ]);
    }
}
