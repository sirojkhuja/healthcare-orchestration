<?php

namespace App\Modules\Patient\Infrastructure\Persistence;

use App\Modules\Patient\Application\Contracts\PatientDocumentRepository;
use App\Modules\Patient\Application\Data\PatientDocumentData;
use App\Modules\Patient\Application\Data\PatientStoredDocumentData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePatientDocumentRepository implements PatientDocumentRepository
{
    #[\Override]
    public function create(
        string $tenantId,
        string $patientId,
        string $title,
        ?string $documentType,
        PatientStoredDocumentData $storedDocument,
    ): PatientDocumentData {
        $documentId = (string) Str::uuid();

        DB::table('patient_documents')->insert([
            'id' => $documentId,
            'tenant_id' => $tenantId,
            'patient_id' => $patientId,
            'title' => $title,
            'document_type' => $documentType,
            'storage_disk' => $storedDocument->disk,
            'storage_path' => $storedDocument->path,
            'file_name' => $storedDocument->fileName,
            'mime_type' => $storedDocument->mimeType,
            'size_bytes' => $storedDocument->sizeBytes,
            'uploaded_at' => $storedDocument->uploadedAt,
            'created_at' => $storedDocument->uploadedAt,
            'updated_at' => $storedDocument->uploadedAt,
        ]);

        return $this->findInTenant($tenantId, $patientId, $documentId)
            ?? throw new \LogicException('Created patient document could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $patientId, string $documentId): bool
    {
        return DB::table('patient_documents')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $documentId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $patientId, string $documentId): ?PatientDocumentData
    {
        $row = DB::table('patient_documents')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->where('id', $documentId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForPatient(string $tenantId, string $patientId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('patient_documents')
            ->where('tenant_id', $tenantId)
            ->where('patient_id', $patientId)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    private function toData(stdClass $row): PatientDocumentData
    {
        return new PatientDocumentData(
            documentId: $this->stringValue($row->id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            title: $this->stringValue($row->title ?? null),
            documentType: $this->nullableString($row->document_type ?? null),
            storageDisk: $this->stringValue($row->storage_disk ?? null),
            storagePath: $this->stringValue($row->storage_path ?? null),
            fileName: $this->stringValue($row->file_name ?? null),
            mimeType: $this->stringValue($row->mime_type ?? null),
            sizeBytes: $this->intValue($row->size_bytes ?? null),
            uploadedAt: $this->dateTime($row->uploaded_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function dateTime(mixed $value): CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($this->stringValue($value));
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
