<?php

namespace App\Modules\Pharmacy\Presentation\Http\Controllers;

use App\Modules\Pharmacy\Application\Commands\CreatePrescriptionCommand;
use App\Modules\Pharmacy\Application\Commands\DeletePrescriptionCommand;
use App\Modules\Pharmacy\Application\Commands\UpdatePrescriptionCommand;
use App\Modules\Pharmacy\Application\Data\PrescriptionSearchCriteria;
use App\Modules\Pharmacy\Application\Handlers\CreatePrescriptionCommandHandler;
use App\Modules\Pharmacy\Application\Handlers\DeletePrescriptionCommandHandler;
use App\Modules\Pharmacy\Application\Handlers\ExportPrescriptionsQueryHandler;
use App\Modules\Pharmacy\Application\Handlers\GetPrescriptionQueryHandler;
use App\Modules\Pharmacy\Application\Handlers\ListPrescriptionsQueryHandler;
use App\Modules\Pharmacy\Application\Handlers\SearchPrescriptionsQueryHandler;
use App\Modules\Pharmacy\Application\Handlers\UpdatePrescriptionCommandHandler;
use App\Modules\Pharmacy\Application\Queries\ExportPrescriptionsQuery;
use App\Modules\Pharmacy\Application\Queries\GetPrescriptionQuery;
use App\Modules\Pharmacy\Application\Queries\ListPrescriptionsQuery;
use App\Modules\Pharmacy\Application\Queries\SearchPrescriptionsQuery;
use App\Modules\Pharmacy\Domain\Prescriptions\PrescriptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PrescriptionController
{
    public function create(Request $request, CreatePrescriptionCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $prescription = $handler->handle(new CreatePrescriptionCommand($validated));

        return response()->json([
            'status' => 'prescription_created',
            'data' => $prescription->toArray(),
        ], 201);
    }

    public function delete(string $prescriptionId, DeletePrescriptionCommandHandler $handler): JsonResponse
    {
        $prescription = $handler->handle(new DeletePrescriptionCommand($prescriptionId));

        return response()->json([
            'status' => 'prescription_deleted',
            'data' => $prescription->toArray(),
        ]);
    }

    public function export(Request $request, ExportPrescriptionsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 1000, 1000);
        $validated = $request->validate($this->exportRules(1000));
        /** @var array<string, mixed> $validated */
        $format = array_key_exists('format', $validated) && is_string($validated['format'])
            ? $validated['format']
            : 'csv';
        $export = $handler->handle(new ExportPrescriptionsQuery($criteria, $format));

        return response()->json([
            'status' => 'prescription_export_created',
            'data' => $export->toArray(),
        ]);
    }

    public function list(Request $request, ListPrescriptionsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($prescription): array => $prescription->toArray(),
                $handler->handle(new ListPrescriptionsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function search(Request $request, SearchPrescriptionsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($prescription): array => $prescription->toArray(),
                $handler->handle(new SearchPrescriptionsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $prescriptionId, GetPrescriptionQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetPrescriptionQuery($prescriptionId))->toArray(),
        ]);
    }

    public function update(string $prescriptionId, Request $request, UpdatePrescriptionCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $prescription = $handler->handle(new UpdatePrescriptionCommand($prescriptionId, $validated));

        return response()->json([
            'status' => 'prescription_updated',
            'data' => $prescription->toArray(),
        ]);
    }

    private function criteria(Request $request, int $defaultLimit, int $maxLimit): PrescriptionSearchCriteria
    {
        $validated = $request->validate($this->listRules($maxLimit));
        /** @var array<string, mixed> $validated */
        $this->assertDateRange($validated, 'issued_from', 'issued_to', 'issued_at');
        $this->assertDateRange($validated, 'start_from', 'start_to', 'starts_on');
        $this->assertDateRange($validated, 'created_from', 'created_to', 'created_at');

        return new PrescriptionSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            status: $this->stringValue($validated, 'status'),
            patientId: $this->stringValue($validated, 'patient_id'),
            providerId: $this->stringValue($validated, 'provider_id'),
            encounterId: $this->stringValue($validated, 'encounter_id'),
            issuedFrom: $this->stringValue($validated, 'issued_from'),
            issuedTo: $this->stringValue($validated, 'issued_to'),
            startFrom: $this->stringValue($validated, 'start_from'),
            startTo: $this->stringValue($validated, 'start_to'),
            createdFrom: $this->stringValue($validated, 'created_from'),
            createdTo: $this->stringValue($validated, 'created_to'),
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : $defaultLimit,
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'provider_id' => ['required', 'uuid'],
            'encounter_id' => ['sometimes', 'nullable', 'uuid'],
            'treatment_item_id' => ['sometimes', 'nullable', 'uuid'],
            'medication_name' => ['required', 'string', 'max:255'],
            'medication_code' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dosage' => ['required', 'string', 'max:255'],
            'route' => ['required', 'string', 'max:120'],
            'frequency' => ['required', 'string', 'max:120'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'quantity_unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'authorized_refills' => ['required', 'integer', 'min:0', 'max:99'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'starts_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'ends_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
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
            'status' => ['nullable', 'string', 'in:'.implode(',', PrescriptionStatus::all())],
            'patient_id' => ['nullable', 'uuid'],
            'provider_id' => ['nullable', 'uuid'],
            'encounter_id' => ['nullable', 'uuid'],
            'issued_from' => ['nullable', 'date'],
            'issued_to' => ['nullable', 'date'],
            'start_from' => ['nullable', 'date_format:Y-m-d'],
            'start_to' => ['nullable', 'date_format:Y-m-d'],
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
            'encounter_id' => ['sometimes', 'nullable', 'uuid'],
            'treatment_item_id' => ['sometimes', 'nullable', 'uuid'],
            'medication_name' => ['sometimes', 'filled', 'string', 'max:255'],
            'medication_code' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dosage' => ['sometimes', 'filled', 'string', 'max:255'],
            'route' => ['sometimes', 'filled', 'string', 'max:120'],
            'frequency' => ['sometimes', 'filled', 'string', 'max:120'],
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            'quantity_unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'authorized_refills' => ['sometimes', 'integer', 'min:0', 'max:99'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'starts_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'ends_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
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
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }
}
