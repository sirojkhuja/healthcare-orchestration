<?php

namespace App\Modules\Patient\Presentation\Http\Controllers;

use App\Modules\Patient\Application\Commands\CreatePatientCommand;
use App\Modules\Patient\Application\Commands\DeletePatientCommand;
use App\Modules\Patient\Application\Commands\UpdatePatientCommand;
use App\Modules\Patient\Application\Handlers\CreatePatientCommandHandler;
use App\Modules\Patient\Application\Handlers\DeletePatientCommandHandler;
use App\Modules\Patient\Application\Handlers\GetPatientQueryHandler;
use App\Modules\Patient\Application\Handlers\ListPatientsQueryHandler;
use App\Modules\Patient\Application\Handlers\UpdatePatientCommandHandler;
use App\Modules\Patient\Application\Queries\GetPatientQuery;
use App\Modules\Patient\Application\Queries\ListPatientsQuery;
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

    public function show(string $patientId, GetPatientQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetPatientQuery($patientId))->toArray(),
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
}
