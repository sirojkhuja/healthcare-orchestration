<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\UpdateClinicWorkHoursCommand;
use App\Modules\TenantManagement\Application\Data\ClinicWorkHoursData;
use App\Modules\TenantManagement\Application\Handlers\GetClinicWorkHoursQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\UpdateClinicWorkHoursCommandHandler;
use App\Modules\TenantManagement\Application\Queries\GetClinicWorkHoursQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClinicWorkHoursController
{
    public function show(string $clinicId, GetClinicWorkHoursQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetClinicWorkHoursQuery($clinicId))->toArray(),
        ]);
    }

    public function update(string $clinicId, Request $request, UpdateClinicWorkHoursCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $workHours = $handler->handle(new UpdateClinicWorkHoursCommand(
            clinicId: $clinicId,
            workHours: new ClinicWorkHoursData(
                clinicId: $clinicId,
                days: $this->daysPayload($validated),
            ),
        ));

        return response()->json([
            'status' => 'clinic_work_hours_updated',
            'data' => $workHours->toArray(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     * @return array<string, list<array{start_time: string, end_time: string}>>
     */
    private function daysPayload(array $validated): array
    {
        /** @var mixed $value */
        $value = $validated['days'] ?? [];
        $days = [];

        if (! is_array($value)) {
            return $days;
        }

        foreach ($value as $day => $intervals) {
            if (! is_string($day) || ! is_array($intervals)) {
                continue;
            }

            /** @var list<mixed> $intervals */
            /** @var list<array{start_time: string, end_time: string}> $normalizedIntervals */
            $normalizedIntervals = [];
            /** @var list<array<array-key, mixed>> $intervalValues */
            $intervalValues = array_values(array_filter($intervals, 'is_array'));

            foreach ($intervalValues as $interval) {

                $startTime = $interval['start_time'] ?? null;
                $endTime = $interval['end_time'] ?? null;

                if (! is_string($startTime) || ! is_string($endTime)) {
                    continue;
                }

                $normalizedIntervals[] = [
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ];
            }

            $days[$day] = $normalizedIntervals;
        }

        return $days;
    }
}
