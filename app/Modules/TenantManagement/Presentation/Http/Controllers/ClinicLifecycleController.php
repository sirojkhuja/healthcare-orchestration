<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\ActivateClinicCommand;
use App\Modules\TenantManagement\Application\Commands\DeactivateClinicCommand;
use App\Modules\TenantManagement\Application\Handlers\ActivateClinicCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\DeactivateClinicCommandHandler;
use Illuminate\Http\JsonResponse;

final class ClinicLifecycleController
{
    public function activate(string $clinicId, ActivateClinicCommandHandler $handler): JsonResponse
    {
        $clinic = $handler->handle(new ActivateClinicCommand($clinicId));

        return response()->json([
            'status' => 'clinic_activated',
            'data' => $clinic->toArray(),
        ]);
    }

    public function deactivate(string $clinicId, DeactivateClinicCommandHandler $handler): JsonResponse
    {
        $clinic = $handler->handle(new DeactivateClinicCommand($clinicId));

        return response()->json([
            'status' => 'clinic_deactivated',
            'data' => $clinic->toArray(),
        ]);
    }
}
