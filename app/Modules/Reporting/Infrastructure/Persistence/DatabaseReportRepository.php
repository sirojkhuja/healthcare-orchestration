<?php

namespace App\Modules\Reporting\Infrastructure\Persistence;

use App\Modules\Reporting\Application\Contracts\ReportRepository;
use App\Modules\Reporting\Application\Data\ReportData;
use App\Modules\Reporting\Application\Data\ReportRunData;
use App\Modules\Reporting\Application\Data\ReportSearchCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseReportRepository implements ReportRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): ReportData
    {
        $now = CarbonImmutable::now();
        $reportId = (string) Str::uuid();

        DB::table('reports')->insert([
            'id' => $reportId,
            'tenant_id' => $tenantId,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'description' => $attributes['description'],
            'source' => $attributes['source'],
            'format' => $attributes['format'],
            'filters' => json_encode($attributes['filters'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $reportId) ?? throw new \LogicException('Created report could not be reloaded.');
    }

    #[\Override]
    public function createRun(string $tenantId, string $reportId, array $attributes): ReportRunData
    {
        $now = CarbonImmutable::now();
        $runId = (string) Str::uuid();

        DB::table('report_runs')->insert([
            'id' => $runId,
            'report_id' => $reportId,
            'tenant_id' => $tenantId,
            'status' => $attributes['status'],
            'format' => $attributes['format'],
            'row_count' => $attributes['row_count'],
            'file_name' => $attributes['file_name'],
            'storage_disk' => $attributes['storage_disk'],
            'storage_path' => $attributes['storage_path'],
            'generated_at' => $attributes['generated_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->latestRun($tenantId, $reportId) ?? throw new \LogicException('Created report run could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $reportId, bool $withRuns = false, bool $withDeleted = false): ?ReportData
    {
        $query = DB::table('reports')
            ->where('tenant_id', $tenantId)
            ->where('id', $reportId);

        if (! $withDeleted) {
            $query->whereNull('deleted_at');
        }

        $row = $query->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->toReportData($row, $withRuns);
    }

    #[\Override]
    public function latestRun(string $tenantId, string $reportId): ?ReportRunData
    {
        $row = DB::table('report_runs')
            ->where('tenant_id', $tenantId)
            ->where('report_id', $reportId)
            ->orderByDesc('generated_at')
            ->orderByDesc('created_at')
            ->first();

        return $row instanceof stdClass ? $this->toRunData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId, ReportSearchCriteria $criteria): array
    {
        $query = DB::table('reports')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->limit($criteria->limit);

        if (is_string($criteria->source) && $criteria->source !== '') {
            $query->where('source', $criteria->source);
        }

        if (is_string($criteria->query) && trim($criteria->query) !== '') {
            $needle = '%'.mb_strtolower(trim($criteria->query)).'%';
            $query->where(function (Builder $nested) use ($needle): void {
                $nested
                    ->whereRaw('LOWER(code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query->get()->all();

        return array_map(fn (stdClass $row): ReportData => $this->toReportData($row, false), $rows);
    }

    #[\Override]
    public function softDelete(string $tenantId, string $reportId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('reports')
            ->where('tenant_id', $tenantId)
            ->where('id', $reportId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(stdClass $row): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode($this->stringValue($row->filters ?? null), true);

        return is_array($decoded) ? $this->normalizeFilterPayload($decoded) : [];
    }

    /**
     * @return list<ReportRunData>
     */
    private function recentRuns(string $tenantId, string $reportId, int $limit): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('report_runs')
            ->where('tenant_id', $tenantId)
            ->where('report_id', $reportId)
            ->orderByDesc('generated_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->all();

        return array_map(fn (stdClass $row): ReportRunData => $this->toRunData($row), $rows);
    }

    private function toReportData(stdClass $row, bool $withRuns): ReportData
    {
        $tenantId = $this->stringValue($row->tenant_id ?? null);
        $reportId = $this->stringValue($row->id ?? null);
        $runs = $withRuns ? $this->recentRuns($tenantId, $reportId, 10) : [];

        return new ReportData(
            reportId: $reportId,
            tenantId: $tenantId,
            code: $this->stringValue($row->code ?? null),
            name: $this->stringValue($row->name ?? null),
            description: $this->nullableString($row->description ?? null),
            source: $this->stringValue($row->source ?? null),
            format: $this->stringValue($row->format ?? null),
            filters: $this->filters($row),
            latestRun: $this->latestRun($tenantId, $reportId),
            runs: $runs,
            deletedAt: $this->nullableDateTime($row->deleted_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function toRunData(stdClass $row): ReportRunData
    {
        return new ReportRunData(
            runId: $this->stringValue($row->id ?? null),
            reportId: $this->stringValue($row->report_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            status: $this->stringValue($row->status ?? null),
            format: $this->stringValue($row->format ?? null),
            rowCount: $this->intValue($row->row_count ?? null),
            fileName: $this->stringValue($row->file_name ?? null),
            storageDisk: $this->stringValue($row->storage_disk ?? null),
            storagePath: $this->stringValue($row->storage_path ?? null),
            generatedAt: $this->dateTime($row->generated_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
        );
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

    private function nullableDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        $string = $this->nullableString($value);

        return $string !== null ? CarbonImmutable::parse($string) : null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function intValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeFilterPayload(array $payload): array
    {
        /** @var array<string, mixed> $normalized */
        $normalized = [];

        array_walk($payload, function (mixed $value, int|string $key) use (&$normalized): void {
            if (! is_string($key)) {
                return;
            }

            $normalized[$key] = $this->normalizeFilterValue($value);
        });

        return $normalized;
    }

    /**
     * @return array<string, mixed>|bool|float|int|string|null
     */
    private function normalizeFilterValue(mixed $value): array|bool|float|int|string|null
    {
        if (is_array($value)) {
            return $this->normalizeFilterPayload($value);
        }

        return is_scalar($value) || $value === null ? $value : null;
    }
}
