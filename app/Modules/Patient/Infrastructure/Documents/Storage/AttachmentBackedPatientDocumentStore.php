<?php

namespace App\Modules\Patient\Infrastructure\Documents\Storage;

use App\Modules\Patient\Application\Contracts\PatientDocumentStore;
use App\Modules\Patient\Application\Data\PatientStoredDocumentData;
use App\Shared\Application\Contracts\FileStorageManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class AttachmentBackedPatientDocumentStore implements PatientDocumentStore
{
    public function __construct(
        private readonly FileStorageManager $fileStorageManager,
    ) {}

    #[\Override]
    public function storeForPatient(string $tenantId, string $patientId, UploadedFile $file): PatientStoredDocumentData
    {
        /** @var string $suffix */
        $suffix = Str::random(12);
        $storedFile = $this->fileStorageManager->storeAttachment(
            $file,
            sprintf(
                'tenants/%s/patients/%s/documents/%s/%s',
                $tenantId,
                $patientId,
                CarbonImmutable::now()->format('Y/m/d'),
                strtolower($suffix),
            ),
        );
        $sizeBytes = $file->getSize();
        $originalName = trim($file->getClientOriginalName());

        return new PatientStoredDocumentData(
            disk: $storedFile->disk,
            path: $storedFile->path,
            fileName: $originalName !== '' ? $originalName : basename($storedFile->path),
            mimeType: $file->getClientMimeType() ?: ($file->getMimeType() ?? 'application/octet-stream'),
            sizeBytes: is_int($sizeBytes) && $sizeBytes >= 0 ? $sizeBytes : 0,
            uploadedAt: CarbonImmutable::now(),
        );
    }

    #[\Override]
    public function delete(string $disk, string $path): void
    {
        if ($disk === '' || $path === '') {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable) {
            // Best-effort deletion is mandated by ADR-019.
        }
    }
}
