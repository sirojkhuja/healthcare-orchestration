<?php

namespace App\Modules\Provider\Presentation\Http\Controllers;

use App\Modules\Provider\Application\Commands\UpdateProviderWorkHoursCommand;
use App\Modules\Provider\Application\Data\ProviderWorkHoursData;
use App\Modules\Provider\Application\Handlers\GetProviderWorkHoursQueryHandler;
use App\Modules\Provider\Application\Handlers\UpdateProviderWorkHoursCommandHandler;
use App\Modules\Provider\Application\Queries\GetProviderWorkHoursQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProviderWorkHoursController
{
    public function show(string $providerId, GetProviderWorkHoursQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetProviderWorkHoursQuery($providerId))->toArray(),
        ]);
    }

    public function update(
        string $providerId,
        Request $request,
        UpdateProviderWorkHoursCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'days' => ['required', 'array'],
        ]);
        /** @var array<string, mixed> $validated */
        $workHours = $handler->handle(new UpdateProviderWorkHoursCommand(
            providerId: $providerId,
            workHours: new ProviderWorkHoursData(
                providerId: $providerId,
                days: $this->daysPayload($validated),
            ),
        ));

        return response()->json([
            'status' => 'provider_work_hours_updated',
            'data' => $workHours->toArray(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     * @return array<string, list<array{start_time: string, end_time: string}>>
     */
    private function daysPayload(array $validated): array
    {
        $value = $validated['days'] ?? [];
        $days = [];

        if (! is_array($value)) {
            return $days;
        }

        foreach ($value as $day => $intervals) {
            if (! is_string($day) || ! is_array($intervals)) {
                continue;
            }

            $normalizedIntervals = [];

            foreach (array_values(array_filter($intervals, 'is_array')) as $interval) {
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
