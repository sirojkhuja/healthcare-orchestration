<?php

namespace App\Modules\Scheduling\Infrastructure\Persistence;

use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AppointmentSearchCriteria;
use App\Modules\Scheduling\Domain\Appointments\AppointmentStatus;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseAppointmentRepository implements AppointmentRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): AppointmentData
    {
        $candidateId = $attributes['id'] ?? null;
        $appointmentId = is_string($candidateId) ? $candidateId : (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('appointments')->insert([
            'id' => $appointmentId,
            'tenant_id' => $tenantId,
            'patient_id' => $attributes['patient_id'],
            'provider_id' => $attributes['provider_id'],
            'clinic_id' => $attributes['clinic_id'],
            'room_id' => $attributes['room_id'],
            'recurrence_id' => $attributes['recurrence_id'] ?? null,
            'status' => $attributes['status'],
            'scheduled_start_at' => $attributes['scheduled_start_at'],
            'scheduled_end_at' => $attributes['scheduled_end_at'],
            'timezone' => $attributes['timezone'],
            'last_transition' => $this->jsonValue($attributes['last_transition'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $appointmentId)
            ?? throw new \LogicException('Created appointment could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $appointmentId, bool $withDeleted = false): ?AppointmentData
    {
        $row = $this->baseQuery($tenantId, $withDeleted)
            ->where('appointments.id', $appointmentId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function findManyInTenant(string $tenantId, array $appointmentIds, bool $withDeleted = false): array
    {
        if ($appointmentIds === []) {
            return [];
        }

        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId, $withDeleted)
            ->whereIn('appointments.id', $appointmentIds)
            ->orderBy('appointments.scheduled_start_at')
            ->orderBy('appointments.created_at')
            ->orderBy('appointments.id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId)
            ->orderBy('appointments.scheduled_start_at')
            ->orderBy('appointments.created_at')
            ->orderBy('appointments.id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function search(string $tenantId, AppointmentSearchCriteria $criteria): array
    {
        $query = $this->baseQuery($tenantId);
        $this->applyStructuredFilters($query, $criteria);
        $this->applySearchTokens($query, $criteria);

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderBy('appointments.scheduled_start_at')
            ->orderBy('appointments.created_at')
            ->orderBy('appointments.id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function listBlockingForProviderWindow(
        string $tenantId,
        string $providerId,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
    ): array {
        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId)
            ->where('appointments.provider_id', $providerId)
            ->whereIn('appointments.status', [
                AppointmentStatus::SCHEDULED->value,
                AppointmentStatus::CONFIRMED->value,
                AppointmentStatus::CHECKED_IN->value,
                AppointmentStatus::IN_PROGRESS->value,
            ])
            ->where('appointments.scheduled_end_at', '>', $windowStart)
            ->where('appointments.scheduled_start_at', '<', $windowEnd)
            ->orderBy('appointments.scheduled_start_at')
            ->orderBy('appointments.id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function listForRecurrence(string $tenantId, string $recurrenceId, bool $withDeleted = false): array
    {
        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId, $withDeleted)
            ->where('appointments.recurrence_id', $recurrenceId)
            ->orderBy('appointments.scheduled_start_at')
            ->orderBy('appointments.created_at')
            ->orderBy('appointments.id')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function softDelete(string $tenantId, string $appointmentId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->where('id', $appointmentId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function update(string $tenantId, string $appointmentId, array $updates): ?AppointmentData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $appointmentId);
        }

        $updates['updated_at'] = CarbonImmutable::now();
        if (array_key_exists('last_transition', $updates)) {
            $updates['last_transition'] = $this->jsonValue($updates['last_transition']);
        }

        $updated = DB::table('appointments')
            ->where('tenant_id', $tenantId)
            ->where('id', $appointmentId)
            ->whereNull('deleted_at')
            ->update($updates);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $appointmentId);
        }

        return $this->findInTenant($tenantId, $appointmentId);
    }

    private function applyStructuredFilters(Builder $query, AppointmentSearchCriteria $criteria): void
    {
        foreach ([
            'status' => $criteria->status,
            'patient_id' => $criteria->patientId,
            'provider_id' => $criteria->providerId,
            'clinic_id' => $criteria->clinicId,
            'room_id' => $criteria->roomId,
        ] as $column => $value) {
            if ($value !== null) {
                $query->where('appointments.'.$column, $value);
            }
        }

        if ($criteria->scheduledFrom !== null) {
            $query->where('appointments.scheduled_start_at', '>=', CarbonImmutable::parse($criteria->scheduledFrom)->startOfDay());
        }

        if ($criteria->scheduledTo !== null) {
            $query->where('appointments.scheduled_start_at', '<=', CarbonImmutable::parse($criteria->scheduledTo)->endOfDay());
        }

        if ($criteria->createdFrom !== null) {
            $query->where('appointments.created_at', '>=', CarbonImmutable::parse($criteria->createdFrom)->startOfDay());
        }

        if ($criteria->createdTo !== null) {
            $query->where('appointments.created_at', '<=', CarbonImmutable::parse($criteria->createdTo)->endOfDay());
        }
    }

    private function applySearchTokens(Builder $query, AppointmentSearchCriteria $criteria): void
    {
        foreach ($criteria->tokens() as $token) {
            $pattern = '%'.$token.'%';

            $query->where(function (Builder $nested) use ($pattern): void {
                $nested
                    ->whereRaw('LOWER(CAST(appointments.id AS TEXT)) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(patients.first_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(patients.last_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(patients.preferred_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(providers.first_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(providers.last_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(providers.preferred_name, \'\')) LIKE ?', [$pattern]);
            });
        }
    }

    private function baseQuery(string $tenantId, bool $withDeleted = false): Builder
    {
        $query = DB::table('appointments')
            ->leftJoin('patients', function (JoinClause $join): void {
                $join->on('patients.id', '=', 'appointments.patient_id')
                    ->on('patients.tenant_id', '=', 'appointments.tenant_id');
            })
            ->leftJoin('providers', function (JoinClause $join): void {
                $join->on('providers.id', '=', 'appointments.provider_id')
                    ->on('providers.tenant_id', '=', 'appointments.tenant_id');
            })
            ->leftJoin('clinics', function (JoinClause $join): void {
                $join->on('clinics.id', '=', 'appointments.clinic_id')
                    ->on('clinics.tenant_id', '=', 'appointments.tenant_id');
            })
            ->leftJoin('clinic_rooms', function (JoinClause $join): void {
                $join->on('clinic_rooms.id', '=', 'appointments.room_id')
                    ->on('clinic_rooms.tenant_id', '=', 'appointments.tenant_id');
            })
            ->where('appointments.tenant_id', $tenantId)
            ->select([
                'appointments.id as appointment_id',
                'appointments.tenant_id',
                'appointments.patient_id',
                'appointments.provider_id',
                'appointments.clinic_id',
                'appointments.room_id',
                'appointments.recurrence_id',
                'appointments.status',
                'appointments.scheduled_start_at',
                'appointments.scheduled_end_at',
                'appointments.timezone',
                'appointments.last_transition',
                'appointments.deleted_at',
                'appointments.created_at',
                'appointments.updated_at',
                'patients.first_name as patient_first_name',
                'patients.last_name as patient_last_name',
                'patients.preferred_name as patient_preferred_name',
                'providers.first_name as provider_first_name',
                'providers.last_name as provider_last_name',
                'providers.preferred_name as provider_preferred_name',
                'clinics.name as clinic_name',
                'clinic_rooms.name as room_name',
            ]);

        if (! $withDeleted) {
            $query->whereNull('appointments.deleted_at');
        }

        return $query;
    }

    private function toData(stdClass $row): AppointmentData
    {
        $timezone = $this->stringValue($row->timezone ?? null);

        return new AppointmentData(
            appointmentId: $this->stringValue($row->appointment_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            patientDisplayName: $this->displayName(
                preferredName: $this->nullableString($row->patient_preferred_name ?? null),
                firstName: $this->nullableString($row->patient_first_name ?? null),
                lastName: $this->nullableString($row->patient_last_name ?? null),
                fallback: $this->stringValue($row->patient_id ?? null),
            ),
            providerId: $this->stringValue($row->provider_id ?? null),
            providerDisplayName: $this->displayName(
                preferredName: $this->nullableString($row->provider_preferred_name ?? null),
                firstName: $this->nullableString($row->provider_first_name ?? null),
                lastName: $this->nullableString($row->provider_last_name ?? null),
                fallback: $this->stringValue($row->provider_id ?? null),
            ),
            clinicId: $this->nullableString($row->clinic_id ?? null),
            clinicName: $this->nullableString($row->clinic_name ?? null),
            roomId: $this->nullableString($row->room_id ?? null),
            roomName: $this->nullableString($row->room_name ?? null),
            recurrenceId: $this->nullableString($row->recurrence_id ?? null),
            status: $this->stringValue($row->status ?? null),
            scheduledStartAt: $this->slotDateTime($row->scheduled_start_at ?? null, $timezone),
            scheduledEndAt: $this->slotDateTime($row->scheduled_end_at ?? null, $timezone),
            timezone: $timezone,
            lastTransition: $this->arrayValue($row->last_transition ?? null),
            deletedAt: $this->nullableDateTime($row->deleted_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function displayName(?string $preferredName, ?string $firstName, ?string $lastName, string $fallback): string
    {
        $parts = array_values(array_filter([
            $preferredName ?? $firstName,
            $lastName,
        ]));

        return $parts !== [] ? implode(' ', $parts) : $fallback;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function arrayValue(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            /** @var array<string, mixed> $value */
            return $value;
        }

        if (is_string($value)) {
            /** @var mixed $decoded */
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }

        return null;
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(\DateTime::createFromInterface($value));
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value);
        }

        throw new \LogicException('Expected datetime column to be a string or DateTimeInterface value.');
    }

    private function slotDateTime(mixed $value, string $timezone): CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance(\DateTime::createFromInterface($value))->setTimezone($timezone);
        }

        if (is_string($value)) {
            return CarbonImmutable::parse($value, $timezone);
        }

        throw new \LogicException('Expected appointment slot column to be a string or DateTimeInterface value.');
    }

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return $this->dateTime($value);
    }

    private function jsonValue(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
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
