<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Handlers\GetAppointmentAuditQueryHandler;
use App\Modules\Scheduling\Application\Queries\GetAppointmentAuditQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AppointmentAuditController
{
    public function list(string $appointmentId, Request $request, GetAppointmentAuditQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = array_key_exists('limit', $validated) && is_numeric($validated['limit'])
            ? (int) $validated['limit']
            : 50;

        return response()->json([
            'data' => array_map(
                static fn ($event): array => $event->toArray(),
                $handler->handle(new GetAppointmentAuditQuery($appointmentId, $limit)),
            ),
            'meta' => [
                'limit' => $limit,
            ],
        ]);
    }
}
