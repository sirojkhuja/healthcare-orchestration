<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\CreateClinicHolidayCommand;
use App\Modules\TenantManagement\Application\Commands\DeleteClinicHolidayCommand;
use App\Modules\TenantManagement\Application\Handlers\CreateClinicHolidayCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\DeleteClinicHolidayCommandHandler;
use App\Modules\TenantManagement\Application\Handlers\ListClinicHolidaysQueryHandler;
use App\Modules\TenantManagement\Application\Queries\ListClinicHolidaysQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClinicHolidayController
{
    public function create(string $clinicId, Request $request, CreateClinicHolidayCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_closed' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */
        $holiday = $handler->handle(new CreateClinicHolidayCommand($clinicId, $validated));

        return response()->json([
            'status' => 'clinic_holiday_created',
            'data' => $holiday->toArray(),
        ], 201);
    }

    public function delete(string $clinicId, string $holidayId, DeleteClinicHolidayCommandHandler $handler): JsonResponse
    {
        $holiday = $handler->handle(new DeleteClinicHolidayCommand($clinicId, $holidayId));

        return response()->json([
            'status' => 'clinic_holiday_deleted',
            'data' => $holiday->toArray(),
        ]);
    }

    public function list(string $clinicId, ListClinicHolidaysQueryHandler $handler): JsonResponse
    {
        $items = [];

        foreach ($handler->handle(new ListClinicHolidaysQuery($clinicId)) as $holiday) {
            $items[] = $holiday->toArray();
        }

        return response()->json([
            'data' => $items,
        ]);
    }
}
