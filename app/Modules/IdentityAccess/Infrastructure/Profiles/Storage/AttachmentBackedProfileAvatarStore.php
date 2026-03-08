<?php

namespace App\Modules\IdentityAccess\Infrastructure\Profiles\Storage;

use App\Modules\IdentityAccess\Application\Contracts\ProfileAvatarStore;
use App\Modules\IdentityAccess\Application\Data\ProfileAvatarData;
use App\Shared\Application\Contracts\FileStorageManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class AttachmentBackedProfileAvatarStore implements ProfileAvatarStore
{
    public function __construct(
        private readonly FileStorageManager $fileStorageManager,
    ) {}

    #[\Override]
    public function storeForUser(string $userId, UploadedFile $file): ProfileAvatarData
    {
        $storedFile = $this->fileStorageManager->storeAttachment($file, sprintf('profiles/%s/avatar', $userId));
        $sizeBytes = $file->getSize();

        return new ProfileAvatarData(
            disk: $storedFile->disk,
            path: $storedFile->path,
            fileName: $file->getClientOriginalName() !== '' ? $file->getClientOriginalName() : basename($storedFile->path),
            mimeType: $file->getClientMimeType() ?: ($file->getMimeType() ?? 'application/octet-stream'),
            sizeBytes: is_int($sizeBytes) && $sizeBytes >= 0 ? $sizeBytes : 0,
            uploadedAt: CarbonImmutable::now(),
        );
    }

    #[\Override]
    public function delete(?ProfileAvatarData $avatar): void
    {
        if ($avatar === null || $avatar->disk === '' || $avatar->path === '') {
            return;
        }

        Storage::disk($avatar->disk)->delete($avatar->path);
    }
}
