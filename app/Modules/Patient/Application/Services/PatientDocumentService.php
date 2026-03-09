<?php

namespace App\Modules\Patient\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Patient\Application\Contracts\PatientDocumentRepository;
use App\Modules\Patient\Application\Contracts\PatientDocumentStore;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Application\Data\PatientDocumentData;
use App\Shared\Application\Contracts\TenantContext;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class PatientDocumentService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly PatientDocumentRepository $patientDocumentRepository,
        private readonly PatientDocumentStore $patientDocumentStore,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function delete(string $patientId, string $documentId): PatientDocumentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $document = $this->documentOrFail($patient->patientId, $documentId);

        if (! $this->patientDocumentRepository->delete($tenantId, $patient->patientId, $document->documentId)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $this->patientDocumentStore->delete($document->storageDisk, $document->storagePath);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.document_deleted',
            objectType: 'patient',
            objectId: $patient->patientId,
            before: ['document' => $document->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'document_id' => $document->documentId,
            ],
        ));

        return $document;
    }

    public function get(string $patientId, string $documentId): PatientDocumentData
    {
        $patient = $this->patientOrFail($patientId);

        return $this->documentOrFail($patient->patientId, $documentId);
    }

    /**
     * @return list<PatientDocumentData>
     */
    public function list(string $patientId): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);

        return $this->patientDocumentRepository->listForPatient($tenantId, $patient->patientId);
    }

    public function upload(string $patientId, UploadedFile $file, ?string $title, ?string $documentType): PatientDocumentData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($patientId);
        $storedDocument = $this->patientDocumentStore->storeForPatient($tenantId, $patient->patientId, $file);

        try {
            $document = $this->patientDocumentRepository->create(
                tenantId: $tenantId,
                patientId: $patient->patientId,
                title: $this->normalizedTitle($title, $storedDocument->fileName),
                documentType: $this->nullableString($documentType),
                storedDocument: $storedDocument,
            );
        } catch (Throwable $throwable) {
            $this->patientDocumentStore->delete($storedDocument->disk, $storedDocument->path);

            throw $throwable;
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.document_uploaded',
            objectType: 'patient',
            objectId: $patient->patientId,
            after: ['document' => $document->toArray()],
            metadata: [
                'patient_id' => $patient->patientId,
                'document_id' => $document->documentId,
            ],
        ));

        return $document;
    }

    private function documentOrFail(string $patientId, string $documentId): PatientDocumentData
    {
        $document = $this->patientDocumentRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
            $documentId,
        );

        if (! $document instanceof PatientDocumentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $document;
    }

    private function normalizedTitle(?string $title, string $fallback): string
    {
        $normalizedTitle = $this->nullableString($title);

        return $normalizedTitle ?? $fallback;
    }

    private function nullableString(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function patientOrFail(string $patientId): PatientData
    {
        $patient = $this->patientRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
        );

        if (! $patient instanceof PatientData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $patient;
    }
}
