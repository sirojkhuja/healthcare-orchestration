<?php

namespace App\Modules\Integrations\Presentation\Http\Controllers;

use App\Modules\Integrations\Application\Commands\AttachPatientExternalRefCommand;
use App\Modules\Integrations\Application\Commands\DetachPatientExternalRefCommand;
use App\Modules\Integrations\Application\Handlers\AttachPatientExternalRefCommandHandler;
use App\Modules\Integrations\Application\Handlers\DetachPatientExternalRefCommandHandler;
use App\Modules\Integrations\Application\Handlers\ListPatientExternalRefsQueryHandler;
use App\Modules\Integrations\Application\Queries\ListPatientExternalRefsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientExternalReferenceController
{
    public function create(
        string $patientId,
        Request $request,
        AttachPatientExternalRefCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'integration_key' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/'],
            'external_id' => ['required', 'string', 'max:191'],
            'external_type' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9._-]+$/'],
            'display_name' => ['nullable', 'string', 'max:160'],
            'metadata' => ['nullable', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $reference = $handler->handle(new AttachPatientExternalRefCommand($patientId, $validated));

        return response()->json([
            'status' => 'patient_external_ref_attached',
            'data' => $reference->toArray(),
        ], 201);
    }

    public function delete(
        string $patientId,
        string $refId,
        DetachPatientExternalRefCommandHandler $handler,
    ): JsonResponse {
        $reference = $handler->handle(new DetachPatientExternalRefCommand($patientId, $refId));

        return response()->json([
            'status' => 'patient_external_ref_detached',
            'data' => $reference->toArray(),
        ]);
    }

    public function list(string $patientId, ListPatientExternalRefsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($reference): array => $reference->toArray(),
                $handler->handle(new ListPatientExternalRefsQuery($patientId)),
            ),
        ]);
    }
}
