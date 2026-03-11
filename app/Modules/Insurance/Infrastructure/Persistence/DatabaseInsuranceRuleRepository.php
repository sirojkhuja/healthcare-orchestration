<?php

namespace App\Modules\Insurance\Infrastructure\Persistence;

use App\Modules\Insurance\Application\Contracts\InsuranceRuleRepository;
use App\Modules\Insurance\Application\Data\InsuranceRuleData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseInsuranceRuleRepository implements InsuranceRuleRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): InsuranceRuleData
    {
        $ruleId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('insurance_rules')->insert([
            'id' => $ruleId,
            'tenant_id' => $tenantId,
            'payer_id' => $attributes['payer_id'],
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'service_category' => $attributes['service_category'],
            'requires_primary_policy' => $attributes['requires_primary_policy'],
            'requires_attachment' => $attributes['requires_attachment'],
            'max_claim_amount' => $attributes['max_claim_amount'],
            'submission_window_days' => $attributes['submission_window_days'],
            'is_active' => $attributes['is_active'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $ruleId)
            ?? throw new \LogicException('Created insurance rule could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $ruleId): bool
    {
        return DB::table('insurance_rules')
            ->where('tenant_id', $tenantId)
            ->where('id', $ruleId)
            ->delete() > 0;
    }

    #[\Override]
    public function existsCode(string $tenantId, string $code, ?string $ignoreRuleId = null): bool
    {
        $query = DB::table('insurance_rules')
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreRuleId !== null) {
            $query->where('id', '!=', $ignoreRuleId);
        }

        return $query->exists();
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $ruleId): ?InsuranceRuleData
    {
        $row = $this->baseQuery($tenantId)
            ->where('insurance_rules.id', $ruleId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listActiveForPayer(string $tenantId, string $payerId): array
    {
        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId)
            ->where('insurance_rules.payer_id', $payerId)
            ->where('insurance_rules.is_active', true)
            ->orderBy('insurance_rules.code')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function listForTenant(
        string $tenantId,
        ?string $query = null,
        ?string $payerId = null,
        ?string $serviceCategory = null,
        ?bool $isActive = null,
        int $limit = 25,
    ): array {
        $builder = $this->baseQuery($tenantId);

        if ($payerId !== null) {
            $builder->where('insurance_rules.payer_id', $payerId);
        }

        if ($serviceCategory !== null) {
            $builder->where('insurance_rules.service_category', $serviceCategory);
        }

        if ($isActive !== null) {
            $builder->where('insurance_rules.is_active', $isActive);
        }

        if ($query !== null && trim($query) !== '') {
            $pattern = '%'.mb_strtolower(trim($query)).'%';
            $builder->where(function (Builder $nested) use ($pattern): void {
                $nested
                    ->whereRaw('LOWER(insurance_rules.code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(insurance_rules.name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(insurance_payers.name) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $builder
            ->orderByDesc('insurance_rules.is_active')
            ->orderBy('insurance_rules.code')
            ->limit($limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function update(string $tenantId, string $ruleId, array $updates): ?InsuranceRuleData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $ruleId);
        }

        $updates['updated_at'] = CarbonImmutable::now();

        DB::table('insurance_rules')
            ->where('tenant_id', $tenantId)
            ->where('id', $ruleId)
            ->update($updates);

        return $this->findInTenant($tenantId, $ruleId);
    }

    private function baseQuery(string $tenantId): Builder
    {
        return DB::table('insurance_rules')
            ->join('insurance_payers', 'insurance_payers.id', '=', 'insurance_rules.payer_id')
            ->where('insurance_rules.tenant_id', $tenantId)
            ->select([
                'insurance_rules.id',
                'insurance_rules.tenant_id',
                'insurance_rules.payer_id',
                'insurance_rules.code',
                'insurance_rules.name',
                'insurance_rules.service_category',
                'insurance_rules.requires_primary_policy',
                'insurance_rules.requires_attachment',
                'insurance_rules.max_claim_amount',
                'insurance_rules.submission_window_days',
                'insurance_rules.is_active',
                'insurance_rules.notes',
                'insurance_rules.created_at',
                'insurance_rules.updated_at',
                'insurance_payers.code as payer_code',
                'insurance_payers.name as payer_name',
                'insurance_payers.insurance_code as payer_insurance_code',
            ]);
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

    private function decimalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return sprintf('%d.00', $value);
        }

        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        if (! is_string($value)) {
            return null;
        }

        $parts = explode('.', $value, 2);

        return $parts[0].'.'.str_pad($parts[1] ?? '', 2, '0');
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function toData(stdClass $row): InsuranceRuleData
    {
        return new InsuranceRuleData(
            ruleId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            payerId: $this->stringValue($row->payer_id ?? null),
            payerCode: $this->stringValue($row->payer_code ?? null),
            payerName: $this->stringValue($row->payer_name ?? null),
            payerInsuranceCode: $this->stringValue($row->payer_insurance_code ?? null),
            code: $this->stringValue($row->code ?? null),
            name: $this->stringValue($row->name ?? null),
            serviceCategory: $this->nullableString($row->service_category ?? null),
            requiresPrimaryPolicy: (bool) ($row->requires_primary_policy ?? false),
            requiresAttachment: (bool) ($row->requires_attachment ?? false),
            maxClaimAmount: $this->decimalString($row->max_claim_amount ?? null),
            submissionWindowDays: is_numeric($row->submission_window_days ?? null)
                ? (int) $row->submission_window_days
                : null,
            isActive: (bool) ($row->is_active ?? false),
            notes: $this->nullableString($row->notes ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
