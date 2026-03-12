<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Modules\AuditCompliance\Application\Contracts\DataAccessRequestRepository;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestData;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestSearchCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseDataAccessRequestRepository implements DataAccessRequestRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    #[\Override]
    public function approve(string $tenantId, string $requestId, array $attributes): ?DataAccessRequestData
    {
        DB::table('data_access_requests')
            ->where('tenant_id', $tenantId)
            ->where('id', $requestId)
            ->update([
                'status' => 'approved',
                'approved_at' => $attributes['approved_at'],
                'approved_by_user_id' => $attributes['approved_by_user_id'],
                'approved_by_name' => $attributes['approved_by_name'],
                'decision_notes' => $attributes['decision_notes'],
                'updated_at' => $attributes['approved_at'],
            ]);

        return $this->findInTenant($tenantId, $requestId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[\Override]
    public function create(string $tenantId, array $attributes): DataAccessRequestData
    {
        $requestId = (string) Str::orderedUuid();
        $now = CarbonImmutable::now();

        DB::table('data_access_requests')->insert([
            'id' => $requestId,
            'tenant_id' => $tenantId,
            'patient_id' => $attributes['patient_id'],
            'request_type' => $attributes['request_type'],
            'status' => 'submitted',
            'requested_by_name' => $attributes['requested_by_name'],
            'requested_by_relationship' => $attributes['requested_by_relationship'],
            'requested_at' => $attributes['requested_at'],
            'reason' => $attributes['reason'],
            'notes' => $attributes['notes'],
            'approved_at' => null,
            'approved_by_user_id' => null,
            'approved_by_name' => null,
            'denied_at' => null,
            'denied_by_user_id' => null,
            'denied_by_name' => null,
            'denial_reason' => null,
            'decision_notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $requestId)
            ?? throw new \LogicException('Created data access request could not be reloaded.');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[\Override]
    public function deny(string $tenantId, string $requestId, array $attributes): ?DataAccessRequestData
    {
        DB::table('data_access_requests')
            ->where('tenant_id', $tenantId)
            ->where('id', $requestId)
            ->update([
                'status' => 'denied',
                'denied_at' => $attributes['denied_at'],
                'denied_by_user_id' => $attributes['denied_by_user_id'],
                'denied_by_name' => $attributes['denied_by_name'],
                'denial_reason' => $attributes['denial_reason'],
                'decision_notes' => $attributes['decision_notes'],
                'updated_at' => $attributes['denied_at'],
            ]);

        return $this->findInTenant($tenantId, $requestId);
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $requestId): ?DataAccessRequestData
    {
        $row = $this->baseQuery($tenantId)
            ->where('requests.id', $requestId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    /**
     * @return list<DataAccessRequestData>
     */
    #[\Override]
    public function listForTenant(string $tenantId, DataAccessRequestSearchCriteria $criteria): array
    {
        $query = $this->baseQuery($tenantId);
        $this->applyFilters($query, $criteria);
        $this->applyOrdering($query);

        /** @var list<stdClass> $rows */
        $rows = $query->limit($criteria->limit)->get()->all();

        return array_map($this->toData(...), $rows);
    }

    private function applyFilters(Builder $query, DataAccessRequestSearchCriteria $criteria): void
    {
        $normalizedQuery = $criteria->normalizedQuery();

        if ($normalizedQuery !== null) {
            $like = '%'.$normalizedQuery.'%';

            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->whereRaw('LOWER(requests.request_type) like ?', [$like])
                    ->orWhereRaw('LOWER(requests.requested_by_name) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(patients.preferred_name, \'\')) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(patients.first_name, \'\')) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(patients.last_name, \'\')) like ?', [$like]);
            });
        }

        if ($criteria->patientId !== null) {
            $query->where('requests.patient_id', $criteria->patientId);
        }

        if ($criteria->requestType !== null) {
            $query->where('requests.request_type', $criteria->requestType);
        }

        if ($criteria->status !== null) {
            $query->where('requests.status', $criteria->status);
        }

        if ($criteria->requestedFrom instanceof CarbonImmutable) {
            $query->where('requests.requested_at', '>=', $criteria->requestedFrom);
        }

        if ($criteria->requestedTo instanceof CarbonImmutable) {
            $query->where('requests.requested_at', '<=', $criteria->requestedTo);
        }
    }

    private function applyOrdering(Builder $query): void
    {
        $query
            ->orderByRaw("CASE WHEN requests.status = 'submitted' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('requests.requested_at')
            ->orderByDesc('requests.created_at');
    }

    private function baseQuery(string $tenantId): Builder
    {
        return DB::table('data_access_requests as requests')
            ->join('patients', function (JoinClause $join): void {
                $join
                    ->on('patients.id', '=', 'requests.patient_id')
                    ->on('patients.tenant_id', '=', 'requests.tenant_id');
            })
            ->where('requests.tenant_id', $tenantId)
            ->select([
                'requests.id',
                'requests.patient_id',
                'requests.request_type',
                'requests.status',
                'requests.requested_by_name',
                'requests.requested_by_relationship',
                'requests.requested_at',
                'requests.reason',
                'requests.notes',
                'requests.approved_at',
                'requests.approved_by_user_id',
                'requests.approved_by_name',
                'requests.denied_at',
                'requests.denied_by_user_id',
                'requests.denied_by_name',
                'requests.denial_reason',
                'requests.decision_notes',
                'requests.created_at',
                'requests.updated_at',
                'patients.first_name',
                'patients.last_name',
                'patients.preferred_name',
            ]);
    }

    private function toData(stdClass $row): DataAccessRequestData
    {
        return new DataAccessRequestData(
            requestId: $this->stringValue($row->id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            patientDisplayName: $this->patientDisplayName($row),
            requestType: $this->stringValue($row->request_type ?? null),
            status: $this->stringValue($row->status ?? null),
            requestedByName: $this->stringValue($row->requested_by_name ?? null),
            requestedByRelationship: $this->nullableString($row->requested_by_relationship ?? null),
            requestedAt: $this->dateTime($row->requested_at ?? null),
            reason: $this->nullableString($row->reason ?? null),
            notes: $this->nullableString($row->notes ?? null),
            approvedAt: $this->nullableDateTime($row->approved_at ?? null),
            approvedByUserId: $this->nullableString($row->approved_by_user_id ?? null),
            approvedByName: $this->nullableString($row->approved_by_name ?? null),
            deniedAt: $this->nullableDateTime($row->denied_at ?? null),
            deniedByUserId: $this->nullableString($row->denied_by_user_id ?? null),
            deniedByName: $this->nullableString($row->denied_by_name ?? null),
            denialReason: $this->nullableString($row->denial_reason ?? null),
            decisionNotes: $this->nullableString($row->decision_notes ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }

    private function patientDisplayName(stdClass $row): string
    {
        $preferredName = trim($this->nullableString($row->preferred_name ?? null) ?? '');
        $lastName = trim($this->nullableString($row->last_name ?? null) ?? '');
        $firstName = trim($this->nullableString($row->first_name ?? null) ?? '');

        if ($preferredName !== '') {
            return trim($preferredName.' '.$lastName);
        }

        return trim($firstName.' '.$lastName);
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

        return $this->dateTime($value);
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
