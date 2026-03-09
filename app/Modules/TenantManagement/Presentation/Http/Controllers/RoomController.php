<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\CreateRoomCommand;
use App\Modules\TenantManagement\Application\Commands\DeleteRoomCommand;
use App\Modules\TenantManagement\Application\Commands\UpdateRoomCommand;
use App\Modules\TenantManagement\Application\Handlers\CreateRoomCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\DeleteRoomCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\ListRoomsQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\UpdateRoomCommandHandler;
use App\Modules\TenantManagement\Application\Queries\ListRoomsQuery;
use App\Modules\TenantManagement\Domain\Clinics\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class RoomController
{
    public function create(string $clinicId, Request $request, CreateRoomCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['nullable', 'uuid'],
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string', 'in:'.implode(',', RoomType::all())],
            'floor' => ['nullable', 'string', 'max:32'],
            'capacity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $room = $handler->handle(new CreateRoomCommand($clinicId, $validated));

        return response()->json([
            'status' => 'room_created',
            'data' => $room->toArray(),
        ], 201);
    }

    public function delete(string $clinicId, string $roomId, DeleteRoomCommandHandler $handler): JsonResponse
    {
        $room = $handler->handle(new DeleteRoomCommand($clinicId, $roomId));

        return response()->json([
            'status' => 'room_deleted',
            'data' => $room->toArray(),
        ]);
    }

    public function list(string $clinicId, ListRoomsQueryHandler $handler): JsonResponse
    {
        $items = [];

        foreach ($handler->handle(new ListRoomsQuery($clinicId)) as $room) {
            $items[] = $room->toArray();
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function update(string $clinicId, string $roomId, Request $request, UpdateRoomCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['sometimes', 'nullable', 'uuid'],
            'code' => ['sometimes', 'filled', 'string', 'max:32'],
            'name' => ['sometimes', 'filled', 'string', 'max:160'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', RoomType::all())],
            'floor' => ['sometimes', 'nullable', 'string', 'max:32'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);

        $room = $handler->handle(new UpdateRoomCommand($clinicId, $roomId, $validated));

        return response()->json([
            'status' => 'room_updated',
            'data' => $room->toArray(),
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
}
