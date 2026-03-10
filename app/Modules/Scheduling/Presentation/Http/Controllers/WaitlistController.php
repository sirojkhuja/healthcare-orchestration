<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Commands\AddToWaitlistCommand;
use App\Modules\Scheduling\Application\Commands\OfferWaitlistSlotCommand;
use App\Modules\Scheduling\Application\Commands\RemoveFromWaitlistCommand;
use App\Modules\Scheduling\Application\Handlers\AddToWaitlistCommandHandler;
use App\Modules\Scheduling\Application\Handlers\ListWaitlistQueryHandler;
use App\Modules\Scheduling\Application\Handlers\OfferWaitlistSlotCommandHandler;
use App\Modules\Scheduling\Application\Handlers\RemoveFromWaitlistCommandHandler;
use App\Modules\Scheduling\Application\Queries\ListWaitlistQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WaitlistController
{
    public function create(Request $request, AddToWaitlistCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'uuid'],
            'provider_id' => ['required', 'uuid'],
            'clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'room_id' => ['sometimes', 'nullable', 'uuid'],
            'desired_date_from' => ['required', 'date_format:Y-m-d'],
            'desired_date_to' => ['required', 'date_format:Y-m-d'],
            'preferred_start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'preferred_end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $entry = $handler->handle(new AddToWaitlistCommand($validated));

        return response()->json([
            'status' => 'waitlist_entry_created',
            'data' => $entry->toArray(),
        ], 201);
    }

    public function delete(string $entryId, RemoveFromWaitlistCommandHandler $handler): JsonResponse
    {
        $entry = $handler->handle(new RemoveFromWaitlistCommand($entryId));

        return response()->json([
            'status' => 'waitlist_entry_removed',
            'data' => $entry->toArray(),
        ]);
    }

    public function list(Request $request, ListWaitlistQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'in:open,booked,removed'],
            'patient_id' => ['sometimes', 'nullable', 'uuid'],
            'provider_id' => ['sometimes', 'nullable', 'uuid'],
            'clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'desired_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'desired_to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'data' => array_map(
                static fn ($entry): array => $entry->toArray(),
                $handler->handle(new ListWaitlistQuery($validated)),
            ),
            'meta' => [
                'filters' => $validated,
            ],
        ]);
    }

    public function offer(
        string $entryId,
        Request $request,
        OfferWaitlistSlotCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'scheduled_start_at' => ['required', 'date'],
            'scheduled_end_at' => ['required', 'date', 'after:scheduled_start_at'],
            'timezone' => ['required', 'timezone:all'],
            'clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'room_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        /** @var array<string, mixed> $validated */
        $offer = $handler->handle(new OfferWaitlistSlotCommand($entryId, $validated));

        return response()->json([
            'status' => 'waitlist_slot_offered',
            'data' => $offer->toArray(),
        ]);
    }
}
