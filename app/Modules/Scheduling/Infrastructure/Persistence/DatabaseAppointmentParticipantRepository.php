<?php

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Scheduling\Application\Contracts\AppointmentParticipantRepository;
use App\Modules\Scheduling\Application\Data\AppointmentParticipantData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseAppointmentParticipantRepository implements AppointmentParticipantRepository
{
    #[\Override]
    public function create(string $tenantId, string $appointmentId, array $attributes): AppointmentParticipantData
    {
        $participantId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('appointment_participants')->insert([
            'id' => $participantId,
            'tenant_id' => $tenantId,
            'appointment_id' => $appointmentId,
            'participant_type' => $attributes['participant_type'],
            'reference_id' => $attributes['reference_id'],
            'display_name' => $attributes['display_name'],
            'role' => $attributes['role'],
            'required' => $attributes['required'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $appointmentId, $participantId)
            ?? throw new \LogicException('Created appointment participant could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $appointmentId, string $participantId): bool
    {
        return DB::table('appointment_participants')
            ->where('tenant_id', $tenantId)
            ->where('appointment_id', $appointmentId)
            ->where('id', $participantId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $appointmentId, string $participantId): ?AppointmentParticipantData
    {
        $row = DB::table('appointment_participants')
            ->where('tenant_id', $tenantId)
            ->where('appointment_id', $appointmentId)
            ->where('id', $participantId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForAppointment(string $tenantId, string $appointmentId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('appointment_participants')
            ->where('tenant_id', $tenantId)
            ->where('appointment_id', $appointmentId)
            ->orderByDesc('required')
            ->orderBy('display_name')
            ->orderBy('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    private function toData(stdClass $row): AppointmentParticipantData
    {
        return new AppointmentParticipantData(
            participantId: $this->stringValue($row->id ?? null),
            appointmentId: $this->stringValue($row->appointment_id ?? null),
            participantType: $this->stringValue($row->participant_type ?? null),
            referenceId: $this->nullableString($row->reference_id ?? null),
            displayName: $this->stringValue($row->display_name ?? null),
            role: $this->stringValue($row->role ?? null),
            required: (bool) ($row->required ?? false),
            notes: $this->nullableString($row->notes ?? null),
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

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
