<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\UpdateTenantLimitsCommand;
use App\Modules\TenantManagement\Application\Data\TenantLimitsData;
use App\Modules\TenantManagement\Application\Handlers\GetTenantLimitsQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\UpdateTenantLimitsCommandHandler;
use App\Modules\TenantManagement\Application\Queries\GetTenantLimitsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantLimitsController
{
    public function show(string $tenantId, GetTenantLimitsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetTenantLimitsQuery($tenantId))->toArray(),
        ]);
    }

    public function update(string $tenantId, Request $request, UpdateTenantLimitsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'users' => ['present', 'nullable', 'integer', 'min:0'],
            'clinics' => ['present', 'nullable', 'integer', 'min:0'],
            'providers' => ['present', 'nullable', 'integer', 'min:0'],
            'patients' => ['present', 'nullable', 'integer', 'min:0'],
            'storage_gb' => ['present', 'nullable', 'numeric', 'min:0'],
            'monthly_notifications' => ['present', 'nullable', 'integer', 'min:0'],
        ]);

        $limits = $handler->handle(new UpdateTenantLimitsCommand(
            tenantId: $tenantId,
            limits: new TenantLimitsData(
                users: $this->nullableInt($validated, 'users'),
                clinics: $this->nullableInt($validated, 'clinics'),
                providers: $this->nullableInt($validated, 'providers'),
                patients: $this->nullableInt($validated, 'patients'),
                storageGb: $this->nullableFloat($validated, 'storage_gb'),
                monthlyNotifications: $this->nullableInt($validated, 'monthly_notifications'),
            ),
        ));

        return response()->json([
            'status' => 'tenant_limits_updated',
            'data' => $limits->toArray(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableFloat(array $validated, string $key): ?float
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableInt(array $validated, string $key): ?int
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
