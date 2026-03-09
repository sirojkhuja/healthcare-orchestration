<?php

namespace App\Modules\Insurance\Presentation\Http\Controllers;

use App\Modules\Insurance\Application\Commands\AttachPatientInsuranceCommand;
use App\Modules\Insurance\Application\Commands\DetachPatientInsuranceCommand;
use App\Modules\Insurance\Application\Handlers\AttachPatientInsuranceCommandHandler;
use App\Modules\Insurance\Application\Handlers\DetachPatientInsuranceCommandHandler;
use App\Modules\Insurance\Application\Handlers\ListPatientInsuranceQueryHandler;
use App\Modules\Insurance\Application\Queries\ListPatientInsuranceQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientInsuranceController
{
    public function create(string $patientId, Request $request, AttachPatientInsuranceCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'insurance_code' => ['required', 'string', 'max:64'],
            'policy_number' => ['required', 'string', 'max:120'],
            'member_number' => ['nullable', 'string', 'max:120'],
            'group_number' => ['nullable', 'string', 'max:120'],
            'plan_name' => ['nullable', 'string', 'max:160'],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $policy = $handler->handle(new AttachPatientInsuranceCommand($patientId, $validated));

        return response()->json([
            'status' => 'patient_insurance_attached',
            'data' => $policy->toArray(),
        ], 201);
    }

    public function delete(string $patientId, string $policyId, DetachPatientInsuranceCommandHandler $handler): JsonResponse
    {
        $policy = $handler->handle(new DetachPatientInsuranceCommand($patientId, $policyId));

        return response()->json([
            'status' => 'patient_insurance_detached',
            'data' => $policy->toArray(),
        ]);
    }

    public function list(string $patientId, ListPatientInsuranceQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($policy): array => $policy->toArray(),
                $handler->handle(new ListPatientInsuranceQuery($patientId)),
            ),
        ]);
    }
}
