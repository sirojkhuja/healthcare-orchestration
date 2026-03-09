<?php

namespace App\Modules\Provider\Presentation\Http\Controllers;

use App\Modules\Provider\Application\Commands\AddProviderLicenseCommand;
use App\Modules\Provider\Application\Commands\RemoveProviderLicenseCommand;
use App\Modules\Provider\Application\Handlers\AddProviderLicenseCommandHandler;
use App\Modules\Provider\Application\Handlers\ListProviderLicensesQueryHandler;
use App\Modules\Provider\Application\Handlers\RemoveProviderLicenseCommandHandler;
use App\Modules\Provider\Application\Queries\ListProviderLicensesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProviderLicenseController
{
    public function create(
        string $providerId,
        Request $request,
        AddProviderLicenseCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'license_type' => ['required', 'string', 'max:64'],
            'license_number' => ['required', 'string', 'max:120'],
            'issuing_authority' => ['required', 'string', 'max:160'],
            'jurisdiction' => ['nullable', 'string', 'max:120'],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $license = $handler->handle(new AddProviderLicenseCommand($providerId, $validated));

        return response()->json([
            'status' => 'provider_license_added',
            'data' => $license->toArray(),
        ], 201);
    }

    public function delete(
        string $providerId,
        string $licenseId,
        RemoveProviderLicenseCommandHandler $handler,
    ): JsonResponse {
        $license = $handler->handle(new RemoveProviderLicenseCommand($providerId, $licenseId));

        return response()->json([
            'status' => 'provider_license_removed',
            'data' => $license->toArray(),
        ]);
    }

    public function list(string $providerId, ListProviderLicensesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($license): array => $license->toArray(),
                $handler->handle(new ListProviderLicensesQuery($providerId)),
            ),
        ]);
    }
}
