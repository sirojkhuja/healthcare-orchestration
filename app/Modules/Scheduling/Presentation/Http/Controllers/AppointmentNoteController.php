<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Commands\AddAppointmentNoteCommand;
use App\Modules\Scheduling\Application\Commands\DeleteAppointmentNoteCommand;
use App\Modules\Scheduling\Application\Commands\UpdateAppointmentNoteCommand;
use App\Modules\Scheduling\Application\Handlers\AddAppointmentNoteCommandHandler;
use App\Modules\Scheduling\Application\Handlers\DeleteAppointmentNoteCommandHandler;
use App\Modules\Scheduling\Application\Handlers\ListAppointmentNotesQueryHandler;
use App\Modules\Scheduling\Application\Handlers\UpdateAppointmentNoteCommandHandler;
use App\Modules\Scheduling\Application\Queries\ListAppointmentNotesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AppointmentNoteController
{
    public function create(
        string $appointmentId,
        Request $request,
        AddAppointmentNoteCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $note = $handler->handle(new AddAppointmentNoteCommand($appointmentId, $validated));

        return response()->json([
            'status' => 'appointment_note_created',
            'data' => $note->toArray(),
        ], 201);
    }

    public function delete(
        string $appointmentId,
        string $noteId,
        DeleteAppointmentNoteCommandHandler $handler,
    ): JsonResponse {
        $note = $handler->handle(new DeleteAppointmentNoteCommand($appointmentId, $noteId));

        return response()->json([
            'status' => 'appointment_note_deleted',
            'data' => $note->toArray(),
        ]);
    }

    public function list(string $appointmentId, ListAppointmentNotesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($note): array => $note->toArray(),
                $handler->handle(new ListAppointmentNotesQuery($appointmentId)),
            ),
        ]);
    }

    public function update(
        string $appointmentId,
        string $noteId,
        Request $request,
        UpdateAppointmentNoteCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $note = $handler->handle(new UpdateAppointmentNoteCommand($appointmentId, $noteId, $validated));

        return response()->json([
            'status' => 'appointment_note_updated',
            'data' => $note->toArray(),
        ]);
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
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'body' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'body' => ['sometimes', 'filled', 'string', 'max:10000'],
        ];
    }
}
