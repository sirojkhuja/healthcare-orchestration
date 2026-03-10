<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Commands\AddAppointmentParticipantCommand;
use App\Modules\Scheduling\Application\Commands\RemoveAppointmentParticipantCommand;
use App\Modules\Scheduling\Application\Handlers\AddAppointmentParticipantCommandHandler;
use App\Modules\Scheduling\Application\Handlers\ListAppointmentParticipantsQueryHandler;
use App\Modules\Scheduling\Application\Handlers\RemoveAppointmentParticipantCommandHandler;
use App\Modules\Scheduling\Application\Queries\ListAppointmentParticipantsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentParticipantController
{
    public function create(
        string $appointmentId,
        Request $request,
        AddAppointmentParticipantCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $participant = $handler->handle(new AddAppointmentParticipantCommand($appointmentId, $validated));

        return response()->json([
            'status' => 'appointment_participant_created',
            'data' => $participant->toArray(),
        ], 201);
    }

    public function delete(
        string $appointmentId,
        string $participantId,
        RemoveAppointmentParticipantCommandHandler $handler,
    ): JsonResponse {
        $participant = $handler->handle(new RemoveAppointmentParticipantCommand($appointmentId, $participantId));

        return response()->json([
            'status' => 'appointment_participant_deleted',
            'data' => $participant->toArray(),
        ]);
    }

    public function list(string $appointmentId, ListAppointmentParticipantsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($participant): array => $participant->toArray(),
                $handler->handle(new ListAppointmentParticipantsQuery($appointmentId)),
            ),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'participant_type' => ['required', 'string', 'in:user,provider,external'],
            'reference_id' => ['sometimes', 'nullable', 'uuid'],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'role' => ['required', 'string', 'max:120'],
            'required' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
