<?php

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Scheduling\Application\Contracts\AppointmentRecurrenceRepository;
use App\Modules\Scheduling\Application\Data\AppointmentRecurrenceData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseAppointmentRecurrenceRepository implements AppointmentRecurrenceRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): AppointmentRecurrenceData
    {
        $recurrenceId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('appointment_recurrences')->insert([
            'id' => $recurrenceId,
            'tenant_id' => $tenantId,
            'source_appointment_id' => $attributes['source_appointment_id'],
            'patient_id' => $attributes['patient_id'],
            'provider_id' => $attributes['provider_id'],
            'clinic_id' => $attributes['clinic_id'],
            'room_id' => $attributes['room_id'],
            'frequency' => $attributes['frequency'],
            'interval' => $attributes['interval'],
            'occurrence_count' => $attributes['occurrence_count'],
            'until_date' => $attributes['until_date'],
            'timezone' => $attributes['timezone'],
            'status' => $attributes['status'],
            'canceled_reason' => $attributes['canceled_reason'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $recurrenceId)
            ?? throw new \LogicException('Created appointment recurrence could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $recurrenceId): ?AppointmentRecurrenceData
    {
        $row = DB::table('appointment_recurrences')
            ->where('tenant_id', $tenantId)
            ->where('id', $recurrenceId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function update(string $tenantId, string $recurrenceId, array $updates): ?AppointmentRecurrenceData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $recurrenceId);
        }

        $updates['updated_at'] = CarbonImmutable::now();

        $updated = DB::table('appointment_recurrences')
            ->where('tenant_id', $tenantId)
            ->where('id', $recurrenceId)
            ->update($updates);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $recurrenceId);
        }

        return $this->findInTenant($tenantId, $recurrenceId);
    }

    private function toData(stdClass $row): AppointmentRecurrenceData
    {
        return new AppointmentRecurrenceData(
            recurrenceId: $this->stringValue($row->id ?? null),
            sourceAppointmentId: $this->stringValue($row->source_appointment_id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            providerId: $this->stringValue($row->provider_id ?? null),
            clinicId: $this->nullableString($row->clinic_id ?? null),
            roomId: $this->nullableString($row->room_id ?? null),
            frequency: $this->stringValue($row->frequency ?? null),
            interval: $this->intValue($row->interval ?? null),
            occurrenceCount: $this->nullableInt($row->occurrence_count ?? null),
            untilDate: $this->nullableDate($row->until_date ?? null),
            timezone: $this->stringValue($row->timezone ?? null),
            status: $this->stringValue($row->status ?? null),
            canceledReason: $this->nullableString($row->canceled_reason ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($this->stringValue($value));
    }

    private function nullableDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return $this->dateTime($value)->startOfDay();
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
