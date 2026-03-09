<?php

namespace App\Modules\Patient\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Application\Data\PatientExportData;
use App\Modules\Patient\Application\Data\PatientSearchCriteria;
use App\Modules\Patient\Application\Data\PatientSummaryData;
use App\Modules\Patient\Application\Queries\ExportPatientsQuery;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PatientReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly AuditEventRepository $auditEventRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly FileStorageManager $fileStorageManager,
    ) {}

    /**
     * @return list<PatientData>
     */
    public function search(PatientSearchCriteria $criteria): array
    {
        $patients = $this->patientRepository->search(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );

        if (! $criteria->hasQuery()) {
            return array_slice($patients, 0, $criteria->limit);
        }

        usort($patients, fn (PatientData $left, PatientData $right): int => $this->comparePatients($left, $right, $criteria));

        return array_slice($patients, 0, $criteria->limit);
    }

    public function summary(string $patientId): PatientSummaryData
    {
        $patient = $this->patientOrFail($patientId);
        $timeline = $this->auditEventRepository->forObject('patient', $patientId, $patient->tenantId);
        $latestEvent = $timeline === [] ? null : $timeline[array_key_last($timeline)];

        return new PatientSummaryData(
            patient: $patient,
            displayName: $this->displayName($patient),
            initials: $this->initials($patient),
            ageYears: (int) $patient->birthDate->diffInYears(CarbonImmutable::now()),
            directoryStatus: 'active',
            timelineEventCount: count($timeline),
            lastActivityAt: $latestEvent instanceof AuditEventData
                ? $latestEvent->occurredAt
                : $patient->updatedAt,
        );
    }

    /**
     * @return list<AuditEventData>
     */
    public function timeline(string $patientId, int $limit = 50): array
    {
        $patient = $this->patientOrFail($patientId);
        $events = $this->auditEventRepository->forObject('patient', $patient->patientId, $patient->tenantId);

        return array_slice(array_reverse($events), 0, $limit);
    }

    public function export(ExportPatientsQuery $query): PatientExportData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patients = $this->search($query->criteria);
        $generatedAt = CarbonImmutable::now();
        $exportId = (string) Str::uuid();
        $fileName = sprintf('patients-%s.csv', $generatedAt->format('Ymd-His'));
        $stored = $this->fileStorageManager->storeExport(
            $this->buildCsv($patients, $generatedAt),
            sprintf('tenants/%s/patients/exports/%s/%s', $tenantId, $generatedAt->format('Y/m/d'), $fileName),
        );
        $export = new PatientExportData(
            exportId: $exportId,
            format: $query->format,
            fileName: $fileName,
            rowCount: count($patients),
            generatedAt: $generatedAt,
            filters: $query->criteria,
            disk: $stored->disk,
            path: $stored->path,
            visibility: $stored->visibility,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'patients.exported',
            objectType: 'patient_export',
            objectId: $exportId,
            after: $export->toArray(),
            metadata: [
                'tenant_id' => $tenantId,
                'filters' => $query->criteria->toArray(),
            ],
        ));

        return $export;
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

    private function comparePatients(PatientData $left, PatientData $right, PatientSearchCriteria $criteria): int
    {
        $scoreDiff = $this->matchScore($right, $criteria) <=> $this->matchScore($left, $criteria);

        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        return [
            $left->lastName,
            $left->firstName,
            $left->createdAt->toIso8601String(),
        ] <=> [
            $right->lastName,
            $right->firstName,
            $right->createdAt->toIso8601String(),
        ];
    }

    private function matchScore(PatientData $patient, PatientSearchCriteria $criteria): int
    {
        $query = $criteria->normalizedQuery();

        if ($query === null) {
            return 0;
        }

        $score = 0;

        foreach ($this->identifierFields($patient) as $field) {
            $score += $this->fieldScore($field, $query, 220, 160, 110);
        }

        foreach ($this->nameFields($patient) as $field) {
            $score += $this->fieldScore($field, $query, 180, 130, 70);
        }

        foreach ($criteria->tokens() as $token) {
            foreach ($this->searchFields($patient) as $field) {
                $score += $this->fieldScore($field, $token, 30, 20, 10);
            }
        }

        return $score;
    }

    private function fieldScore(string $field, string $needle, int $exact, int $prefix, int $contains): int
    {
        if ($field === '') {
            return 0;
        }

        if ($field === $needle) {
            return $exact;
        }

        if (str_starts_with($field, $needle)) {
            return $prefix;
        }

        return str_contains($field, $needle) ? $contains : 0;
    }

    /**
     * @return list<string>
     */
    private function identifierFields(PatientData $patient): array
    {
        return [
            $this->normalize($patient->nationalId),
            $this->normalize($patient->email),
            $this->normalizePhone($patient->phone),
        ];
    }

    /**
     * @return list<string>
     */
    private function nameFields(PatientData $patient): array
    {
        return [
            $this->normalize($patient->firstName),
            $this->normalize($patient->lastName),
            $this->normalize($patient->middleName),
            $this->normalize($patient->preferredName),
            $this->normalize($this->displayName($patient)),
        ];
    }

    /**
     * @return list<string>
     */
    private function searchFields(PatientData $patient): array
    {
        return array_merge($this->identifierFields($patient), $this->nameFields($patient));
    }

    private function normalize(?string $value): string
    {
        return $value !== null ? mb_strtolower(trim($value)) : '';
    }

    private function normalizePhone(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $normalized = preg_replace('/\D+/', '', $value);

        return $normalized !== null ? $normalized : '';
    }

    private function displayName(PatientData $patient): string
    {
        $parts = array_filter([
            $patient->preferredName ?? $patient->firstName,
            $patient->lastName,
        ]);

        return implode(' ', $parts);
    }

    private function initials(PatientData $patient): string
    {
        $displayName = $this->displayName($patient);
        $parts = preg_split('/\s+/', $displayName) ?: [];
        $words = array_values(array_filter(
            $parts,
            static fn (string $part): bool => $part !== '',
        ));

        if ($words === []) {
            return '';
        }

        $first = mb_strtoupper(mb_substr($words[0], 0, 1));
        $last = mb_strtoupper(mb_substr($words[array_key_last($words)], 0, 1));

        return $first.$last;
    }

    /**
     * @param  list<PatientData>  $patients
     */
    private function buildCsv(array $patients, CarbonImmutable $generatedAt): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to allocate the export stream.');
        }

        fputcsv($stream, [
            'id',
            'tenant_id',
            'display_name',
            'first_name',
            'last_name',
            'middle_name',
            'preferred_name',
            'sex',
            'birth_date',
            'age_years',
            'national_id',
            'email',
            'phone',
            'city_code',
            'district_code',
            'address_line_1',
            'address_line_2',
            'postal_code',
            'notes',
            'created_at',
            'updated_at',
            'exported_at',
        ]);

        foreach ($patients as $patient) {
            fputcsv($stream, [
                $patient->patientId,
                $patient->tenantId,
                $this->displayName($patient),
                $patient->firstName,
                $patient->lastName,
                $patient->middleName,
                $patient->preferredName,
                $patient->sex,
                $patient->birthDate->toDateString(),
                $patient->birthDate->diffInYears($generatedAt),
                $patient->nationalId,
                $patient->email,
                $patient->phone,
                $patient->cityCode,
                $patient->districtCode,
                $patient->addressLine1,
                $patient->addressLine2,
                $patient->postalCode,
                $patient->notes,
                $patient->createdAt->toIso8601String(),
                $patient->updatedAt->toIso8601String(),
                $generatedAt->toIso8601String(),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new \RuntimeException('Unable to read the generated export stream.');
        }

        return $contents;
    }
}
