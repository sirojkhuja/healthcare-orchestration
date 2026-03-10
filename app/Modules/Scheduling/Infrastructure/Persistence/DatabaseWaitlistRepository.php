<?php

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Scheduling\Application\Contracts\WaitlistRepository;
use App\Modules\Scheduling\Application\Data\WaitlistEntryData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseWaitlistRepository implements WaitlistRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): WaitlistEntryData
    {
        $entryId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('appointment_waitlist_entries')->insert([
            'id' => $entryId,
            'tenant_id' => $tenantId,
            'patient_id' => $attributes['patient_id'],
            'provider_id' => $attributes['provider_id'],
            'clinic_id' => $attributes['clinic_id'],
            'room_id' => $attributes['room_id'],
            'desired_date_from' => $attributes['desired_date_from'],
            'desired_date_to' => $attributes['desired_date_to'],
            'preferred_start_time' => $attributes['preferred_start_time'],
            'preferred_end_time' => $attributes['preferred_end_time'],
            'notes' => $attributes['notes'],
            'status' => $attributes['status'],
            'booked_appointment_id' => $attributes['booked_appointment_id'],
            'offered_slot' => $this->jsonValue($attributes['offered_slot']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $entryId)
            ?? throw new \LogicException('Created waitlist entry could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $entryId): ?WaitlistEntryData
    {
        $row = $this->baseQuery($tenantId)
            ->where('appointment_waitlist_entries.id', $entryId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId, array $filters = []): array
    {
        $query = $this->baseQuery($tenantId);

        foreach ([
            'status' => 'appointment_waitlist_entries.status',
            'patient_id' => 'appointment_waitlist_entries.patient_id',
            'provider_id' => 'appointment_waitlist_entries.provider_id',
            'clinic_id' => 'appointment_waitlist_entries.clinic_id',
        ] as $filter => $column) {
            if (is_string($filters[$filter] ?? null) && $filters[$filter] !== '') {
                $query->where($column, $filters[$filter]);
            }
        }

        if (is_string($filters['desired_from'] ?? null) && $filters['desired_from'] !== '') {
            $query->where('appointment_waitlist_entries.desired_date_from', '>=', $filters['desired_from']);
        }

        if (is_string($filters['desired_to'] ?? null) && $filters['desired_to'] !== '') {
            $query->where('appointment_waitlist_entries.desired_date_to', '<=', $filters['desired_to']);
        }

        $limit = is_numeric($filters['limit'] ?? null) ? (int) $filters['limit'] : 50;

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderBy('appointment_waitlist_entries.desired_date_from')
            ->orderBy('appointment_waitlist_entries.created_at')
            ->orderBy('appointment_waitlist_entries.id')
            ->limit($limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function update(string $tenantId, string $entryId, array $updates): ?WaitlistEntryData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $entryId);
        }

        $payload = ['updated_at' => CarbonImmutable::now()];

        foreach ($updates as $key => $value) {
            $payload[$key] = $key === 'offered_slot'
                ? $this->jsonValue(is_array($value) ? $this->associativeArrayValue($value) : null)
                : $value;
        }

        $updated = DB::table('appointment_waitlist_entries')
            ->where('tenant_id', $tenantId)
            ->where('id', $entryId)
            ->update($payload);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $entryId);
        }

        return $this->findInTenant($tenantId, $entryId);
    }

    private function baseQuery(string $tenantId): Builder
    {
        return DB::table('appointment_waitlist_entries')
            ->join('patients', function (JoinClause $join): void {
                $join->on('patients.id', '=', 'appointment_waitlist_entries.patient_id')
                    ->on('patients.tenant_id', '=', 'appointment_waitlist_entries.tenant_id');
            })
            ->join('providers', function (JoinClause $join): void {
                $join->on('providers.id', '=', 'appointment_waitlist_entries.provider_id')
                    ->on('providers.tenant_id', '=', 'appointment_waitlist_entries.tenant_id');
            })
            ->leftJoin('clinics', function (JoinClause $join): void {
                $join->on('clinics.id', '=', 'appointment_waitlist_entries.clinic_id')
                    ->on('clinics.tenant_id', '=', 'appointment_waitlist_entries.tenant_id');
            })
            ->leftJoin('clinic_rooms', function (JoinClause $join): void {
                $join->on('clinic_rooms.id', '=', 'appointment_waitlist_entries.room_id')
                    ->on('clinic_rooms.tenant_id', '=', 'appointment_waitlist_entries.tenant_id');
            })
            ->where('appointment_waitlist_entries.tenant_id', $tenantId)
            ->select([
                'appointment_waitlist_entries.id',
                'appointment_waitlist_entries.patient_id',
                'appointment_waitlist_entries.provider_id',
                'appointment_waitlist_entries.clinic_id',
                'appointment_waitlist_entries.room_id',
                'appointment_waitlist_entries.desired_date_from',
                'appointment_waitlist_entries.desired_date_to',
                'appointment_waitlist_entries.preferred_start_time',
                'appointment_waitlist_entries.preferred_end_time',
                'appointment_waitlist_entries.notes',
                'appointment_waitlist_entries.status',
                'appointment_waitlist_entries.booked_appointment_id',
                'appointment_waitlist_entries.offered_slot',
                'appointment_waitlist_entries.created_at',
                'appointment_waitlist_entries.updated_at',
                'patients.first_name as patient_first_name',
                'patients.last_name as patient_last_name',
                'patients.preferred_name as patient_preferred_name',
                'providers.first_name as provider_first_name',
                'providers.last_name as provider_last_name',
                'providers.preferred_name as provider_preferred_name',
                'clinics.name as clinic_name',
                'clinic_rooms.name as room_name',
            ]);
    }

    private function toData(stdClass $row): WaitlistEntryData
    {
        return new WaitlistEntryData(
            entryId: $this->stringValue($row->id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            patientDisplayName: $this->displayName(
                $this->nullableString($row->patient_preferred_name ?? null),
                $this->nullableString($row->patient_first_name ?? null),
                $this->nullableString($row->patient_last_name ?? null),
                $this->stringValue($row->patient_id ?? null),
            ),
            providerId: $this->stringValue($row->provider_id ?? null),
            providerDisplayName: $this->displayName(
                $this->nullableString($row->provider_preferred_name ?? null),
                $this->nullableString($row->provider_first_name ?? null),
                $this->nullableString($row->provider_last_name ?? null),
                $this->stringValue($row->provider_id ?? null),
            ),
            clinicId: $this->nullableString($row->clinic_id ?? null),
            clinicName: $this->nullableString($row->clinic_name ?? null),
            roomId: $this->nullableString($row->room_id ?? null),
            roomName: $this->nullableString($row->room_name ?? null),
            desiredDateFrom: $this->stringValue($row->desired_date_from ?? null),
            desiredDateTo: $this->stringValue($row->desired_date_to ?? null),
            preferredStartTime: $this->nullableString($row->preferred_start_time ?? null),
            preferredEndTime: $this->nullableString($row->preferred_end_time ?? null),
            notes: $this->nullableString($row->notes ?? null),
            status: $this->stringValue($row->status ?? null),
            bookedAppointmentId: $this->nullableString($row->booked_appointment_id ?? null),
            offeredSlot: $this->arrayValue($row->offered_slot ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    private function jsonValue(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayValue(mixed $value): ?array
    {
        if (is_array($value)) {
            return $this->associativeArrayValue($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $this->associativeArrayValue($decoded) : null;
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<string, mixed>
     */
    private function associativeArrayValue(array $value): array
    {
        if (array_is_list($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
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

    private function displayName(?string $preferredName, ?string $firstName, ?string $lastName, string $fallback): string
    {
        $parts = array_values(array_filter([
            $preferredName ?? $firstName,
            $lastName,
        ]));

        return $parts !== [] ? implode(' ', $parts) : $fallback;
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
