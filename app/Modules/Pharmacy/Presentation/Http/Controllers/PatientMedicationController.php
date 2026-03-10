<?php

namespace App\Modules\Pharmacy\Presentation\Http\Controllers;

use App\Modules\Pharmacy\Application\Data\PatientMedicationListCriteria;
use App\Modules\Pharmacy\Application\Handlers\ListPatientMedicationsQueryHandler;
use App\Modules\Pharmacy\Application\Queries\ListPatientMedicationsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PatientMedicationController
{
    public function list(string $patientId, Request $request, ListPatientMedicationsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:issued,dispensed,canceled'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        $criteria = new PatientMedicationListCriteria(
            status: $this->stringValue($validated, 'status'),
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 25,
        );

        return response()->json([
            'data' => array_map(
                static fn ($medication): array => $medication->toArray(),
                $handler->handle(new ListPatientMedicationsQuery($patientId, $criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }
}
