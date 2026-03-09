<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Commands\UpdateClinicSettingsCommand;
use App\Modules\TenantManagement\Application\Data\ClinicSettingsData;
use App\Modules\TenantManagement\Application\Handlers\GetClinicSettingsQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\UpdateClinicSettingsCommandHandler;
use App\Modules\TenantManagement\Application\Queries\GetClinicSettingsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClinicSettingsController
{
    public function show(string $clinicId, GetClinicSettingsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetClinicSettingsQuery($clinicId))->toArray(),
        ]);
    }

    public function update(string $clinicId, Request $request, UpdateClinicSettingsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'timezone' => ['nullable', 'timezone:all'],
            'default_appointment_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'slot_interval_minutes' => ['required', 'integer', 'min:5', 'max:240'],
            'allow_walk_ins' => ['required', 'boolean'],
            'require_appointment_confirmation' => ['required', 'boolean'],
            'telemedicine_enabled' => ['required', 'boolean'],
        ]);

        $settings = $handler->handle(new UpdateClinicSettingsCommand(
            clinicId: $clinicId,
            settings: new ClinicSettingsData(
                timezone: $this->nullableString($validated, 'timezone'),
                defaultAppointmentDurationMinutes: $this->intValue($validated, 'default_appointment_duration_minutes', 30),
                slotIntervalMinutes: $this->intValue($validated, 'slot_interval_minutes', 15),
                allowWalkIns: (bool) ($validated['allow_walk_ins'] ?? true),
                requireAppointmentConfirmation: (bool) ($validated['require_appointment_confirmation'] ?? false),
                telemedicineEnabled: (bool) ($validated['telemedicine_enabled'] ?? false),
            ),
        ));

        return response()->json([
            'status' => 'clinic_settings_updated',
            'data' => $settings->toArray(),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function intValue(array $validated, string $key, int $default): int
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
