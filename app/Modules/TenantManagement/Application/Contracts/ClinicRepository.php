<?php

namespace App\Modules\TenantManagement\Application\Contracts;

use App\Modules\TenantManagement\Application\Data\ClinicData;
use App\Modules\TenantManagement\Application\Data\ClinicHolidayData;
use App\Modules\TenantManagement\Application\Data\ClinicSettingsData;
use App\Modules\TenantManagement\Application\Data\ClinicWorkHoursData;
use App\Modules\TenantManagement\Application\Data\DepartmentData;
use App\Modules\TenantManagement\Application\Data\RoomData;

interface ClinicRepository
{
    public function clinicCodeExists(string $tenantId, string $code, ?string $exceptClinicId = null): bool;

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
    ): ClinicData;

    public function createDepartment(
        string $tenantId,
        string $clinicId,
        string $code,
        string $name,
        ?string $description,
        ?string $phoneExtension,
    ): DepartmentData;

    public function createHoliday(
        string $tenantId,
        string $clinicId,
        string $name,
        string $startDate,
        string $endDate,
        bool $isClosed,
        ?string $notes,
    ): ClinicHolidayData;

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
    ): RoomData;

    public function deleteClinic(string $tenantId, string $clinicId): bool;

    public function deleteDepartment(string $tenantId, string $clinicId, string $departmentId): bool;

    public function deleteHoliday(string $tenantId, string $clinicId, string $holidayId): bool;

    public function deleteRoom(string $tenantId, string $clinicId, string $roomId): bool;

    public function departmentCodeExists(string $tenantId, string $clinicId, string $code, ?string $exceptDepartmentId = null): bool;

    public function departmentExists(string $tenantId, string $clinicId, string $departmentId): bool;

    public function findClinic(string $tenantId, string $clinicId): ?ClinicData;

    public function findDepartment(string $tenantId, string $clinicId, string $departmentId): ?DepartmentData;

    public function findHoliday(string $tenantId, string $clinicId, string $holidayId): ?ClinicHolidayData;

    public function findRoom(string $tenantId, string $clinicId, string $roomId): ?RoomData;

    public function holidayRangeOverlaps(
        string $tenantId,
        string $clinicId,
        string $startDate,
        string $endDate,
        ?string $exceptHolidayId = null,
    ): bool;

    /**
     * @return list<ClinicData>
     */
    public function listClinics(string $tenantId, ?string $search = null, ?string $status = null): array;

    /**
     * @return list<DepartmentData>
     */
    public function listDepartments(string $tenantId, string $clinicId): array;

    /**
     * @return list<ClinicHolidayData>
     */
    public function listHolidays(string $tenantId, string $clinicId): array;

    /**
     * @return list<RoomData>
     */
    public function listRooms(string $tenantId, string $clinicId): array;

    public function replaceClinicWorkHours(string $tenantId, string $clinicId, ClinicWorkHoursData $workHours): ClinicWorkHoursData;

    public function replaceSettings(string $tenantId, string $clinicId, ClinicSettingsData $settings): ClinicSettingsData;

    public function roomCodeExists(string $tenantId, string $clinicId, string $code, ?string $exceptRoomId = null): bool;

    public function settings(string $tenantId, string $clinicId): ClinicSettingsData;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateClinic(string $tenantId, string $clinicId, array $attributes): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDepartment(string $tenantId, string $clinicId, string $departmentId, array $attributes): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRoom(string $tenantId, string $clinicId, string $roomId, array $attributes): bool;

    public function workHours(string $tenantId, string $clinicId): ClinicWorkHoursData;
}
