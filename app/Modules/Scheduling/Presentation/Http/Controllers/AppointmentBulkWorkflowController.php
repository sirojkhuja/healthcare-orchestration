<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Commands\BulkCancelAppointmentsCommand;
use App\Modules\Scheduling\Application\Commands\BulkRescheduleAppointmentsCommand;
use App\Modules\Scheduling\Application\Handlers\BulkCancelAppointmentsCommandHandler;
use App\Modules\Scheduling\Application\Handlers\BulkRescheduleAppointmentsCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentBulkWorkflowController
{
    public function cancel(Request $request, BulkCancelAppointmentsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'appointment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'appointment_ids.*' => ['required', 'uuid', 'distinct'],
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $reason = $validated['reason'] ?? null;

        if (! is_string($reason)) {
            abort(422, 'The reason field is required.');
        }

        $result = $handler->handle(new BulkCancelAppointmentsCommand(
            appointmentIds: $this->normalizedAppointmentIds($validated['appointment_ids'] ?? null),
            reason: trim($reason),
        ));

        return response()->json([
            'status' => 'appointments_bulk_canceled',
            'data' => $result->toArray(),
        ]);
    }

    public function reschedule(Request $request, BulkRescheduleAppointmentsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.appointment_id' => ['required', 'uuid', 'distinct'],
            'items.*.reason' => ['required', 'string', 'max:5000'],
            'items.*.replacement_start_at' => ['required', 'date'],
            'items.*.replacement_end_at' => ['required', 'date'],
            'items.*.timezone' => ['required', 'timezone:all'],
            'items.*.clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'items.*.room_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        /** @var array<string, mixed> $validated */
        $items = $this->normalizedItems($validated['items'] ?? null);

        return response()->json([
            'status' => 'appointments_bulk_rescheduled',
            'data' => $handler->handle(new BulkRescheduleAppointmentsCommand($items))->toArray(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedItems(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            /** @var array<string, mixed> $item */
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function normalizedAppointmentIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        /** @var list<string> $appointmentIds */
        $appointmentIds = [];

        foreach (array_keys($value) as $index) {
            /** @var mixed $appointmentId */
            $appointmentId = $value[$index];

            if (is_string($appointmentId)) {
                $appointmentIds[] = $appointmentId;
            }
        }

        return $appointmentIds;
    }
}
