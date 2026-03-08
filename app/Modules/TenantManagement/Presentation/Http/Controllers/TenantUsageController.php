<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Handlers\GetTenantUsageQueryHandler;
use App\Modules\TenantManagement\Application\Queries\GetTenantUsageQuery;
use Illuminate\Http\JsonResponse;

final class TenantUsageController
{
    public function show(string $tenantId, GetTenantUsageQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetTenantUsageQuery($tenantId))->toArray(),
        ]);
    }
}
