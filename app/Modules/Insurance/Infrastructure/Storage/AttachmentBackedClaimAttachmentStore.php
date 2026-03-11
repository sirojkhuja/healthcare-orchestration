<?php

namespace App\Modules\Insurance\Infrastructure\Storage;

use App\Modules\Insurance\Application\Contracts\ClaimAttachmentStore;
use App\Modules\Insurance\Application\Data\ClaimStoredAttachmentData;
use App\Shared\Application\Contracts\FileStorageManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AttachmentBackedClaimAttachmentStore implements ClaimAttachmentStore
{
    public function __construct(
        private readonly FileStorageManager $fileStorageManager,
    ) {}

    #[\Override]
    public function delete(string $disk, string $path): void
    {
        if ($disk === '' || $path === '') {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable) {
            // Best-effort deletion is sufficient for claim attachments.
        }
    }

    #[\Override]
    public function storeForClaim(string $tenantId, string $claimId, UploadedFile $file): ClaimStoredAttachmentData
    {
        $stored = $this->fileStorageManager->storeAttachment(
            $file,
            sprintf(
                'tenants/%s/claims/%s/attachments/%s/%s',
                $tenantId,
                $claimId,
                CarbonImmutable::now()->format('Y/m/d'),
                strtolower(Str::random(12)),
            ),
        );
        $sizeBytes = $file->getSize();
        $originalName = trim($file->getClientOriginalName());

        return new ClaimStoredAttachmentData(
            disk: $stored->disk,
            path: $stored->path,
            fileName: $originalName !== '' ? $originalName : basename($stored->path),
            mimeType: $file->getClientMimeType() ?: ($file->getMimeType() ?? 'application/octet-stream'),
            sizeBytes: is_int($sizeBytes) && $sizeBytes >= 0 ? $sizeBytes : 0,
            uploadedAt: CarbonImmutable::now(),
        );
    }
}
