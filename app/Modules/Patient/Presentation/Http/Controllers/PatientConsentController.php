<?php

namespace App\Modules\Patient\Presentation\Http\Controllers;

use App\Modules\Patient\Application\Commands\CreatePatientConsentCommand;
use App\Modules\Patient\Application\Commands\RevokePatientConsentCommand;
use App\Modules\Patient\Application\Handlers\CreatePatientConsentCommandHandler;
use App\Modules\Patient\Application\Handlers\ListPatientConsentsQueryHandler;
use App\Modules\Patient\Application\Handlers\RevokePatientConsentCommandHandler;
use App\Modules\Patient\Application\Queries\ListPatientConsentsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientConsentController
{
    public function create(string $patientId, Request $request, CreatePatientConsentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'consent_type' => ['required', 'string', 'max:64'],
            'granted_by_name' => ['required', 'string', 'max:160'],
            'granted_by_relationship' => ['nullable', 'string', 'max:120'],
            'granted_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $consent = $handler->handle(new CreatePatientConsentCommand($patientId, $validated));

        return response()->json([
            'status' => 'patient_consent_created',
            'data' => $consent->toArray(),
        ], 201);
    }

    public function list(string $patientId, ListPatientConsentsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($consent): array => $consent->toArray(),
                $handler->handle(new ListPatientConsentsQuery($patientId)),
            ),
        ]);
    }

    public function revoke(
        string $patientId,
        string $consentId,
        Request $request,
        RevokePatientConsentCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $consent = $handler->handle(new RevokePatientConsentCommand(
            patientId: $patientId,
            consentId: $consentId,
            reason: $this->nullableString($validated, 'reason'),
        ));

        return response()->json([
            'status' => 'patient_consent_revoked',
            'data' => $consent->toArray(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
