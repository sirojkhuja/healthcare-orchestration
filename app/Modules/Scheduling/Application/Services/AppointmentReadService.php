<?php

namespace App\Modules\Scheduling\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Scheduling\Application\Contracts\AppointmentRepository;
use App\Modules\Scheduling\Application\Data\AppointmentData;
use App\Modules\Scheduling\Application\Data\AppointmentExportData;
use App\Modules\Scheduling\Application\Data\AppointmentSearchCriteria;
use App\Modules\Scheduling\Application\Queries\ExportAppointmentsQuery;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class AppointmentReadService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AuditEventRepository $auditEventRepository,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly FileStorageManager $fileStorageManager,
    ) {}

    /**
     * @return list<AuditEventData>
     */
    public function audit(string $appointmentId, int $limit = 50): array
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointment = $this->appointmentRepository->findInTenant($tenantId, $appointmentId, true);

        if (! $appointment instanceof AppointmentData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $events = $this->auditEventRepository->forObject('appointment', $appointment->appointmentId, $tenantId);

        return array_slice(array_reverse($events), 0, $limit);
    }

    public function export(ExportAppointmentsQuery $query): AppointmentExportData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $appointments = $this->search($query->criteria);
        $generatedAt = CarbonImmutable::now();
        $exportId = (string) Str::uuid();
        $fileName = sprintf('appointments-%s.csv', $generatedAt->format('Ymd-His'));
        $stored = $this->fileStorageManager->storeExport(
            $this->buildCsv($appointments, $generatedAt),
            sprintf('tenants/%s/appointments/exports/%s/%s', $tenantId, $generatedAt->format('Y/m/d'), $fileName),
        );
        $export = new AppointmentExportData(
            exportId: $exportId,
            format: $query->format,
            fileName: $fileName,
            rowCount: count($appointments),
            generatedAt: $generatedAt,
            filters: $query->criteria,
            disk: $stored->disk,
            path: $stored->path,
            visibility: $stored->visibility,
        );

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'appointments.exported',
            objectType: 'appointment_export',
            objectId: $exportId,
            after: $export->toArray(),
            metadata: [
                'tenant_id' => $tenantId,
                'filters' => $query->criteria->toArray(),
            ],
        ));

        return $export;
    }

    /**
     * @return list<AppointmentData>
     */
    public function search(AppointmentSearchCriteria $criteria): array
    {
        $appointments = $this->appointmentRepository->search(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );

        if (! $criteria->hasQuery()) {
            return array_slice($appointments, 0, $criteria->limit);
        }

        usort(
            $appointments,
            fn (AppointmentData $left, AppointmentData $right): int => $this->compareAppointments($left, $right, $criteria),
        );

        return array_slice($appointments, 0, $criteria->limit);
    }

    private function compareAppointments(
        AppointmentData $left,
        AppointmentData $right,
        AppointmentSearchCriteria $criteria,
    ): int {
        $scoreDiff = $this->matchScore($right, $criteria) <=> $this->matchScore($left, $criteria);

        if ($scoreDiff !== 0) {
            return $scoreDiff;
        }

        return [
            $left->scheduledStartAt->toIso8601String(),
            $left->createdAt->toIso8601String(),
            $left->appointmentId,
        ] <=> [
            $right->scheduledStartAt->toIso8601String(),
            $right->createdAt->toIso8601String(),
            $right->appointmentId,
        ];
    }

    private function matchScore(AppointmentData $appointment, AppointmentSearchCriteria $criteria): int
    {
        $query = $criteria->normalizedQuery();

        if ($query === null) {
            return 0;
        }

        $score = $this->fieldScore($this->normalize($appointment->appointmentId), $query, 220, 180, 120);

        foreach ([
            $appointment->patientDisplayName,
            $appointment->providerDisplayName,
        ] as $field) {
            $score += $this->fieldScore($this->normalize($field), $query, 150, 110, 70);
        }

        foreach ($criteria->tokens() as $token) {
            foreach ([
                $appointment->appointmentId,
                $appointment->patientDisplayName,
                $appointment->providerDisplayName,
            ] as $field) {
                $score += $this->fieldScore($this->normalize($field), $token, 40, 25, 10);
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

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * @param  list<AppointmentData>  $appointments
     */
    private function buildCsv(array $appointments, CarbonImmutable $generatedAt): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new UnprocessableEntityHttpException('Appointment export could not allocate a CSV stream.');
        }

        fputcsv($stream, [
            'id',
            'tenant_id',
            'status',
            'patient_id',
            'patient_name',
            'provider_id',
            'provider_name',
            'clinic_id',
            'clinic_name',
            'room_id',
            'room_name',
            'scheduled_start_at',
            'scheduled_end_at',
            'timezone',
            'created_at',
            'updated_at',
            'exported_at',
        ]);

        foreach ($appointments as $appointment) {
            fputcsv($stream, [
                $appointment->appointmentId,
                $appointment->tenantId,
                $appointment->status,
                $appointment->patientId,
                $appointment->patientDisplayName,
                $appointment->providerId,
                $appointment->providerDisplayName,
                $appointment->clinicId,
                $appointment->clinicName,
                $appointment->roomId,
                $appointment->roomName,
                $appointment->scheduledStartAt->toIso8601String(),
                $appointment->scheduledEndAt->toIso8601String(),
                $appointment->timezone,
                $appointment->createdAt->toIso8601String(),
                $appointment->updatedAt->toIso8601String(),
                $generatedAt->toIso8601String(),
            ]);
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (! is_string($contents)) {
            throw new UnprocessableEntityHttpException('Appointment export could not be generated.');
        }

        return $contents;
    }
}
