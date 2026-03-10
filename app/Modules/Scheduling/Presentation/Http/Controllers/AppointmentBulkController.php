<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Commands\BulkUpdateAppointmentsCommand;
use App\Modules\Scheduling\Application\Handlers\BulkUpdateAppointmentsCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AppointmentBulkController
{
    public function update(Request $request, BulkUpdateAppointmentsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $changes = $this->filterAllowedChanges($validated['changes'] ?? []);

        if ($changes === []) {
            throw ValidationException::withMessages([
                'changes' => ['At least one updatable field is required.'],
            ]);
        }

        $appointmentIdsInput = [];

        if (array_key_exists('appointment_ids', $validated) && is_array($validated['appointment_ids'])) {
            $appointmentIdsInput = $validated['appointment_ids'];
        }

        /** @var array<array-key, mixed> $appointmentIdsInput */
        /** @var list<string> $appointmentIds */
        $appointmentIds = array_values(array_filter(
            $appointmentIdsInput,
            static fn (mixed $appointmentId): bool => is_string($appointmentId),
        ));

        $result = $handler->handle(new BulkUpdateAppointmentsCommand($appointmentIds, $changes));

        return response()->json([
            'status' => 'appointments_bulk_updated',
            'data' => $result->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'appointment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'appointment_ids.*' => ['required', 'uuid', 'distinct'],
            'changes' => ['required', 'array'],
            'changes.patient_id' => ['sometimes', 'filled', 'uuid'],
            'changes.provider_id' => ['sometimes', 'filled', 'uuid'],
            'changes.clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'changes.room_id' => ['sometimes', 'nullable', 'uuid'],
            'changes.scheduled_start_at' => ['sometimes', 'filled', 'date'],
            'changes.scheduled_end_at' => ['sometimes', 'filled', 'date'],
            'changes.timezone' => ['sometimes', 'filled', 'timezone:all'],
        ];
    }

    /**
     * @return array{
     *     patient_id?: string,
     *     provider_id?: string,
     *     clinic_id?: ?string,
     *     room_id?: ?string,
     *     scheduled_start_at?: string,
     *     scheduled_end_at?: string,
     *     timezone?: string
     * }
     */
    private function filterAllowedChanges(mixed $changes): array
    {
        if (! is_array($changes)) {
            return [];
        }

        $normalized = [];

        if (array_key_exists('patient_id', $changes) && is_string($changes['patient_id'])) {
            $normalized['patient_id'] = $changes['patient_id'];
        }

        if (array_key_exists('provider_id', $changes) && is_string($changes['provider_id'])) {
            $normalized['provider_id'] = $changes['provider_id'];
        }

        if (array_key_exists('clinic_id', $changes) && (is_string($changes['clinic_id']) || $changes['clinic_id'] === null)) {
            $normalized['clinic_id'] = $changes['clinic_id'];
        }

        if (array_key_exists('room_id', $changes) && (is_string($changes['room_id']) || $changes['room_id'] === null)) {
            $normalized['room_id'] = $changes['room_id'];
        }

        if (array_key_exists('scheduled_start_at', $changes) && is_string($changes['scheduled_start_at'])) {
            $normalized['scheduled_start_at'] = $changes['scheduled_start_at'];
        }

        if (array_key_exists('scheduled_end_at', $changes) && is_string($changes['scheduled_end_at'])) {
            $normalized['scheduled_end_at'] = $changes['scheduled_end_at'];
        }

        if (array_key_exists('timezone', $changes) && is_string($changes['timezone'])) {
            $normalized['timezone'] = $changes['timezone'];
        }

        return $normalized;
    }
}
