<?php

namespace App\Modules\Reporting\Application\Contracts;

use App\Modules\Reporting\Application\Data\ReportData;
use App\Modules\Reporting\Application\Data\ReportRunData;
use App\Modules\Reporting\Application\Data\ReportSearchCriteria;
use Carbon\CarbonImmutable;

interface ReportRepository
{
    /**
     * @param  array{code: string, name: string, description: ?string, source: string, format: string, filters: array<string, mixed>}  $attributes
     */
    public function create(string $tenantId, array $attributes): ReportData;

    public function findInTenant(string $tenantId, string $reportId, bool $withRuns = false, bool $withDeleted = false): ?ReportData;

    /**
     * @return list<ReportData>
     */
    public function listForTenant(string $tenantId, ReportSearchCriteria $criteria): array;

    public function softDelete(string $tenantId, string $reportId, CarbonImmutable $deletedAt): bool;

    /**
     * @param  array{
     *     status: string,
     *     format: string,
     *     row_count: int,
     *     file_name: string,
     *     storage_disk: string,
     *     storage_path: string,
     *     generated_at: CarbonImmutable
     * }  $attributes
     */
    public function createRun(string $tenantId, string $reportId, array $attributes): ReportRunData;

    public function latestRun(string $tenantId, string $reportId): ?ReportRunData;
}
