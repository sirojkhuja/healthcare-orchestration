<?php

namespace App\Modules\Patient\Presentation\Http\Controllers;

use App\Modules\Patient\Application\Commands\CreatePatientCommand;
use App\Modules\Patient\Application\Commands\DeletePatientCommand;
use App\Modules\Patient\Application\Commands\UpdatePatientCommand;
use App\Modules\Patient\Application\Data\PatientSearchCriteria;
use App\Modules\Patient\Application\Handlers\CreatePatientCommandHandler;
use App\Modules\Patient\Application\Handlers\DeletePatientCommandHandler;
use App\Modules\Patient\Application\Handlers\ExportPatientsQueryHandler;
use App\Modules\Patient\Application\Handlers\GetPatientQueryHandler;
use App\Modules\Patient\Application\Handlers\GetPatientSummaryQueryHandler;
use App\Modules\Patient\Application\Handlers\GetPatientTimelineQueryHandler;
use App\Modules\Patient\Application\Handlers\ListPatientsQueryHandler;
use App\Modules\Patient\Application\Handlers\SearchPatientsQueryHandler;
use App\Modules\Patient\Application\Handlers\UpdatePatientCommandHandler;
use App\Modules\Patient\Application\Queries\ExportPatientsQuery;
use App\Modules\Patient\Application\Queries\GetPatientQuery;
use App\Modules\Patient\Application\Queries\GetPatientSummaryQuery;
use App\Modules\Patient\Application\Queries\GetPatientTimelineQuery;
use App\Modules\Patient\Application\Queries\ListPatientsQuery;
use App\Modules\Patient\Application\Queries\SearchPatientsQuery;
use App\Modules\Patient\Domain\Patients\PatientSex;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PatientController
{
    public function create(Request $request, CreatePatientCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $patient = $handler->handle(new CreatePatientCommand($validated));

        return response()->json([
            'status' => 'patient_created',
            'data' => $patient->toArray(),
        ], 201);
    }

    public function delete(string $patientId, DeletePatientCommandHandler $handler): JsonResponse
    {
        $patient = $handler->handle(new DeletePatientCommand($patientId));

        return response()->json([
            'status' => 'patient_deleted',
            'data' => $patient->toArray(),
        ]);
    }

    public function list(ListPatientsQueryHandler $handler): JsonResponse
    {
        $items = [];

        foreach ($handler->handle(new ListPatientsQuery) as $patient) {
            $items[] = $patient->toArray();
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function search(Request $request, SearchPatientsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->searchCriteria($request, defaultLimit: 25, maxLimit: 100);

        return response()->json([
            'data' => array_map(
                static fn ($patient): array => $patient->toArray(),
                $handler->handle(new SearchPatientsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $patientId, GetPatientQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetPatientQuery($patientId))->toArray(),
        ]);
    }

    public function summary(string $patientId, GetPatientSummaryQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetPatientSummaryQuery($patientId))->toArray(),
        ]);
    }

    public function timeline(string $patientId, Request $request, GetPatientTimelineQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        $limit = $this->integerValue($validated, 'limit', 50);

        return response()->json([
            'data' => array_map(
                static fn ($event): array => $event->toArray(),
                $handler->handle(new GetPatientTimelineQuery($patientId, $limit)),
            ),
            'meta' => [
                'limit' => $limit,
            ],
        ]);
    }

    public function update(string $patientId, Request $request, UpdatePatientCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $patient = $handler->handle(new UpdatePatientCommand($patientId, $validated));

        return response()->json([
            'status' => 'patient_updated',
            'data' => $patient->toArray(),
        ]);
    }

    public function export(Request $request, ExportPatientsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            ...$this->searchRules(1000),
            'format' => ['nullable', 'string', 'in:csv'],
        ]);
        /** @var array<string, mixed> $validated */
        $criteria = $this->searchCriteriaFromValidated($validated, defaultLimit: 1000);
        $format = array_key_exists('format', $validated) && is_string($validated['format']) && $validated['format'] !== ''
            ? $validated['format']
            : 'csv';

        return response()->json([
            'status' => 'patient_export_created',
            'data' => $handler->handle(new ExportPatientsQuery($criteria, $format))->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'preferred_name' => ['nullable', 'string', 'max:120'],
            'sex' => ['required', 'string', 'in:'.implode(',', PatientSex::all())],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'city_code' => ['nullable', 'string', 'max:64'],
            'district_code' => ['nullable', 'string', 'max:64'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'first_name' => ['sometimes', 'filled', 'string', 'max:120'],
            'last_name' => ['sometimes', 'filled', 'string', 'max:120'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferred_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sex' => ['sometimes', 'filled', 'string', 'in:'.implode(',', PatientSex::all())],
            'birth_date' => ['sometimes', 'filled', 'date_format:Y-m-d', 'before_or_equal:today'],
            'national_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'email' => ['sometimes', 'nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'city_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'district_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'address_line_1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function searchRules(int $maxLimit): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'sex' => ['nullable', 'string', 'in:'.implode(',', PatientSex::all())],
            'city_code' => ['nullable', 'string', 'max:64'],
            'district_code' => ['nullable', 'string', 'max:64'],
            'birth_date_from' => ['nullable', 'date_format:Y-m-d'],
            'birth_date_to' => ['nullable', 'date_format:Y-m-d'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'has_email' => ['nullable', 'string', 'in:true,false,1,0'],
            'has_phone' => ['nullable', 'string', 'in:true,false,1,0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
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

    private function searchCriteria(Request $request, int $defaultLimit, int $maxLimit): PatientSearchCriteria
    {
        $validated = $request->validate($this->searchRules($maxLimit));
        /** @var array<string, mixed> $validated */

        return $this->searchCriteriaFromValidated($validated, $defaultLimit);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function searchCriteriaFromValidated(array $validated, int $defaultLimit): PatientSearchCriteria
    {
        $this->assertDateRange($validated, 'birth_date_from', 'birth_date_to', 'birth_date');
        $this->assertDateRange($validated, 'created_from', 'created_to', 'created_at');

        return new PatientSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            sex: $this->stringValue($validated, 'sex'),
            cityCode: $this->stringValue($validated, 'city_code'),
            districtCode: $this->stringValue($validated, 'district_code'),
            birthDateFrom: $this->stringValue($validated, 'birth_date_from'),
            birthDateTo: $this->stringValue($validated, 'birth_date_to'),
            createdFrom: $this->stringValue($validated, 'created_from'),
            createdTo: $this->stringValue($validated, 'created_to'),
            hasEmail: $this->booleanValue($validated, 'has_email'),
            hasPhone: $this->booleanValue($validated, 'has_phone'),
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

        if ($from === null || $to === null || $from <= $to) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => ['The from value must be earlier than or equal to the to value.'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function booleanValue(array $validated, string $key): ?bool
    {
        if (! array_key_exists($key, $validated)) {
            return null;
        }

        return filter_var($validated[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
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
        if (! array_key_exists($key, $validated) || ! is_string($validated[$key])) {
            return null;
        }

        $value = trim($validated[$key]);

        return $value !== '' ? $value : null;
    }
}
