<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Commands\CreateAppointmentCommand;
use App\Modules\Scheduling\Application\Commands\DeleteAppointmentCommand;
use App\Modules\Scheduling\Application\Commands\UpdateAppointmentCommand;
use App\Modules\Scheduling\Application\Data\AppointmentSearchCriteria;
use App\Modules\Scheduling\Application\Handlers\CreateAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Handlers\DeleteAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Handlers\ExportAppointmentsQueryHandler;
use App\Modules\Scheduling\Application\Handlers\GetAppointmentQueryHandler;
use App\Modules\Scheduling\Application\Handlers\ListAppointmentsQueryHandler;
use App\Modules\Scheduling\Application\Handlers\SearchAppointmentsQueryHandler;
use App\Modules\Scheduling\Application\Handlers\UpdateAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Queries\ExportAppointmentsQuery;
use App\Modules\Scheduling\Application\Queries\GetAppointmentQuery;
use App\Modules\Scheduling\Application\Queries\ListAppointmentsQuery;
use App\Modules\Scheduling\Application\Queries\SearchAppointmentsQuery;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AppointmentController
{
    public function create(Request $request, CreateAppointmentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $appointment = $handler->handle(new CreateAppointmentCommand($validated));

        return response()->json([
            'status' => 'appointment_created',
            'data' => $appointment->toArray(),
        ], 201);
    }

    public function delete(string $appointmentId, DeleteAppointmentCommandHandler $handler): JsonResponse
    {
        $appointment = $handler->handle(new DeleteAppointmentCommand($appointmentId));

        return response()->json([
            'status' => 'appointment_deleted',
            'data' => $appointment->toArray(),
        ]);
    }

    public function export(Request $request, ExportAppointmentsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            ...$this->searchRules(1000),
            'format' => ['nullable', 'string', 'in:csv'],
        ]);
        /** @var array<string, mixed> $validated */
        $criteria = $this->searchCriteriaFromValidated($validated, 1000);
        $format = array_key_exists('format', $validated) && is_string($validated['format']) && $validated['format'] !== ''
            ? $validated['format']
            : 'csv';

        return response()->json([
            'status' => 'appointment_export_created',
            'data' => $handler->handle(new ExportAppointmentsQuery($criteria, $format))->toArray(),
        ]);
    }

    public function list(ListAppointmentsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($appointment): array => $appointment->toArray(),
                $handler->handle(new ListAppointmentsQuery),
            ),
        ]);
    }

    public function search(Request $request, SearchAppointmentsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->searchCriteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($appointment): array => $appointment->toArray(),
                $handler->handle(new SearchAppointmentsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $appointmentId, GetAppointmentQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetAppointmentQuery($appointmentId))->toArray(),
        ]);
    }

    public function update(string $appointmentId, Request $request, UpdateAppointmentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $appointment = $handler->handle(new UpdateAppointmentCommand($appointmentId, $validated));

        return response()->json([
            'status' => 'appointment_updated',
            'data' => $appointment->toArray(),
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
            'clinic_id' => ['nullable', 'uuid'],
            'room_id' => ['nullable', 'uuid'],
            'scheduled_start_at' => ['required', 'date'],
            'scheduled_end_at' => ['required', 'date', 'after:scheduled_start_at'],
            'timezone' => ['required', 'timezone:all'],
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
            'clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'room_id' => ['sometimes', 'nullable', 'uuid'],
            'scheduled_start_at' => ['sometimes', 'filled', 'date'],
            'scheduled_end_at' => ['sometimes', 'filled', 'date'],
            'timezone' => ['sometimes', 'filled', 'timezone:all'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function searchRules(int $maxLimit): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:'.implode(',', AppointmentStatus::all())],
            'patient_id' => ['nullable', 'uuid'],
            'provider_id' => ['nullable', 'uuid'],
            'clinic_id' => ['nullable', 'uuid'],
            'room_id' => ['nullable', 'uuid'],
            'scheduled_from' => ['nullable', 'date_format:Y-m-d'],
            'scheduled_to' => ['nullable', 'date_format:Y-m-d'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
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

    private function searchCriteria(Request $request, int $defaultLimit, int $maxLimit): AppointmentSearchCriteria
    {
        $validated = $request->validate($this->searchRules($maxLimit));
        /** @var array<string, mixed> $validated */

        return $this->searchCriteriaFromValidated($validated, $defaultLimit);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function searchCriteriaFromValidated(array $validated, int $defaultLimit): AppointmentSearchCriteria
    {
        $this->assertDateRange($validated, 'scheduled_from', 'scheduled_to', 'scheduled_at');
        $this->assertDateRange($validated, 'created_from', 'created_to', 'created_at');

        return new AppointmentSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            status: $this->stringValue($validated, 'status'),
            patientId: $this->stringValue($validated, 'patient_id'),
            providerId: $this->stringValue($validated, 'provider_id'),
            clinicId: $this->stringValue($validated, 'clinic_id'),
            roomId: $this->stringValue($validated, 'room_id'),
            scheduledFrom: $this->stringValue($validated, 'scheduled_from'),
            scheduledTo: $this->stringValue($validated, 'scheduled_to'),
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

        if ($from !== null && $to !== null && $from > $to) {
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
