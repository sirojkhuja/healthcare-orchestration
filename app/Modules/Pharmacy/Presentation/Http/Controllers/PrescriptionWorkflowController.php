<?php

namespace App\Modules\Pharmacy\Presentation\Http\Controllers;

use App\Modules\Pharmacy\Application\Commands\CancelPrescriptionCommand;
use App\Modules\Pharmacy\Application\Commands\DispensePrescriptionCommand;
use App\Modules\Pharmacy\Application\Commands\IssuePrescriptionCommand;
use App\Modules\Pharmacy\Application\Handlers\CancelPrescriptionCommandHandler;
use App\Modules\Pharmacy\Application\Handlers\DispensePrescriptionCommandHandler;
use App\Modules\Pharmacy\Application\Handlers\IssuePrescriptionCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PrescriptionWorkflowController
{
    public function cancel(string $prescriptionId, Request $request, CancelPrescriptionCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string} $validated */
        $prescription = $handler->handle(new CancelPrescriptionCommand($prescriptionId, trim($validated['reason'])));

        return response()->json([
            'status' => 'prescription_canceled',
            'data' => $prescription->toArray(),
        ]);
    }

    public function dispense(string $prescriptionId, DispensePrescriptionCommandHandler $handler): JsonResponse
    {
        $prescription = $handler->handle(new DispensePrescriptionCommand($prescriptionId));

        return response()->json([
            'status' => 'prescription_dispensed',
            'data' => $prescription->toArray(),
        ]);
    }

    public function issue(string $prescriptionId, IssuePrescriptionCommandHandler $handler): JsonResponse
    {
        $prescription = $handler->handle(new IssuePrescriptionCommand($prescriptionId));

        return response()->json([
            'status' => 'prescription_issued',
            'data' => $prescription->toArray(),
        ]);
    }
}
