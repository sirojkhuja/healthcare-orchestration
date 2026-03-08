<?php

namespace App\Modules\TenantManagement\Application\Data;

use Carbon\CarbonImmutable;

final readonly class TenantLimitsData
{
    public function __construct(
        public ?int $users = null,
        public ?int $clinics = null,
        public ?int $providers = null,
        public ?int $patients = null,
        public ?float $storageGb = null,
        public ?int $monthlyNotifications = null,
        public ?CarbonImmutable $updatedAt = null,
    ) {}

    /**
     * @return array{
     *     users: int|null,
     *     clinics: int|null,
     *     providers: int|null,
     *     patients: int|null,
     *     storage_gb: float|null,
     *     monthly_notifications: int|null,
     *     updated_at: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'users' => $this->users,
            'clinics' => $this->clinics,
            'providers' => $this->providers,
            'patients' => $this->patients,
            'storage_gb' => $this->storageGb,
            'monthly_notifications' => $this->monthlyNotifications,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
