<?php

namespace App\Modules\TenantManagement\Infrastructure\Persistence;

use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Modules\TenantManagement\Application\Data\ClinicData;
use App\Modules\TenantManagement\Application\Data\ClinicHolidayData;
use App\Modules\TenantManagement\Application\Data\ClinicSettingsData;
use App\Modules\TenantManagement\Application\Data\ClinicWorkHoursData;
use App\Modules\TenantManagement\Application\Data\DepartmentData;
use App\Modules\TenantManagement\Application\Data\RoomData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class DatabaseClinicRepository implements ClinicRepository
{
    #[\Override]
    public function clinicCodeExists(string $tenantId, string $code, ?string $exceptClinicId = null): bool
    {
        $query = DB::table('clinics')
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if (is_string($exceptClinicId) && $exceptClinicId !== '') {
            $query->where('id', '!=', $exceptClinicId);
        }

        return $query->exists();
    }

    #[\Override]
    public function createClinic(
        string $tenantId,
        string $code,
        string $name,
        string $status,
        ?string $contactEmail,
        ?string $contactPhone,
        ?string $cityCode,
        ?string $districtCode,
        ?string $addressLine1,
        ?string $addressLine2,
        ?string $postalCode,
        ?string $notes,
    ): ClinicData {
        $clinicId = (string) Str::uuid();
        $timestamp = CarbonImmutable::now();

        DB::table('clinics')->insert([
            'id' => $clinicId,
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'status' => $status,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'city_code' => $cityCode,
            'district_code' => $districtCode,
            'address_line_1' => $addressLine1,
            'address_line_2' => $addressLine2,
            'postal_code' => $postalCode,
            'notes' => $notes,
            'activated_at' => $status === 'active' ? $timestamp : null,
            'deactivated_at' => $status === 'inactive' ? $timestamp : null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $this->findClinic($tenantId, $clinicId) ?? throw new LogicException('The clinic could not be reloaded after creation.');
    }

    #[\Override]
    public function createDepartment(
        string $tenantId,
        string $clinicId,
        string $code,
        string $name,
        ?string $description,
        ?string $phoneExtension,
    ): DepartmentData {
        $departmentId = (string) Str::uuid();
        $timestamp = CarbonImmutable::now();

        DB::table('clinic_departments')->insert([
            'id' => $departmentId,
            'tenant_id' => $tenantId,
            'clinic_id' => $clinicId,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'phone_extension' => $phoneExtension,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $this->findDepartment($tenantId, $clinicId, $departmentId) ?? throw new LogicException('The department could not be reloaded after creation.');
    }

    #[\Override]
    public function createHoliday(
        string $tenantId,
        string $clinicId,
        string $name,
        string $startDate,
        string $endDate,
        bool $isClosed,
        ?string $notes,
    ): ClinicHolidayData {
        $holidayId = (string) Str::uuid();
        $timestamp = CarbonImmutable::now();

        DB::table('clinic_holidays')->insert([
            'id' => $holidayId,
            'tenant_id' => $tenantId,
            'clinic_id' => $clinicId,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_closed' => $isClosed,
            'notes' => $notes,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $this->findHoliday($tenantId, $clinicId, $holidayId) ?? throw new LogicException('The holiday could not be reloaded after creation.');
    }

    #[\Override]
    public function createRoom(
        string $tenantId,
        string $clinicId,
        ?string $departmentId,
        string $code,
        string $name,
        string $type,
        ?string $floor,
        int $capacity,
        ?string $notes,
    ): RoomData {
        $roomId = (string) Str::uuid();
        $timestamp = CarbonImmutable::now();

        DB::table('clinic_rooms')->insert([
            'id' => $roomId,
            'tenant_id' => $tenantId,
            'clinic_id' => $clinicId,
            'department_id' => $departmentId,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'floor' => $floor,
            'capacity' => $capacity,
            'notes' => $notes,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $this->findRoom($tenantId, $clinicId, $roomId) ?? throw new LogicException('The room could not be reloaded after creation.');
    }

    #[\Override]
    public function deleteClinic(string $tenantId, string $clinicId): bool
    {
        return DB::table('clinics')
            ->where('tenant_id', $tenantId)
            ->where('id', $clinicId)
            ->delete() > 0;
    }

    #[\Override]
    public function deleteDepartment(string $tenantId, string $clinicId, string $departmentId): bool
    {
        return DB::table('clinic_departments')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('id', $departmentId)
            ->delete() > 0;
    }

    #[\Override]
    public function deleteHoliday(string $tenantId, string $clinicId, string $holidayId): bool
    {
        return DB::table('clinic_holidays')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('id', $holidayId)
            ->delete() > 0;
    }

    #[\Override]
    public function deleteRoom(string $tenantId, string $clinicId, string $roomId): bool
    {
        return DB::table('clinic_rooms')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('id', $roomId)
            ->delete() > 0;
    }

    #[\Override]
    public function departmentCodeExists(string $tenantId, string $clinicId, string $code, ?string $exceptDepartmentId = null): bool
    {
        $query = DB::table('clinic_departments')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('code', $code);

        if (is_string($exceptDepartmentId) && $exceptDepartmentId !== '') {
            $query->where('id', '!=', $exceptDepartmentId);
        }

        return $query->exists();
    }

    #[\Override]
    public function departmentExists(string $tenantId, string $clinicId, string $departmentId): bool
    {
        return DB::table('clinic_departments')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('id', $departmentId)
            ->exists();
    }

    #[\Override]
    public function findClinic(string $tenantId, string $clinicId): ?ClinicData
    {
        $row = $this->clinicQuery()
            ->where('tenant_id', $tenantId)
            ->where('id', $clinicId)
            ->first();

        return $row !== null ? $this->mapClinic($row) : null;
    }

    #[\Override]
    public function findDepartment(string $tenantId, string $clinicId, string $departmentId): ?DepartmentData
    {
        $row = $this->departmentQuery()
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('id', $departmentId)
            ->first();

        return $row !== null ? $this->mapDepartment($row) : null;
    }

    #[\Override]
    public function findHoliday(string $tenantId, string $clinicId, string $holidayId): ?ClinicHolidayData
    {
        $row = $this->holidayQuery()
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('id', $holidayId)
            ->first();

        return $row !== null ? $this->mapHoliday($row) : null;
    }

    #[\Override]
    public function findRoom(string $tenantId, string $clinicId, string $roomId): ?RoomData
    {
        $row = $this->roomQuery()
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('id', $roomId)
            ->first();

        return $row !== null ? $this->mapRoom($row) : null;
    }

    #[\Override]
    public function holidayRangeOverlaps(
        string $tenantId,
        string $clinicId,
        string $startDate,
        string $endDate,
        ?string $exceptHolidayId = null,
    ): bool {
        $query = DB::table('clinic_holidays')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);

        if (is_string($exceptHolidayId) && $exceptHolidayId !== '') {
            $query->where('id', '!=', $exceptHolidayId);
        }

        return $query->exists();
    }

    #[\Override]
    public function listClinics(string $tenantId, ?string $search = null, ?string $status = null): array
    {
        $query = $this->clinicQuery()->where('tenant_id', $tenantId);

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        if (is_string($search) && $search !== '') {
            $term = '%'.strtolower($search).'%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(contact_email, \'\')) LIKE ?', [$term]);
            });
        }

        return array_values(array_map(
            fn (object $row): ClinicData => $this->mapClinic($row),
            $query->orderBy('name')->get()->all(),
        ));
    }

    #[\Override]
    public function listDepartments(string $tenantId, string $clinicId): array
    {
        return array_values(array_map(
            fn (object $row): DepartmentData => $this->mapDepartment($row),
            $this->departmentQuery()
                ->where('tenant_id', $tenantId)
                ->where('clinic_id', $clinicId)
                ->orderBy('name')
                ->get()
                ->all(),
        ));
    }

    #[\Override]
    public function listHolidays(string $tenantId, string $clinicId): array
    {
        return array_values(array_map(
            fn (object $row): ClinicHolidayData => $this->mapHoliday($row),
            $this->holidayQuery()
                ->where('tenant_id', $tenantId)
                ->where('clinic_id', $clinicId)
                ->orderBy('start_date')
                ->orderBy('name')
                ->get()
                ->all(),
        ));
    }

    #[\Override]
    public function listRooms(string $tenantId, string $clinicId): array
    {
        return array_values(array_map(
            fn (object $row): RoomData => $this->mapRoom($row),
            $this->roomQuery()
                ->where('tenant_id', $tenantId)
                ->where('clinic_id', $clinicId)
                ->orderBy('name')
                ->get()
                ->all(),
        ));
    }

    #[\Override]
    public function replaceClinicWorkHours(string $tenantId, string $clinicId, ClinicWorkHoursData $workHours): ClinicWorkHoursData
    {
        DB::table('clinic_work_hours')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->delete();

        $timestamp = CarbonImmutable::now();
        $payload = [];

        foreach ($workHours->days as $day => $intervals) {
            foreach ($intervals as $interval) {
                $payload[] = [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'clinic_id' => $clinicId,
                    'day_of_week' => $day,
                    'start_time' => $interval['start_time'],
                    'end_time' => $interval['end_time'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        if ($payload !== []) {
            DB::table('clinic_work_hours')->insert($payload);
        }

        return $this->workHours($tenantId, $clinicId);
    }

    #[\Override]
    public function replaceSettings(string $tenantId, string $clinicId, ClinicSettingsData $settings): ClinicSettingsData
    {
        $now = CarbonImmutable::now();
        $payload = [
            'timezone' => $settings->timezone,
            'default_appointment_duration_minutes' => $settings->defaultAppointmentDurationMinutes,
            'slot_interval_minutes' => $settings->slotIntervalMinutes,
            'allow_walk_ins' => $settings->allowWalkIns,
            'require_appointment_confirmation' => $settings->requireAppointmentConfirmation,
            'telemedicine_enabled' => $settings->telemedicineEnabled,
            'updated_at' => $now,
        ];

        if (DB::table('clinic_settings')->where('clinic_id', $clinicId)->exists()) {
            DB::table('clinic_settings')->where('clinic_id', $clinicId)->update($payload);
        } else {
            DB::table('clinic_settings')->insert($payload + [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'clinic_id' => $clinicId,
                'created_at' => $now,
            ]);
        }

        return $this->settings($tenantId, $clinicId);
    }

    #[\Override]
    public function roomCodeExists(string $tenantId, string $clinicId, string $code, ?string $exceptRoomId = null): bool
    {
        $query = DB::table('clinic_rooms')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('code', $code);

        if (is_string($exceptRoomId) && $exceptRoomId !== '') {
            $query->where('id', '!=', $exceptRoomId);
        }

        return $query->exists();
    }

    #[\Override]
    public function settings(string $tenantId, string $clinicId): ClinicSettingsData
    {
        $row = DB::table('clinic_settings')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->first();

        if ($row === null) {
            return new ClinicSettingsData;
        }

        return new ClinicSettingsData(
            timezone: $this->nullableString($row->timezone ?? null),
            defaultAppointmentDurationMinutes: $this->intValue($row->default_appointment_duration_minutes ?? 30, 30),
            slotIntervalMinutes: $this->intValue($row->slot_interval_minutes ?? 15, 15),
            allowWalkIns: (bool) ($row->allow_walk_ins ?? true),
            requireAppointmentConfirmation: (bool) ($row->require_appointment_confirmation ?? false),
            telemedicineEnabled: (bool) ($row->telemedicine_enabled ?? false),
            updatedAt: $this->nullableTimestamp($row->updated_at ?? null),
        );
    }

    #[\Override]
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateClinic(string $tenantId, string $clinicId, array $attributes): bool
    {
        return DB::table('clinics')
            ->where('tenant_id', $tenantId)
            ->where('id', $clinicId)
            ->update($attributes + ['updated_at' => CarbonImmutable::now()]) > 0;
    }

    #[\Override]
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDepartment(string $tenantId, string $clinicId, string $departmentId, array $attributes): bool
    {
        return DB::table('clinic_departments')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('id', $departmentId)
            ->update($attributes + ['updated_at' => CarbonImmutable::now()]) > 0;
    }

    #[\Override]
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRoom(string $tenantId, string $clinicId, string $roomId, array $attributes): bool
    {
        return DB::table('clinic_rooms')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->where('id', $roomId)
            ->update($attributes + ['updated_at' => CarbonImmutable::now()]) > 0;
    }

    #[\Override]
    public function workHours(string $tenantId, string $clinicId): ClinicWorkHoursData
    {
        $rows = DB::table('clinic_work_hours')
            ->where('tenant_id', $tenantId)
            ->where('clinic_id', $clinicId)
            ->orderByRaw($this->workHoursSortExpression())
            ->orderBy('start_time')
            ->get();

        $days = $this->emptyWorkWeek();
        $updatedAt = null;

        foreach ($rows as $row) {
            $day = $this->requiredString($row->day_of_week ?? null);

            if (! array_key_exists($day, $days)) {
                continue;
            }

            $days[$day][] = [
                'start_time' => $this->timeString($row->start_time ?? null),
                'end_time' => $this->timeString($row->end_time ?? null),
            ];

            $candidate = $this->nullableTimestamp($row->updated_at ?? null);

            if ($candidate !== null && ($updatedAt === null || $candidate->greaterThan($updatedAt))) {
                $updatedAt = $candidate;
            }
        }

        return new ClinicWorkHoursData(
            clinicId: $clinicId,
            days: $days,
            updatedAt: $updatedAt,
        );
    }

    private function clinicQuery(): Builder
    {
        return DB::table('clinics')->select([
            'id',
            'tenant_id',
            'code',
            'name',
            'status',
            'contact_email',
            'contact_phone',
            'city_code',
            'district_code',
            'address_line_1',
            'address_line_2',
            'postal_code',
            'notes',
            'activated_at',
            'deactivated_at',
            'created_at',
            'updated_at',
        ]);
    }

    private function departmentQuery(): Builder
    {
        return DB::table('clinic_departments')->select([
            'id',
            'clinic_id',
            'code',
            'name',
            'description',
            'phone_extension',
            'created_at',
            'updated_at',
            'tenant_id',
        ]);
    }

    /**
     * @return array<string, list<array{start_time: string, end_time: string}>>
     */
    private function emptyWorkWeek(): array
    {
        return [
            'monday' => [],
            'tuesday' => [],
            'wednesday' => [],
            'thursday' => [],
            'friday' => [],
            'saturday' => [],
            'sunday' => [],
        ];
    }

    private function holidayQuery(): Builder
    {
        return DB::table('clinic_holidays')->select([
            'id',
            'clinic_id',
            'name',
            'start_date',
            'end_date',
            'is_closed',
            'notes',
            'created_at',
            'updated_at',
            'tenant_id',
        ]);
    }

    private function intValue(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private function mapClinic(object $row): ClinicData
    {
        return new ClinicData(
            clinicId: $this->requiredString($row->id ?? null),
            tenantId: $this->requiredString($row->tenant_id ?? null),
            code: $this->requiredString($row->code ?? null),
            name: $this->requiredString($row->name ?? null),
            status: $this->requiredString($row->status ?? null),
            contactEmail: $this->nullableString($row->contact_email ?? null),
            contactPhone: $this->nullableString($row->contact_phone ?? null),
            cityCode: $this->nullableString($row->city_code ?? null),
            districtCode: $this->nullableString($row->district_code ?? null),
            addressLine1: $this->nullableString($row->address_line_1 ?? null),
            addressLine2: $this->nullableString($row->address_line_2 ?? null),
            postalCode: $this->nullableString($row->postal_code ?? null),
            notes: $this->nullableString($row->notes ?? null),
            activatedAt: $this->nullableTimestamp($row->activated_at ?? null),
            deactivatedAt: $this->nullableTimestamp($row->deactivated_at ?? null),
            createdAt: $this->timestamp($row->created_at ?? null),
            updatedAt: $this->timestamp($row->updated_at ?? null),
        );
    }

    private function mapDepartment(object $row): DepartmentData
    {
        return new DepartmentData(
            departmentId: $this->requiredString($row->id ?? null),
            clinicId: $this->requiredString($row->clinic_id ?? null),
            code: $this->requiredString($row->code ?? null),
            name: $this->requiredString($row->name ?? null),
            description: $this->nullableString($row->description ?? null),
            phoneExtension: $this->nullableString($row->phone_extension ?? null),
            createdAt: $this->timestamp($row->created_at ?? null),
            updatedAt: $this->timestamp($row->updated_at ?? null),
        );
    }

    private function mapHoliday(object $row): ClinicHolidayData
    {
        return new ClinicHolidayData(
            holidayId: $this->requiredString($row->id ?? null),
            clinicId: $this->requiredString($row->clinic_id ?? null),
            name: $this->requiredString($row->name ?? null),
            startDate: $this->timestamp($row->start_date ?? null),
            endDate: $this->timestamp($row->end_date ?? null),
            isClosed: (bool) ($row->is_closed ?? false),
            notes: $this->nullableString($row->notes ?? null),
            createdAt: $this->timestamp($row->created_at ?? null),
            updatedAt: $this->timestamp($row->updated_at ?? null),
        );
    }

    private function mapRoom(object $row): RoomData
    {
        return new RoomData(
            roomId: $this->requiredString($row->id ?? null),
            clinicId: $this->requiredString($row->clinic_id ?? null),
            departmentId: $this->nullableString($row->department_id ?? null),
            code: $this->requiredString($row->code ?? null),
            name: $this->requiredString($row->name ?? null),
            type: $this->requiredString($row->type ?? null),
            floor: $this->nullableString($row->floor ?? null),
            capacity: $this->intValue($row->capacity ?? 1, 1),
            notes: $this->nullableString($row->notes ?? null),
            createdAt: $this->timestamp($row->created_at ?? null),
            updatedAt: $this->timestamp($row->updated_at ?? null),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function nullableTimestamp(mixed $value): ?CarbonImmutable
    {
        return is_string($value) || $value instanceof \DateTimeInterface ? CarbonImmutable::parse($value) : null;
    }

    private function requiredString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function roomQuery(): Builder
    {
        return DB::table('clinic_rooms')->select([
            'id',
            'clinic_id',
            'department_id',
            'code',
            'name',
            'type',
            'floor',
            'capacity',
            'notes',
            'created_at',
            'updated_at',
            'tenant_id',
        ]);
    }

    private function timeString(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return substr($value, 0, 5);
    }

    private function timestamp(mixed $value): CarbonImmutable
    {
        if (! is_string($value) && ! $value instanceof \DateTimeInterface) {
            throw new LogicException('Clinic timestamps must be string or date-time instances.');
        }

        return CarbonImmutable::parse($value);
    }

    private function workHoursSortExpression(): string
    {
        return "CASE day_of_week
            WHEN 'monday' THEN 1
            WHEN 'tuesday' THEN 2
            WHEN 'wednesday' THEN 3
            WHEN 'thursday' THEN 4
            WHEN 'friday' THEN 5
            WHEN 'saturday' THEN 6
            WHEN 'sunday' THEN 7
            ELSE 8
        END";
    }
}
