<?php

namespace App\Modules\Patient\Application\Data;

use Carbon\CarbonImmutable;

final readonly class PatientDocumentData
{
    public function __construct(
        public string $documentId,
        public string $patientId,
        public string $title,
        public ?string $documentType,
        public string $storageDisk,
        public string $storagePath,
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
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
            'id' => $this->documentId,
            'patient_id' => $this->patientId,
            'title' => $this->title,
            'document_type' => $this->documentType,
            'file_name' => $this->fileName,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'uploaded_at' => $this->uploadedAt->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
        ];
    }
}
