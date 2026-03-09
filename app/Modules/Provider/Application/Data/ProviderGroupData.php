<?php

namespace App\Modules\Provider\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProviderGroupData
{
    /**
     * @param  list<ProviderGroupMemberData>  $members
     */
    public function __construct(
        public string $groupId,
        public string $tenantId,
        public string $name,
        public ?string $description,
        public ?string $clinicId,
        public array $members,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return list<string>
     */
    public function memberIds(): array
    {
        return array_map(
            static fn (ProviderGroupMemberData $member): string => $member->providerId,
            $this->members,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->groupId,
            'tenant_id' => $this->tenantId,
            'name' => $this->name,
            'description' => $this->description,
            'clinic_id' => $this->clinicId,
            'member_count' => count($this->members),
            'member_ids' => $this->memberIds(),
            'members' => array_map(
                static fn (ProviderGroupMemberData $member): array => $member->toArray(),
                $this->members,
            ),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
