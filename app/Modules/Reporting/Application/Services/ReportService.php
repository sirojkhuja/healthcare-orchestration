<?php

namespace App\Modules\Reporting\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\Reporting\Application\Contracts\ReportRepository;
use App\Modules\Reporting\Application\Data\ReportData;
use App\Modules\Reporting\Application\Data\ReportRunData;
use App\Modules\Reporting\Application\Data\ReportSearchCriteria;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ReportService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ReportRepository $reportRepository,
        private readonly ReportFilterNormalizer $reportFilterNormalizer,
        private readonly ReportGenerationService $reportGenerationService,
        private readonly FileStorageManager $fileStorageManager,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): ReportData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $source = $this->requiredString($attributes, 'source');
        $report = $this->reportRepository->create($tenantId, [
            'code' => $this->normalizedCode($attributes['code'] ?? null),
            'name' => $this->requiredString($attributes, 'name'),
            'description' => $this->nullableString($attributes['description'] ?? null),
            'source' => $source,
            'format' => $this->normalizedFormat($attributes['format'] ?? null),
            'filters' => $this->reportFilterNormalizer->normalize($source, $this->filters($attributes)),
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'reports.created',
            objectType: 'report',
            objectId: $report->reportId,
            after: $report->toArray(),
        ));

        return $report;
    }

    public function delete(string $reportId): ReportData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $report = $this->reportOrFail($reportId);
        $deletedAt = CarbonImmutable::now();

        if (! $this->reportRepository->softDelete($tenantId, $reportId, $deletedAt)) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        $deleted = $this->reportRepository->findInTenant($tenantId, $reportId, withDeleted: true)
            ?? throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'reports.deleted',
            objectType: 'report',
            objectId: $reportId,
            before: $report->toArray(),
            after: $deleted->toArray(),
        ));

        return $deleted;
    }

    public function downloadableRun(string $reportId): ReportRunData
    {
        $run = $this->reportRepository->latestRun($this->tenantContext->requireTenantId(), $reportId);

        if (! $run instanceof ReportRunData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $run;
    }

    public function get(string $reportId): ReportData
    {
        return $this->reportOrFail($reportId);
    }

    /**
     * @return list<ReportData>
     */
    public function list(ReportSearchCriteria $criteria): array
    {
        return $this->reportRepository->listForTenant($this->tenantContext->requireTenantId(), $criteria);
    }

    public function run(string $reportId): ReportRunData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $report = $this->reportOrFail($reportId);
        $generated = $this->reportGenerationService->generate($report);
        $stored = $this->fileStorageManager->storeArtifact(
            $generated->contents,
            sprintf(
                'tenants/%s/reports/%s/runs/%s/%s',
                $tenantId,
                $report->source,
                $generated->generatedAt->format('Y/m/d'),
                $generated->fileName,
            ),
        );
        $run = $this->reportRepository->createRun($tenantId, $reportId, [
            'status' => 'completed',
            'format' => $report->format,
            'row_count' => $generated->rowCount,
            'file_name' => $generated->fileName,
            'storage_disk' => $stored->disk,
            'storage_path' => $stored->path,
            'generated_at' => $generated->generatedAt,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'reports.ran',
            objectType: 'report',
            objectId: $reportId,
            after: $run->toArray(),
            metadata: [
                'source' => $report->source,
                'filters' => $report->filters,
            ],
        ));

        return $run;
    }

    private function normalizedCode(mixed $value): string
    {
        $raw = $this->requiredStringValue($value, 'code');
        $normalized = Str::snake($raw);

        if ($normalized === '') {
            throw new UnprocessableEntityHttpException('The report code is required.');
        }

        return $normalized;
    }

    private function normalizedFormat(mixed $value): string
    {
        $format = $this->requiredStringValue($value, 'format');

        if ($format !== 'csv') {
            throw new UnprocessableEntityHttpException('Only csv report format is supported in this phase.');
        }

        return $format;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filters(array $attributes): array
    {
        $filters = $attributes['filters'] ?? [];

        if (! is_array($filters)) {
            throw new UnprocessableEntityHttpException('Report filters must be an object.');
        }

        /** @var array<string, mixed> $filters */
        return $filters;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function reportOrFail(string $reportId): ReportData
    {
        $report = $this->reportRepository->findInTenant($this->tenantContext->requireTenantId(), $reportId, withRuns: true);

        if (! $report instanceof ReportData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function requiredString(array $attributes, string $key): string
    {
        return $this->requiredStringValue($attributes[$key] ?? null, $key);
    }

    private function requiredStringValue(mixed $value, string $key): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new UnprocessableEntityHttpException(sprintf('The %s field is required.', $key));
        }

        return trim($value);
    }
}
