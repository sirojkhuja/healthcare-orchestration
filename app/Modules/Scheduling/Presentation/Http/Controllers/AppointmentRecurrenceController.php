<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Commands\CancelRecurrenceCommand;
use App\Modules\Scheduling\Application\Commands\MakeAppointmentRecurringCommand;
use App\Modules\Scheduling\Application\Handlers\CancelRecurrenceCommandHandler;
use App\Modules\Scheduling\Application\Handlers\MakeAppointmentRecurringCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentRecurrenceController
{
    public function cancel(
        string $recurrenceId,
        Request $request,
        CancelRecurrenceCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string} $validated */
        $recurrence = $handler->handle(new CancelRecurrenceCommand($recurrenceId, trim($validated['reason'])));

        return response()->json([
            'status' => 'appointment_recurrence_canceled',
            'data' => $recurrence->toArray(),
        ]);
    }

    public function create(
        string $appointmentId,
        Request $request,
        MakeAppointmentRecurringCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly'],
            'interval' => ['required', 'integer', 'min:1', 'max:12'],
            'occurrence_count' => ['sometimes', 'nullable', 'integer', 'min:2', 'max:24'],
            'until_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        /** @var array<string, mixed> $validated */
        $result = $handler->handle(new MakeAppointmentRecurringCommand($appointmentId, $validated));

        return response()->json([
            'status' => 'appointment_recurrence_created',
            'data' => $result->toArray(),
        ], 201);
    }
}
