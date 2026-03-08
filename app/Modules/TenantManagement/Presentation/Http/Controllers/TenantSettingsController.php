<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\UpdateTenantSettingsCommand;
use App\Modules\TenantManagement\Application\Data\TenantSettingsData;
use App\Modules\TenantManagement\Application\Handlers\GetTenantSettingsQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\UpdateTenantSettingsCommandHandler;
use App\Modules\TenantManagement\Application\Queries\GetTenantSettingsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantSettingsController
{
    public function show(string $tenantId, GetTenantSettingsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetTenantSettingsQuery($tenantId))->toArray(),
        ]);
    }

    public function update(string $tenantId, Request $request, UpdateTenantSettingsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['present', 'nullable', 'string', 'max:16'],
            'timezone' => ['present', 'nullable', 'timezone'],
            'currency' => ['present', 'nullable', 'string', 'size:3', 'alpha'],
        ]);

        $settings = $handler->handle(new UpdateTenantSettingsCommand(
            tenantId: $tenantId,
            settings: new TenantSettingsData(
                locale: $this->nullableString($validated, 'locale'),
                timezone: $this->nullableString($validated, 'timezone'),
                currency: $this->nullableString($validated, 'currency'),
            ),
        ));

        return response()->json([
            'status' => 'tenant_settings_updated',
            'data' => $settings->toArray(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
