<?php

namespace App\Modules\IdentityAccess\Application\Data;

use Carbon\CarbonImmutable;

final readonly class ProfileAvatarData
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $fileName,
        public string $mimeType,
        public int $sizeBytes,
        public CarbonImmutable $uploadedAt,
    ) {}

    /**
     * @return array{
     *     file_name: string,
     *     mime_type: string,
     *     size_bytes: int,
     *     uploaded_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'file_name' => $this->fileName,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'uploaded_at' => $this->uploadedAt->toIso8601String(),
        ];
    }
}
