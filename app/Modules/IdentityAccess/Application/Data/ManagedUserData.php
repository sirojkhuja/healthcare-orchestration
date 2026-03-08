<?php

namespace App\Modules\IdentityAccess\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ManagedUserData
{
    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $name,
        public string $email,
        public string $status,
        public ?CarbonImmutable $emailVerifiedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public CarbonImmutable $joinedAt,
        public CarbonImmutable $membershipUpdatedAt,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     tenant_id: string,
     *     name: string,
     *     email: string,
     *     status: string,
     *     email_verified_at: string|null,
     *     created_at: string,
     *     updated_at: string,
     *     joined_at: string,
     *     membership_updated_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'email_verified_at' => $this->emailVerifiedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
            'joined_at' => $this->joinedAt->toIso8601String(),
            'membership_updated_at' => $this->membershipUpdatedAt->toIso8601String(),
        ];
    }
}
