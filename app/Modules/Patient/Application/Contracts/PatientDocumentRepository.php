<?php

namespace App\Modules\Patient\Application\Contracts;

use App\Modules\Patient\Application\Data\PatientDocumentData;
use App\Modules\Patient\Application\Data\PatientStoredDocumentData;

interface PatientDocumentRepository
{
    public function create(
        string $tenantId,
        string $patientId,
        string $title,
        ?string $documentType,
        PatientStoredDocumentData $storedDocument,
    ): PatientDocumentData;

    public function findInTenant(string $tenantId, string $patientId, string $documentId): ?PatientDocumentData;

    /**
     * @return list<PatientDocumentData>
     */
    public function listForPatient(string $tenantId, string $patientId): array;

    public function delete(string $tenantId, string $patientId, string $documentId): bool;
}
