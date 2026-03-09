<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Handlers\ExportProviderCalendarQueryHandler;
use App\Modules\Scheduling\Application\Handlers\GetProviderCalendarQueryHandler;
use App\Modules\Scheduling\Application\Queries\ExportProviderCalendarQuery;
use App\Modules\Scheduling\Application\Queries\GetProviderCalendarQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProviderCalendarController
{
    public function export(
        string $providerId,
        Request $request,
        ExportProviderCalendarQueryHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'format' => ['nullable', 'string', 'in:csv'],
        ]);
        /** @var array{date_from: string, date_to: string, limit?: int, format?: string} $validated */
        $export = $handler->handle(new ExportProviderCalendarQuery(
            providerId: $providerId,
            dateFrom: $validated['date_from'],
            dateTo: $validated['date_to'],
            limit: $validated['limit'] ?? null,
            format: $validated['format'] ?? 'csv',
        ));

        return response()->json([
            'status' => 'provider_calendar_export_created',
            'data' => $export->toArray(),
        ]);
    }

    public function show(
        string $providerId,
        Request $request,
        GetProviderCalendarQueryHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);
        /** @var array{date_from: string, date_to: string, limit?: int} $validated */
        $calendar = $handler->handle(new GetProviderCalendarQuery(
            providerId: $providerId,
            dateFrom: $validated['date_from'],
            dateTo: $validated['date_to'],
            limit: $validated['limit'] ?? null,
        ));

        return response()->json([
            'data' => $calendar->toArray(),
        ]);
    }
}
