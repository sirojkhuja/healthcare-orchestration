<?php

namespace App\Modules\TenantManagement\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Modules\TenantManagement\Application\Contracts\LocationReferenceRepository;
use App\Modules\TenantManagement\Application\Data\ClinicData;
use App\Modules\TenantManagement\Application\Data\ClinicHolidayData;
use App\Modules\TenantManagement\Application\Data\ClinicSettingsData;
use App\Modules\TenantManagement\Application\Data\ClinicWorkHoursData;
use App\Modules\TenantManagement\Application\Data\DepartmentData;
use App\Modules\TenantManagement\Application\Data\LocationCityData;
use App\Modules\TenantManagement\Application\Data\LocationDistrictData;
use App\Modules\TenantManagement\Application\Data\LocationSearchResultData;
use App\Modules\TenantManagement\Application\Data\RoomData;
use App\Modules\TenantManagement\Domain\Clinics\ClinicStatus;
use App\Modules\TenantManagement\Domain\Clinics\RoomType;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ClinicAdministrationService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ClinicRepository $clinicRepository,
        private readonly LocationReferenceRepository $locationReferenceRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function activateClinic(string $clinicId): ClinicData
    {
        return $this->transitionClinic($clinicId, ClinicStatus::ACTIVE, 'clinics.activated');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createClinic(array $attributes): ClinicData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalized = $this->normalizedClinicCreateAttributes($attributes);

        if ($this->clinicRepository->clinicCodeExists($tenantId, $normalized['code'])) {
            throw new ConflictHttpException('A clinic with this code already exists in the active tenant.');
        }

        $this->assertLocationConsistency($normalized['city_code'], $normalized['district_code']);

        /** @var ClinicData $clinic */
        $clinic = DB::transaction(function () use ($tenantId, $normalized): ClinicData {
            $clinic = $this->clinicRepository->createClinic(
                tenantId: $tenantId,
                code: $normalized['code'],
                name: $normalized['name'],
                status: ClinicStatus::ACTIVE,
                contactEmail: $normalized['contact_email'],
                contactPhone: $normalized['contact_phone'],
                cityCode: $normalized['city_code'],
                districtCode: $normalized['district_code'],
                addressLine1: $normalized['address_line_1'],
                addressLine2: $normalized['address_line_2'],
                postalCode: $normalized['postal_code'],
                notes: $normalized['notes'],
            );

            $this->clinicRepository->replaceSettings($tenantId, $clinic->clinicId, new ClinicSettingsData);

            return $clinic;
        });

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.created',
            objectType: 'clinic',
            objectId: $clinic->clinicId,
            after: $clinic->toArray(),
        ));

        return $clinic;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createDepartment(string $clinicId, array $attributes): DepartmentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);
        $normalized = $this->normalizedDepartmentCreateAttributes($attributes);

        if ($this->clinicRepository->departmentCodeExists($tenantId, $clinicId, $normalized['code'])) {
            throw new ConflictHttpException('A department with this code already exists in the selected clinic.');
        }

        $department = $this->clinicRepository->createDepartment(
            tenantId: $tenantId,
            clinicId: $clinic->clinicId,
            code: $normalized['code'],
            name: $normalized['name'],
            description: $normalized['description'],
            phoneExtension: $normalized['phone_extension'],
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.department_created',
            objectType: 'clinic_department',
            objectId: $department->departmentId,
            after: $department->toArray(),
            metadata: ['clinic_id' => $clinic->clinicId],
        ));

        return $department;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createHoliday(string $clinicId, array $attributes): ClinicHolidayData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);
        $normalized = $this->normalizedHolidayAttributes($attributes);

        if ($this->clinicRepository->holidayRangeOverlaps(
            tenantId: $tenantId,
            clinicId: $clinic->clinicId,
            startDate: $normalized['start_date'],
            endDate: $normalized['end_date'],
        )) {
            throw new ConflictHttpException('Clinic holidays may not overlap existing holiday ranges.');
        }

        $holiday = $this->clinicRepository->createHoliday(
            tenantId: $tenantId,
            clinicId: $clinic->clinicId,
            name: $normalized['name'],
            startDate: $normalized['start_date'],
            endDate: $normalized['end_date'],
            isClosed: $normalized['is_closed'],
            notes: $normalized['notes'],
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.holiday_created',
            objectType: 'clinic_holiday',
            objectId: $holiday->holidayId,
            after: $holiday->toArray(),
            metadata: ['clinic_id' => $clinic->clinicId],
        ));

        return $holiday;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createRoom(string $clinicId, array $attributes): RoomData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);
        $normalized = $this->normalizedRoomCreateAttributes($attributes);
        $this->assertRoomDepartment($clinic->clinicId, $normalized['department_id']);

        if ($this->clinicRepository->roomCodeExists($tenantId, $clinicId, $normalized['code'])) {
            throw new ConflictHttpException('A room with this code already exists in the selected clinic.');
        }

        $room = $this->clinicRepository->createRoom(
            tenantId: $tenantId,
            clinicId: $clinic->clinicId,
            departmentId: $normalized['department_id'],
            code: $normalized['code'],
            name: $normalized['name'],
            type: $normalized['type'],
            floor: $normalized['floor'],
            capacity: $normalized['capacity'],
            notes: $normalized['notes'],
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.room_created',
            objectType: 'clinic_room',
            objectId: $room->roomId,
            after: $room->toArray(),
            metadata: ['clinic_id' => $clinic->clinicId],
        ));

        return $room;
    }

    public function deactivateClinic(string $clinicId): ClinicData
    {
        return $this->transitionClinic($clinicId, ClinicStatus::INACTIVE, 'clinics.deactivated');
    }

    public function deleteClinic(string $clinicId): ClinicData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);

        if (! ClinicStatus::canDelete($clinic->status)) {
            throw new ConflictHttpException('Only inactive clinics may be deleted.');
        }

        if (! $this->clinicRepository->deleteClinic($tenantId, $clinic->clinicId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.deleted',
            objectType: 'clinic',
            objectId: $clinic->clinicId,
            before: $clinic->toArray(),
        ));

        return $clinic;
    }

    public function deleteDepartment(string $clinicId, string $departmentId): DepartmentData
    {
        $department = $this->departmentOrFail($clinicId, $departmentId);
        $tenantId = $this->tenantContext->requireTenantId();

        if (! $this->clinicRepository->deleteDepartment($tenantId, $clinicId, $departmentId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.department_deleted',
            objectType: 'clinic_department',
            objectId: $department->departmentId,
            before: $department->toArray(),
            metadata: ['clinic_id' => $clinicId],
        ));

        return $department;
    }

    public function deleteHoliday(string $clinicId, string $holidayId): ClinicHolidayData
    {
        $holiday = $this->holidayOrFail($clinicId, $holidayId);
        $tenantId = $this->tenantContext->requireTenantId();

        if (! $this->clinicRepository->deleteHoliday($tenantId, $clinicId, $holidayId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.holiday_deleted',
            objectType: 'clinic_holiday',
            objectId: $holiday->holidayId,
            before: $holiday->toArray(),
            metadata: ['clinic_id' => $clinicId],
        ));

        return $holiday;
    }

    public function deleteRoom(string $clinicId, string $roomId): RoomData
    {
        $room = $this->roomOrFail($clinicId, $roomId);
        $tenantId = $this->tenantContext->requireTenantId();

        if (! $this->clinicRepository->deleteRoom($tenantId, $clinicId, $roomId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.room_deleted',
            objectType: 'clinic_room',
            objectId: $room->roomId,
            before: $room->toArray(),
            metadata: ['clinic_id' => $clinicId],
        ));

        return $room;
    }

    public function getClinic(string $clinicId): ClinicData
    {
        return $this->clinicOrFail($clinicId);
    }

    public function getClinicSettings(string $clinicId): ClinicSettingsData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);

        return $this->clinicRepository->settings($tenantId, $clinic->clinicId);
    }

    public function getClinicWorkHours(string $clinicId): ClinicWorkHoursData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);

        return $this->clinicRepository->workHours($tenantId, $clinic->clinicId);
    }

    public function getDepartment(string $clinicId, string $departmentId): DepartmentData
    {
        return $this->departmentOrFail($clinicId, $departmentId);
    }

    /**
     * @return list<LocationCityData>
     */
    public function listCities(?string $query = null): array
    {
        return $this->locationReferenceRepository->listCities($query);
    }

    /**
     * @return list<ClinicData>
     */
    public function listClinics(?string $search = null, ?string $status = null): array
    {
        return $this->clinicRepository->listClinics($this->tenantContext->requireTenantId(), $search, $status);
    }

    /**
     * @return list<DepartmentData>
     */
    public function listDepartments(string $clinicId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);

        return $this->clinicRepository->listDepartments($tenantId, $clinic->clinicId);
    }

    /**
     * @return list<LocationDistrictData>
     */
    public function listDistricts(string $cityCode): array
    {
        $normalized = $this->normalizedCode($cityCode, false) ?? '';

        if (! $this->locationReferenceRepository->cityExists($normalized)) {
            throw new NotFoundHttpException('The requested city does not exist in the approved location catalog.');
        }

        return $this->locationReferenceRepository->listDistricts($normalized);
    }

    /**
     * @return list<ClinicHolidayData>
     */
    public function listHolidays(string $clinicId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);

        return $this->clinicRepository->listHolidays($tenantId, $clinic->clinicId);
    }

    /**
     * @return list<RoomData>
     */
    public function listRooms(string $clinicId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);

        return $this->clinicRepository->listRooms($tenantId, $clinic->clinicId);
    }

    /**
     * @return list<LocationSearchResultData>
     */
    public function searchLocations(string $query): array
    {
        $normalized = trim($query);

        if ($normalized === '') {
            throw new UnprocessableEntityHttpException('The location search query must not be empty.');
        }

        return $this->locationReferenceRepository->search($normalized);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateClinic(string $clinicId, array $attributes): ClinicData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);
        $normalized = $this->normalizedClinicPatchAttributes($attributes);
        $updates = [];

        if (array_key_exists('code', $normalized) && is_string($normalized['code']) && $normalized['code'] !== $clinic->code) {
            if ($this->clinicRepository->clinicCodeExists($tenantId, $normalized['code'], $clinic->clinicId)) {
                throw new ConflictHttpException('A clinic with this code already exists in the active tenant.');
            }

            $updates['code'] = $normalized['code'];
        }

        if (array_key_exists('name', $normalized) && $normalized['name'] !== $clinic->name) {
            $updates['name'] = $normalized['name'];
        }

        $mergedCityCode = array_key_exists('city_code', $normalized) ? $normalized['city_code'] : $clinic->cityCode;
        $mergedDistrictCode = array_key_exists('district_code', $normalized) ? $normalized['district_code'] : $clinic->districtCode;
        $this->assertLocationConsistency($mergedCityCode, $mergedDistrictCode);

        foreach (['contact_email', 'contact_phone', 'city_code', 'district_code', 'address_line_1', 'address_line_2', 'postal_code', 'notes'] as $key) {
            $current = $this->clinicAttributeValue($clinic, $key);

            if (! array_key_exists($key, $normalized) || $normalized[$key] === $current) {
                continue;
            }

            $updates[$key] = $normalized[$key];
        }

        if ($updates === []) {
            return $clinic;
        }

        $this->clinicRepository->updateClinic($tenantId, $clinic->clinicId, $updates);
        $updated = $this->clinicOrFail($clinicId);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.updated',
            objectType: 'clinic',
            objectId: $updated->clinicId,
            before: $clinic->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    public function updateClinicSettings(string $clinicId, ClinicSettingsData $settings): ClinicSettingsData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);
        $before = $this->clinicRepository->settings($tenantId, $clinic->clinicId);
        $after = $this->clinicRepository->replaceSettings($tenantId, $clinic->clinicId, $this->normalizedSettings($settings));

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.settings_updated',
            objectType: 'clinic',
            objectId: $clinic->clinicId,
            before: $before->toArray(),
            after: $after->toArray(),
        ));

        return $after;
    }

    public function updateClinicWorkHours(string $clinicId, ClinicWorkHoursData $workHours): ClinicWorkHoursData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);
        $before = $this->clinicRepository->workHours($tenantId, $clinic->clinicId);
        $after = $this->clinicRepository->replaceClinicWorkHours($tenantId, $clinic->clinicId, $this->normalizedWorkHours($clinic->clinicId, $workHours));

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.work_hours_updated',
            objectType: 'clinic',
            objectId: $clinic->clinicId,
            before: $before->toArray(),
            after: $after->toArray(),
        ));

        return $after;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDepartment(string $clinicId, string $departmentId, array $attributes): DepartmentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $department = $this->departmentOrFail($clinicId, $departmentId);
        $normalized = $this->normalizedDepartmentPatchAttributes($attributes);
        $updates = [];

        if (array_key_exists('code', $normalized) && is_string($normalized['code']) && $normalized['code'] !== $department->code) {
            if ($this->clinicRepository->departmentCodeExists($tenantId, $clinicId, $normalized['code'], $departmentId)) {
                throw new ConflictHttpException('A department with this code already exists in the selected clinic.');
            }

            $updates['code'] = $normalized['code'];
        }

        foreach (['name', 'description', 'phone_extension'] as $key) {
            $current = $this->departmentAttributeValue($department, $key);

            if (! array_key_exists($key, $normalized) || $normalized[$key] === $current) {
                continue;
            }

            $updates[$key] = $normalized[$key];
        }

        if ($updates === []) {
            return $department;
        }

        $this->clinicRepository->updateDepartment($tenantId, $clinicId, $departmentId, $updates);
        $updated = $this->departmentOrFail($clinicId, $departmentId);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.department_updated',
            objectType: 'clinic_department',
            objectId: $departmentId,
            before: $department->toArray(),
            after: $updated->toArray(),
            metadata: ['clinic_id' => $clinicId],
        ));

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRoom(string $clinicId, string $roomId, array $attributes): RoomData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $room = $this->roomOrFail($clinicId, $roomId);
        $normalized = $this->normalizedRoomPatchAttributes($attributes);
        $updates = [];

        if (array_key_exists('code', $normalized) && is_string($normalized['code']) && $normalized['code'] !== $room->code) {
            if ($this->clinicRepository->roomCodeExists($tenantId, $clinicId, $normalized['code'], $roomId)) {
                throw new ConflictHttpException('A room with this code already exists in the selected clinic.');
            }

            $updates['code'] = $normalized['code'];
        }

        $departmentId = array_key_exists('department_id', $normalized)
            ? (is_string($normalized['department_id']) ? $normalized['department_id'] : null)
            : $room->departmentId;
        $this->assertRoomDepartment($clinicId, $departmentId);

        foreach (['department_id', 'name', 'type', 'floor', 'capacity', 'notes'] as $key) {
            $current = $this->roomAttributeValue($room, $key);

            if (! array_key_exists($key, $normalized) || $normalized[$key] === $current) {
                continue;
            }

            $updates[$key] = $normalized[$key];
        }

        if ($updates === []) {
            return $room;
        }

        $this->clinicRepository->updateRoom($tenantId, $clinicId, $roomId, $updates);
        $updated = $this->roomOrFail($clinicId, $roomId);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'clinics.room_updated',
            objectType: 'clinic_room',
            objectId: $roomId,
            before: $room->toArray(),
            after: $updated->toArray(),
            metadata: ['clinic_id' => $clinicId],
        ));

        return $updated;
    }

    private function assertLocationConsistency(?string $cityCode, ?string $districtCode): void
    {
        if ($cityCode === null && $districtCode !== null) {
            throw new UnprocessableEntityHttpException('District codes require a city code.');
        }

        if ($cityCode !== null && ! $this->locationReferenceRepository->cityExists($cityCode)) {
            throw new UnprocessableEntityHttpException('The selected city does not exist in the approved location catalog.');
        }

        if ($districtCode !== null) {
            /** @var string $cityCode */
            if (! $this->locationReferenceRepository->districtBelongsToCity($districtCode, $cityCode)) {
                throw new UnprocessableEntityHttpException('The selected district does not belong to the selected city.');
            }
        }
    }

    private function assertRoomDepartment(string $clinicId, ?string $departmentId): void
    {
        if ($departmentId === null) {
            return;
        }

        $tenantId = $this->tenantContext->requireTenantId();

        if (! $this->clinicRepository->departmentExists($tenantId, $clinicId, $departmentId)) {
            throw new UnprocessableEntityHttpException('The selected department does not belong to the selected clinic.');
        }
    }

    private function clinicAttributeValue(ClinicData $clinic, string $key): ?string
    {
        return match ($key) {
            'contact_email' => $clinic->contactEmail,
            'contact_phone' => $clinic->contactPhone,
            'city_code' => $clinic->cityCode,
            'district_code' => $clinic->districtCode,
            'address_line_1' => $clinic->addressLine1,
            'address_line_2' => $clinic->addressLine2,
            'postal_code' => $clinic->postalCode,
            'notes' => $clinic->notes,
            default => null,
        };
    }

    private function clinicOrFail(string $clinicId): ClinicData
    {
        $clinic = $this->clinicRepository->findClinic($this->tenantContext->requireTenantId(), $clinicId);

        if ($clinic === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $clinic;
    }

    private function departmentAttributeValue(DepartmentData $department, string $key): ?string
    {
        return match ($key) {
            'name' => $department->name,
            'description' => $department->description,
            'phone_extension' => $department->phoneExtension,
            default => null,
        };
    }

    private function departmentOrFail(string $clinicId, string $departmentId): DepartmentData
    {
        $department = $this->clinicRepository->findDepartment(
            $this->tenantContext->requireTenantId(),
            $clinicId,
            $departmentId,
        );

        if ($department === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $department;
    }

    private function holidayOrFail(string $clinicId, string $holidayId): ClinicHolidayData
    {
        $holiday = $this->clinicRepository->findHoliday(
            $this->tenantContext->requireTenantId(),
            $clinicId,
            $holidayId,
        );

        if ($holiday === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $holiday;
    }

    private function normalizedCode(mixed $value, bool $uppercase = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim($this->requiredString($value));

        if ($string === '') {
            return null;
        }

        return $uppercase ? strtoupper($string) : strtolower($string);
    }

    private function normalizedEmail(mixed $value): ?string
    {
        $string = $this->nullableTrimmedString($value);

        return $string !== null ? strtolower($string) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{name: string, start_date: string, end_date: string, is_closed: bool, notes: string|null}
     */
    private function normalizedHolidayAttributes(array $attributes): array
    {
        $startDate = $this->requiredString($attributes['start_date'] ?? null);
        $endDate = $this->requiredString($attributes['end_date'] ?? null);

        if ($startDate > $endDate) {
            throw new UnprocessableEntityHttpException('Holiday start dates must be on or before the end date.');
        }

        return [
            'name' => trim($this->requiredString($attributes['name'] ?? null)),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_closed' => (bool) ($attributes['is_closed'] ?? true),
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
        ];
    }

    private function normalizedRoomType(mixed $value): string
    {
        $type = strtolower(trim($this->requiredString($value)));

        if (! in_array($type, RoomType::all(), true)) {
            throw new UnprocessableEntityHttpException('The selected room type is not supported.');
        }

        return $type;
    }

    private function normalizedSettings(ClinicSettingsData $settings): ClinicSettingsData
    {
        $timezone = $settings->timezone !== null ? trim($settings->timezone) : null;

        if ($timezone !== null && $timezone !== '' && ! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new UnprocessableEntityHttpException('The selected clinic timezone is invalid.');
        }

        return new ClinicSettingsData(
            timezone: $timezone !== '' ? $timezone : null,
            defaultAppointmentDurationMinutes: $settings->defaultAppointmentDurationMinutes,
            slotIntervalMinutes: $settings->slotIntervalMinutes,
            allowWalkIns: $settings->allowWalkIns,
            requireAppointmentConfirmation: $settings->requireAppointmentConfirmation,
            telemedicineEnabled: $settings->telemedicineEnabled,
        );
    }

    private function normalizedWorkHours(string $clinicId, ClinicWorkHoursData $workHours): ClinicWorkHoursData
    {
        $days = [];

        foreach ($this->weekdays() as $day) {
            $intervals = $workHours->days[$day] ?? [];
            $days[$day] = $this->normalizedDayIntervals($day, $intervals);
        }

        return new ClinicWorkHoursData(
            clinicId: $clinicId,
            days: $days,
        );
    }

    /**
     * @param  list<mixed>  $intervals
     * @return list<array{start_time: string, end_time: string}>
     */
    private function normalizedDayIntervals(string $day, array $intervals): array
    {
        /** @var list<array{start_time: string, end_time: string}> $normalized */
        $normalized = [];

        foreach ($intervals as $interval) {
            if (! is_array($interval)) {
                throw new UnprocessableEntityHttpException("Each {$day} interval must be an object.");
            }

            $start = $this->requiredString($interval['start_time'] ?? null);
            $end = $this->requiredString($interval['end_time'] ?? null);

            if (! $this->isTime($start) || ! $this->isTime($end)) {
                throw new UnprocessableEntityHttpException("Each {$day} interval must use HH:MM 24-hour times.");
            }

            if ($start >= $end) {
                throw new UnprocessableEntityHttpException("Each {$day} interval must end after it starts.");
            }

            $normalized[] = [
                'start_time' => $start,
                'end_time' => $end,
            ];
        }

        usort(
            $normalized,
            static fn (array $left, array $right): int => strcmp(
                (string) $left['start_time'],
                (string) $right['start_time'],
            ),
        );

        $lastEnd = null;

        foreach ($normalized as $interval) {
            if ($lastEnd !== null && $interval['start_time'] < $lastEnd) {
                throw new UnprocessableEntityHttpException("Intervals for {$day} must not overlap.");
            }

            $lastEnd = $interval['end_time'];
        }

        return $normalized;
    }

    private function requiredString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function roomAttributeValue(RoomData $room, string $key): string|int|null
    {
        return match ($key) {
            'department_id' => $room->departmentId,
            'name' => $room->name,
            'type' => $room->type,
            'floor' => $room->floor,
            'capacity' => $room->capacity,
            'notes' => $room->notes,
            default => null,
        };
    }

    private function roomOrFail(string $clinicId, string $roomId): RoomData
    {
        $room = $this->clinicRepository->findRoom(
            $this->tenantContext->requireTenantId(),
            $clinicId,
            $roomId,
        );

        if ($room === null) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $room;
    }

    private function normalizedCapacity(mixed $value): int
    {
        if (! is_numeric($value)) {
            throw new UnprocessableEntityHttpException('Room capacity must be a positive integer.');
        }

        $capacity = (int) $value;

        if ($capacity < 1) {
            throw new UnprocessableEntityHttpException('Room capacity must be a positive integer.');
        }

        return $capacity;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *   code: string,
     *   name: string,
     *   contact_email: string|null,
     *   contact_phone: string|null,
     *   city_code: string|null,
     *   district_code: string|null,
     *   address_line_1: string|null,
     *   address_line_2: string|null,
     *   postal_code: string|null,
     *   notes: string|null
     * }
     */
    private function normalizedClinicCreateAttributes(array $attributes): array
    {
        return [
            'code' => $this->normalizedCode($attributes['code'] ?? null) ?? '',
            'name' => trim($this->requiredString($attributes['name'] ?? null)),
            'contact_email' => $this->normalizedEmail($attributes['contact_email'] ?? null),
            'contact_phone' => $this->nullableTrimmedString($attributes['contact_phone'] ?? null),
            'city_code' => $this->normalizedCode($attributes['city_code'] ?? null, false),
            'district_code' => $this->normalizedCode($attributes['district_code'] ?? null, false),
            'address_line_1' => $this->nullableTrimmedString($attributes['address_line_1'] ?? null),
            'address_line_2' => $this->nullableTrimmedString($attributes['address_line_2'] ?? null),
            'postal_code' => $this->nullableTrimmedString($attributes['postal_code'] ?? null),
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    private function normalizedClinicPatchAttributes(array $attributes): array
    {
        $normalized = [];

        foreach (['code', 'name', 'contact_email', 'contact_phone', 'city_code', 'district_code', 'address_line_1', 'address_line_2', 'postal_code', 'notes'] as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $normalized[$key] = match ($key) {
                'code' => $this->normalizedCode($attributes[$key]),
                'name' => trim($this->requiredString($attributes[$key])),
                'contact_email' => $this->normalizedEmail($attributes[$key]),
                'city_code', 'district_code' => $this->normalizedCode($attributes[$key], false),
                default => $this->nullableTrimmedString($attributes[$key]),
            };
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{code: string, name: string, description: string|null, phone_extension: string|null}
     */
    private function normalizedDepartmentCreateAttributes(array $attributes): array
    {
        return [
            'code' => $this->normalizedCode($attributes['code'] ?? null) ?? '',
            'name' => trim($this->requiredString($attributes['name'] ?? null)),
            'description' => $this->nullableTrimmedString($attributes['description'] ?? null),
            'phone_extension' => $this->nullableTrimmedString($attributes['phone_extension'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    private function normalizedDepartmentPatchAttributes(array $attributes): array
    {
        $normalized = [];

        foreach (['code', 'name', 'description', 'phone_extension'] as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $normalized[$key] = match ($key) {
                'code' => $this->normalizedCode($attributes[$key]),
                'name' => trim($this->requiredString($attributes[$key])),
                default => $this->nullableTrimmedString($attributes[$key]),
            };
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *   department_id: string|null,
     *   code: string,
     *   name: string,
     *   type: string,
     *   floor: string|null,
     *   capacity: int,
     *   notes: string|null
     * }
     */
    private function normalizedRoomCreateAttributes(array $attributes): array
    {
        return [
            'department_id' => $this->nullableTrimmedString($attributes['department_id'] ?? null),
            'code' => $this->normalizedCode($attributes['code'] ?? null) ?? '',
            'name' => trim($this->requiredString($attributes['name'] ?? null)),
            'type' => $this->normalizedRoomType($attributes['type'] ?? RoomType::OTHER),
            'floor' => $this->nullableTrimmedString($attributes['floor'] ?? null),
            'capacity' => $this->normalizedCapacity($attributes['capacity'] ?? 1),
            'notes' => $this->nullableTrimmedString($attributes['notes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     */
    private function normalizedRoomPatchAttributes(array $attributes): array
    {
        $normalized = [];

        foreach (['department_id', 'code', 'name', 'type', 'floor', 'capacity', 'notes'] as $key) {
            if (! array_key_exists($key, $attributes)) {
                continue;
            }

            $normalized[$key] = match ($key) {
                'department_id' => $this->nullableTrimmedString($attributes[$key]),
                'code' => $this->normalizedCode($attributes[$key]),
                'name' => trim($this->requiredString($attributes[$key])),
                'type' => $this->normalizedRoomType($attributes[$key]),
                'capacity' => $this->normalizedCapacity($attributes[$key]),
                default => $this->nullableTrimmedString($attributes[$key]),
            };
        }

        return $normalized;
    }

    private function transitionClinic(string $clinicId, string $targetStatus, string $auditAction): ClinicData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $clinic = $this->clinicOrFail($clinicId);

        $allowed = $targetStatus === ClinicStatus::ACTIVE
            ? ClinicStatus::canActivate($clinic->status)
            : ClinicStatus::canDeactivate($clinic->status);

        if (! $allowed) {
            throw new ConflictHttpException('The requested clinic lifecycle transition is not allowed.');
        }

        $attributes = [
            'status' => $targetStatus,
            'activated_at' => $targetStatus === ClinicStatus::ACTIVE ? now() : null,
            'deactivated_at' => $targetStatus === ClinicStatus::INACTIVE ? now() : null,
        ];

        $this->clinicRepository->updateClinic($tenantId, $clinic->clinicId, $attributes);
        $updated = $this->clinicOrFail($clinicId);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $auditAction,
            objectType: 'clinic',
            objectId: $updated->clinicId,
            before: $clinic->toArray(),
            after: $updated->toArray(),
        ));

        return $updated;
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim($this->requiredString($value));

        return $string !== '' ? $string : null;
    }

    /**
     * @return list<string>
     */
    private function weekdays(): array
    {
        return [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ];
    }

    private function isTime(string $value): bool
    {
        return preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9]$/', $value) === 1;
    }
}
