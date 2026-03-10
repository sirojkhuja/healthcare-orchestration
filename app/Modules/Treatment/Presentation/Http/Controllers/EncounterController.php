<?php

namespace App\Modules\Treatment\Presentation\Http\Controllers;

use App\Modules\Treatment\Application\Commands\CreateEncounterCommand;
use App\Modules\Treatment\Application\Commands\DeleteEncounterCommand;
use App\Modules\Treatment\Application\Commands\UpdateEncounterCommand;
use App\Modules\Treatment\Application\Data\EncounterListCriteria;
use App\Modules\Treatment\Application\Handlers\CreateEncounterCommandHandler;
use App\Modules\Treatment\Application\Handlers\DeleteEncounterCommandHandler;
use App\Modules\Treatment\Application\Handlers\ExportEncountersQueryHandler;
use App\Modules\Treatment\Application\Handlers\GetEncounterQueryHandler;
use App\Modules\Treatment\Application\Handlers\ListEncountersQueryHandler;
use App\Modules\Treatment\Application\Handlers\UpdateEncounterCommandHandler;
use App\Modules\Treatment\Application\Queries\ExportEncountersQuery;
use App\Modules\Treatment\Application\Queries\GetEncounterQuery;
use App\Modules\Treatment\Application\Queries\ListEncountersQuery;
use App\Modules\Treatment\Domain\Encounters\EncounterStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class EncounterController
{
    public function create(Request $request, CreateEncounterCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $encounter = $handler->handle(new CreateEncounterCommand($validated));

        return response()->json([
            'status' => 'encounter_created',
            'data' => $encounter->toArray(),
        ], 201);
    }

    public function delete(string $encounterId, DeleteEncounterCommandHandler $handler): JsonResponse
    {
        $encounter = $handler->handle(new DeleteEncounterCommand($encounterId));

        return response()->json([
            'status' => 'encounter_deleted',
            'data' => $encounter->toArray(),
        ]);
    }

    public function export(Request $request, ExportEncountersQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 1000, 1000);
        $validated = $request->validate($this->exportRules(1000));
        /** @var array<string, mixed> $validated */
        $format = array_key_exists('format', $validated) && is_string($validated['format'])
            ? $validated['format']
            : 'csv';

        $export = $handler->handle(new ExportEncountersQuery($criteria, $format));

        return response()->json([
            'status' => 'encounter_export_created',
            'data' => $export->toArray(),
        ]);
    }

    public function list(Request $request, ListEncountersQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($encounter): array => $encounter->toArray(),
                $handler->handle(new ListEncountersQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $encounterId, GetEncounterQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetEncounterQuery($encounterId))->toArray(),
        ]);
    }

    public function update(string $encounterId, Request $request, UpdateEncounterCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $encounter = $handler->handle(new UpdateEncounterCommand($encounterId, $validated));

        return response()->json([
            'status' => 'encounter_updated',
            'data' => $encounter->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'provider_id' => ['required', 'uuid'],
            'treatment_plan_id' => ['sometimes', 'nullable', 'uuid'],
            'appointment_id' => ['sometimes', 'nullable', 'uuid'],
            'clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'room_id' => ['sometimes', 'nullable', 'uuid'],
            'encountered_at' => ['required', 'date'],
            'timezone' => ['required', 'timezone:all'],
            'chief_complaint' => ['sometimes', 'nullable', 'string', 'max:255'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'follow_up_instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function exportRules(int $maxLimit): array
    {
        return $this->listRules($maxLimit) + [
            'format' => ['sometimes', 'string', 'in:csv'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function listRules(int $maxLimit): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:'.implode(',', EncounterStatus::all())],
            'patient_id' => ['nullable', 'uuid'],
            'provider_id' => ['nullable', 'uuid'],
            'treatment_plan_id' => ['nullable', 'uuid'],
            'appointment_id' => ['nullable', 'uuid'],
            'clinic_id' => ['nullable', 'uuid'],
            'encounter_from' => ['nullable', 'date'],
            'encounter_to' => ['nullable', 'date'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'patient_id' => ['sometimes', 'filled', 'uuid'],
            'provider_id' => ['sometimes', 'filled', 'uuid'],
            'treatment_plan_id' => ['sometimes', 'nullable', 'uuid'],
            'appointment_id' => ['sometimes', 'nullable', 'uuid'],
            'clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'room_id' => ['sometimes', 'nullable', 'uuid'],
            'status' => ['sometimes', 'filled', 'string', 'in:'.implode(',', EncounterStatus::all())],
            'encountered_at' => ['sometimes', 'filled', 'date'],
            'timezone' => ['sometimes', 'filled', 'timezone:all'],
            'chief_complaint' => ['sometimes', 'nullable', 'string', 'max:255'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'follow_up_instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function assertNonEmptyPatch(array $validated): void
    {
        if ($validated === []) {
            throw ValidationException::withMessages([
                'payload' => ['At least one updatable field is required.'],
            ]);
        }
    }

    private function criteria(Request $request, int $defaultLimit, int $maxLimit): EncounterListCriteria
    {
        $validated = $request->validate($this->listRules($maxLimit));
        /** @var array<string, mixed> $validated */
        $this->assertDateRange($validated, 'encounter_from', 'encounter_to', 'encountered_at');
        $this->assertDateRange($validated, 'created_from', 'created_to', 'created_at');

        return new EncounterListCriteria(
            query: $this->stringValue($validated, 'q'),
            status: $this->stringValue($validated, 'status'),
            patientId: $this->stringValue($validated, 'patient_id'),
            providerId: $this->stringValue($validated, 'provider_id'),
            treatmentPlanId: $this->stringValue($validated, 'treatment_plan_id'),
            appointmentId: $this->stringValue($validated, 'appointment_id'),
            clinicId: $this->stringValue($validated, 'clinic_id'),
            encounterFrom: $this->stringValue($validated, 'encounter_from'),
            encounterTo: $this->stringValue($validated, 'encounter_to'),
            createdFrom: $this->stringValue($validated, 'created_from'),
            createdTo: $this->stringValue($validated, 'created_to'),
            limit: $this->integerValue($validated, 'limit', $defaultLimit),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertDateRange(array $validated, string $fromKey, string $toKey, string $errorKey): void
    {
        $from = $this->stringValue($validated, $fromKey);
        $to = $this->stringValue($validated, $toKey);

        if ($from !== null && $to !== null && CarbonImmutable::parse($from)->greaterThan(CarbonImmutable::parse($to))) {
            throw ValidationException::withMessages([
                $errorKey => ['The end date must be on or after the start date.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function integerValue(array $validated, string $key, int $default): int
    {
        return array_key_exists($key, $validated) && is_numeric($validated[$key])
            ? (int) $validated[$key]
            : $default;
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
