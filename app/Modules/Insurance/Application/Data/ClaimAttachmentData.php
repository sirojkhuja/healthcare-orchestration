<?php

namespace App\Modules\Insurance\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ClaimAttachmentData
{
    public function __construct(
        public string $attachmentId,
        public string $tenantId,
        public string $claimId,
        public ?string $attachmentType,
        public ?string $notes,
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
        public string $disk,
        public string $path,
        public CarbonImmutable $uploadedAt,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->attachmentId,
            'tenant_id' => $this->tenantId,
            'claim_id' => $this->claimId,
            'attachment_type' => $this->attachmentType,
            'notes' => $this->notes,
            'file_name' => $this->fileName,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'storage' => [
                'disk' => $this->disk,
                'path' => $this->path,
            ],
            'uploaded_at' => $this->uploadedAt->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
