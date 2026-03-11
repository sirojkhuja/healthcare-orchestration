<?php

namespace App\Modules\Insurance\Application\Contracts;

use App\Modules\Insurance\Application\Data\ClaimStoredAttachmentData;
use Illuminate\Http\UploadedFile;

interface ClaimAttachmentStore
{
    public function storeForClaim(string $tenantId, string $claimId, UploadedFile $file): ClaimStoredAttachmentData;

    public function delete(string $disk, string $path): void;
}
