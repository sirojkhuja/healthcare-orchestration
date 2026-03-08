<?php

namespace App\Modules\TenantManagement\Application\Data;

final readonly class TenantUsageData
{
    public function __construct(
        public string $tenantId,
        public int $usersUsed,
        public ?int $usersLimit,
        public int $clinicsUsed,
        public ?int $clinicsLimit,
        public int $providersUsed,
        public ?int $providersLimit,
        public int $patientsUsed,
        public ?int $patientsLimit,
        public float $storageGbUsed,
        public ?float $storageGbLimit,
        public int $monthlyNotificationsUsed,
        public ?int $monthlyNotificationsLimit,
    ) {}

    /**
     * @return array{
     *     tenant_id: string,
     *     users: array{used: int, limit: int|null, remaining: int|null},
     *     clinics: array{used: int, limit: int|null, remaining: int|null},
     *     providers: array{used: int, limit: int|null, remaining: int|null},
     *     patients: array{used: int, limit: int|null, remaining: int|null},
     *     storage_gb: array{used: float, limit: float|null, remaining: float|null},
     *     monthly_notifications: array{used: int, limit: int|null, remaining: int|null}
     * }
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'users' => $this->intMetric($this->usersUsed, $this->usersLimit),
            'clinics' => $this->intMetric($this->clinicsUsed, $this->clinicsLimit),
            'providers' => $this->intMetric($this->providersUsed, $this->providersLimit),
            'patients' => $this->intMetric($this->patientsUsed, $this->patientsLimit),
            'storage_gb' => $this->floatMetric($this->storageGbUsed, $this->storageGbLimit),
            'monthly_notifications' => $this->intMetric($this->monthlyNotificationsUsed, $this->monthlyNotificationsLimit),
        ];
    }

    /**
     * @return array{used: float, limit: float|null, remaining: float|null}
     */
    private function floatMetric(float $used, ?float $limit): array
    {
        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : $limit - $used,
        ];
    }

    /**
     * @return array{used: int, limit: int|null, remaining: int|null}
     */
    private function intMetric(int $used, ?int $limit): array
    {
        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : $limit - $used,
        ];
    }
}
