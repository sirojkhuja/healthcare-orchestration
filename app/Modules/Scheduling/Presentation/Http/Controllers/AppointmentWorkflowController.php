<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Commands\CancelAppointmentCommand;
use App\Modules\Scheduling\Application\Commands\CheckInAppointmentCommand;
use App\Modules\Scheduling\Application\Commands\CompleteAppointmentCommand;
use App\Modules\Scheduling\Application\Commands\ConfirmAppointmentCommand;
use App\Modules\Scheduling\Application\Commands\MarkNoShowCommand;
use App\Modules\Scheduling\Application\Commands\RescheduleAppointmentCommand;
use App\Modules\Scheduling\Application\Commands\RestoreAppointmentCommand;
use App\Modules\Scheduling\Application\Commands\ScheduleAppointmentCommand;
use App\Modules\Scheduling\Application\Commands\StartAppointmentCommand;
use App\Modules\Scheduling\Application\Handlers\CancelAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Handlers\CheckInAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Handlers\CompleteAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Handlers\ConfirmAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Handlers\MarkNoShowCommandHandler;
use App\Modules\Scheduling\Application\Handlers\RescheduleAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Handlers\RestoreAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Handlers\ScheduleAppointmentCommandHandler;
use App\Modules\Scheduling\Application\Handlers\StartAppointmentCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentWorkflowController
{
    public function cancel(string $appointmentId, Request $request, CancelAppointmentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string} $validated */
        $appointment = $handler->handle(new CancelAppointmentCommand($appointmentId, trim($validated['reason'])));

        return response()->json([
            'status' => 'appointment_canceled',
            'data' => $appointment->toArray(),
        ]);
    }

    public function checkIn(string $appointmentId, Request $request, CheckInAppointmentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'admin_override' => ['sometimes', 'boolean'],
        ]);
        /** @var array<string, mixed> $validated */
        $appointment = $handler->handle(new CheckInAppointmentCommand(
            appointmentId: $appointmentId,
            adminOverride: (bool) ($validated['admin_override'] ?? false),
        ));

        return response()->json([
            'status' => 'appointment_checked_in',
            'data' => $appointment->toArray(),
        ]);
    }

    public function complete(string $appointmentId, CompleteAppointmentCommandHandler $handler): JsonResponse
    {
        $appointment = $handler->handle(new CompleteAppointmentCommand($appointmentId));

        return response()->json([
            'status' => 'appointment_completed',
            'data' => $appointment->toArray(),
        ]);
    }

    public function confirm(string $appointmentId, ConfirmAppointmentCommandHandler $handler): JsonResponse
    {
        $appointment = $handler->handle(new ConfirmAppointmentCommand($appointmentId));

        return response()->json([
            'status' => 'appointment_confirmed',
            'data' => $appointment->toArray(),
        ]);
    }

    public function noShow(string $appointmentId, Request $request, MarkNoShowCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string} $validated */
        $appointment = $handler->handle(new MarkNoShowCommand($appointmentId, trim($validated['reason'])));

        return response()->json([
            'status' => 'appointment_no_show',
            'data' => $appointment->toArray(),
        ]);
    }

    public function reschedule(string $appointmentId, Request $request, RescheduleAppointmentCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
            'replacement_start_at' => ['required', 'date'],
            'replacement_end_at' => ['required', 'date', 'after:replacement_start_at'],
            'timezone' => ['required', 'timezone:all'],
            'clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'room_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        /** @var array<string, mixed> $validated */
        $result = $handler->handle(new RescheduleAppointmentCommand($appointmentId, $validated));

        return response()->json([
            'status' => 'appointment_rescheduled',
            'data' => $result->toArray(),
        ]);
    }

    public function restore(string $appointmentId, RestoreAppointmentCommandHandler $handler): JsonResponse
    {
        $appointment = $handler->handle(new RestoreAppointmentCommand($appointmentId));

        return response()->json([
            'status' => 'appointment_restored',
            'data' => $appointment->toArray(),
        ]);
    }

    public function schedule(string $appointmentId, ScheduleAppointmentCommandHandler $handler): JsonResponse
    {
        $appointment = $handler->handle(new ScheduleAppointmentCommand($appointmentId));

        return response()->json([
            'status' => 'appointment_scheduled',
            'data' => $appointment->toArray(),
        ]);
    }

    public function start(string $appointmentId, StartAppointmentCommandHandler $handler): JsonResponse
    {
        $appointment = $handler->handle(new StartAppointmentCommand($appointmentId));

        return response()->json([
            'status' => 'appointment_started',
            'data' => $appointment->toArray(),
        ]);
    }
}
