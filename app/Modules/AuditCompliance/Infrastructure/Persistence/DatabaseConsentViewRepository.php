<?php

namespace App\Modules\AuditCompliance\Infrastructure\Persistence;

use App\Modules\AuditCompliance\Application\Contracts\ConsentViewRepository;
use App\Modules\AuditCompliance\Application\Data\ConsentViewData;
use App\Modules\AuditCompliance\Application\Data\ConsentViewSearchCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use stdClass;

final class DatabaseConsentViewRepository implements ConsentViewRepository
{
    #[\Override]
    public function findInTenant(string $tenantId, string $consentId): ?ConsentViewData
    {
        $row = $this->baseQuery($tenantId)
            ->where('consents.id', $consentId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    /**
     * @return list<ConsentViewData>
     */
    #[\Override]
    public function listForTenant(string $tenantId, ConsentViewSearchCriteria $criteria): array
    {
        $query = $this->baseQuery($tenantId);
        $this->applyFilters($query, $criteria);
        $this->applyOrdering($query);

        /** @var list<stdClass> $rows */
        $rows = $query->limit($criteria->limit)->get()->all();

        return array_map($this->toData(...), $rows);
    }

    private function applyFilters(Builder $query, ConsentViewSearchCriteria $criteria): void
    {
        $normalizedQuery = $criteria->normalizedQuery();

        if ($normalizedQuery !== null) {
            $like = '%'.$normalizedQuery.'%';

            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->whereRaw('LOWER(consents.consent_type) like ?', [$like])
                    ->orWhereRaw('LOWER(consents.granted_by_name) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(patients.preferred_name, \'\')) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(patients.first_name, \'\')) like ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(patients.last_name, \'\')) like ?', [$like]);
            });
        }

        if ($criteria->patientId !== null) {
            $query->where('consents.patient_id', $criteria->patientId);
        }

        if ($criteria->consentType !== null) {
            $query->where('consents.consent_type', $criteria->consentType);
        }

        if ($criteria->status !== null) {
            $this->applyStatusFilter($query, $criteria->status);
        }

        if ($criteria->grantedFrom instanceof CarbonImmutable) {
            $query->where('consents.granted_at', '>=', $criteria->grantedFrom);
        }

        if ($criteria->grantedTo instanceof CarbonImmutable) {
            $query->where('consents.granted_at', '<=', $criteria->grantedTo);
        }

        if ($criteria->expiresFrom instanceof CarbonImmutable) {
            $query->where('consents.expires_at', '>=', $criteria->expiresFrom);
        }

        if ($criteria->expiresTo instanceof CarbonImmutable) {
            $query->where('consents.expires_at', '<=', $criteria->expiresTo);
        }
    }

    private function applyOrdering(Builder $query): void
    {
        $query
            ->orderByRaw(
                'CASE WHEN consents.revoked_at IS NULL AND (consents.expires_at IS NULL OR consents.expires_at > ?) THEN 0 ELSE 1 END ASC',
                [CarbonImmutable::now()],
            )
            ->orderByDesc('consents.granted_at')
            ->orderByDesc('consents.created_at');
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        $now = CarbonImmutable::now();

        if ($status === 'active') {
            $query
                ->whereNull('consents.revoked_at')
                ->where(function (Builder $builder) use ($now): void {
                    $builder
                        ->whereNull('consents.expires_at')
                        ->orWhere('consents.expires_at', '>', $now);
                });

            return;
        }

        if ($status === 'expired') {
            $query
                ->whereNull('consents.revoked_at')
                ->whereNotNull('consents.expires_at')
                ->where('consents.expires_at', '<=', $now);

            return;
        }

        $query->whereNotNull('consents.revoked_at');
    }

    private function baseQuery(string $tenantId): Builder
    {
        return DB::table('patient_consents as consents')
            ->join('patients', function (JoinClause $join): void {
                $join
                    ->on('patients.id', '=', 'consents.patient_id')
                    ->on('patients.tenant_id', '=', 'consents.tenant_id');
            })
            ->where('consents.tenant_id', $tenantId)
            ->select([
                'consents.id',
                'consents.patient_id',
                'consents.consent_type',
                'consents.granted_by_name',
                'consents.granted_by_relationship',
                'consents.granted_at',
                'consents.expires_at',
                'consents.revoked_at',
                'consents.revocation_reason',
                'consents.notes',
                'consents.created_at',
                'consents.updated_at',
                'patients.first_name',
                'patients.last_name',
                'patients.preferred_name',
            ]);
    }

    private function toData(stdClass $row): ConsentViewData
    {
        return new ConsentViewData(
            consentId: $this->stringValue($row->id ?? null),
            patientId: $this->stringValue($row->patient_id ?? null),
            patientDisplayName: $this->patientDisplayName($row),
            consentType: $this->stringValue($row->consent_type ?? null),
            grantedByName: $this->stringValue($row->granted_by_name ?? null),
            grantedByRelationship: $this->nullableString($row->granted_by_relationship ?? null),
            grantedAt: $this->dateTime($row->granted_at ?? null),
            expiresAt: $this->nullableDateTime($row->expires_at ?? null),
            revokedAt: $this->nullableDateTime($row->revoked_at ?? null),
            revocationReason: $this->nullableString($row->revocation_reason ?? null),
            notes: $this->nullableString($row->notes ?? null),
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
