<?php

namespace App\Modules\Pharmacy\Presentation\Http\Controllers;

use App\Modules\Pharmacy\Application\Commands\AddAllergyCommand;
use App\Modules\Pharmacy\Application\Commands\RemoveAllergyCommand;
use App\Modules\Pharmacy\Application\Handlers\AddAllergyCommandHandler;
use App\Modules\Pharmacy\Application\Handlers\ListAllergiesQueryHandler;
use App\Modules\Pharmacy\Application\Handlers\RemoveAllergyCommandHandler;
use App\Modules\Pharmacy\Application\Queries\ListAllergiesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientAllergyController
{
    public function create(string $patientId, Request $request, AddAllergyCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'medication_id' => ['sometimes', 'nullable', 'uuid'],
            'allergen_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reaction' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'severity' => ['sometimes', 'nullable', 'string', 'in:mild,moderate,severe,life_threatening'],
            'noted_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $allergy = $handler->handle(new AddAllergyCommand($patientId, $validated));

        return response()->json([
            'status' => 'patient_allergy_created',
            'data' => $allergy->toArray(),
        ], 201);
    }

    public function delete(string $patientId, string $allergyId, RemoveAllergyCommandHandler $handler): JsonResponse
    {
        $allergy = $handler->handle(new RemoveAllergyCommand($patientId, $allergyId));

        return response()->json([
            'status' => 'patient_allergy_deleted',
            'data' => $allergy->toArray(),
        ]);
    }

    public function list(string $patientId, ListAllergiesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($allergy): array => $allergy->toArray(),
                $handler->handle(new ListAllergiesQuery($patientId)),
            ),
        ]);
    }
}
