<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\ActivateTenantCommand;
use App\Modules\TenantManagement\Application\Commands\SuspendTenantCommand;
use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Handlers\ActivateTenantCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\SuspendTenantCommandHandler;
use Illuminate\Http\JsonResponse;

final class TenantLifecycleController
{
    public function activate(string $tenantId, ActivateTenantCommandHandler $handler): JsonResponse
    {
        return $this->response('tenant_activated', $handler->handle(new ActivateTenantCommand($tenantId)));
    }

    public function suspend(string $tenantId, SuspendTenantCommandHandler $handler): JsonResponse
    {
        return $this->response('tenant_suspended', $handler->handle(new SuspendTenantCommand($tenantId)));
    }

    private function response(string $status, TenantData $tenant): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'data' => $tenant->toArray(),
        ]);
    }
}
