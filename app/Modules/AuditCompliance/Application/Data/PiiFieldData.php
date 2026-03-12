<?php

namespace App\Modules\AuditCompliance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PiiFieldData
{
    public function __construct(
        public string $fieldId,
        public string $tenantId,
        public string $objectType,
        public string $fieldPath,
        public string $classification,
        public string $encryptionProfile,
        public int $keyVersion,
        public string $status,
        public ?string $notes,
        public ?CarbonImmutable $lastRotatedAt,
        public ?CarbonImmutable $lastReencryptedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field_id' => $this->fieldId,
            'tenant_id' => $this->tenantId,
            'object_type' => $this->objectType,
            'field_path' => $this->fieldPath,
            'classification' => $this->classification,
            'encryption_profile' => $this->encryptionProfile,
            'key_version' => $this->keyVersion,
            'status' => $this->status,
            'notes' => $this->notes,
            'last_rotated_at' => $this->lastRotatedAt?->toIso8601String(),
            'last_reencrypted_at' => $this->lastReencryptedAt?->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
