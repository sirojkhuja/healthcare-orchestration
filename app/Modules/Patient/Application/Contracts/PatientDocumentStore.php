<?php

namespace App\Modules\Patient\Application\Contracts;

use App\Modules\Patient\Application\Data\PatientStoredDocumentData;
use Illuminate\Http\UploadedFile;

interface PatientDocumentStore
{
    public function storeForPatient(string $tenantId, string $patientId, UploadedFile $file): PatientStoredDocumentData;

    public function delete(string $disk, string $path): void;
}
