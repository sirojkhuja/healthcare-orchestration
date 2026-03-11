<?php

namespace App\Modules\Insurance\Infrastructure\Persistence;

use App\Modules\Insurance\Application\Contracts\PayerRepository;
use App\Modules\Insurance\Application\Data\PayerData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePayerRepository implements PayerRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): PayerData
    {
        $payerId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('insurance_payers')->insert([
            'id' => $payerId,
            'tenant_id' => $tenantId,
            'code' => $attributes['code'],
            'name' => $attributes['name'],
            'insurance_code' => $attributes['insurance_code'],
            'contact_name' => $attributes['contact_name'],
            'contact_email' => $attributes['contact_email'],
            'contact_phone' => $attributes['contact_phone'],
            'is_active' => $attributes['is_active'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $payerId)
            ?? throw new \LogicException('Created payer could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $payerId): bool
    {
        return DB::table('insurance_payers')
            ->where('tenant_id', $tenantId)
            ->where('id', $payerId)
            ->delete() > 0;
    }

    #[\Override]
    public function existsCode(string $tenantId, string $code, ?string $ignorePayerId = null): bool
    {
        $query = DB::table('insurance_payers')
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignorePayerId !== null) {
            $query->where('id', '!=', $ignorePayerId);
        }

        return $query->exists();
    }

    #[\Override]
    public function existsInsuranceCode(string $tenantId, string $insuranceCode, ?string $ignorePayerId = null): bool
    {
        $query = DB::table('insurance_payers')
            ->where('tenant_id', $tenantId)
            ->where('insurance_code', $insuranceCode);

        if ($ignorePayerId !== null) {
            $query->where('id', '!=', $ignorePayerId);
        }

        return $query->exists();
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $payerId): ?PayerData
    {
        $row = DB::table('insurance_payers')
            ->where('tenant_id', $tenantId)
            ->where('id', $payerId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function isReferenced(string $tenantId, string $payerId): bool
    {
        return DB::table('claims')
            ->where('tenant_id', $tenantId)
            ->where('payer_id', $payerId)
            ->whereNull('deleted_at')
            ->exists();
    }

    #[\Override]
    public function listForTenant(
        string $tenantId,
        ?string $query = null,
        ?string $insuranceCode = null,
        ?bool $isActive = null,
        int $limit = 25,
    ): array {
        $builder = DB::table('insurance_payers')->where('tenant_id', $tenantId);

        if ($insuranceCode !== null) {
            $builder->where('insurance_code', $insuranceCode);
        }

        if ($isActive !== null) {
            $builder->where('is_active', $isActive);
        }

        if ($query !== null && trim($query) !== '') {
            $pattern = '%'.mb_strtolower(trim($query)).'%';
            $builder->where(function (Builder $nested) use ($pattern): void {
                $nested
                    ->whereRaw('LOWER(code) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(insurance_code) LIKE ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $builder
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function update(string $tenantId, string $payerId, array $updates): ?PayerData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $payerId);
        }

        $updates['updated_at'] = CarbonImmutable::now();

        DB::table('insurance_payers')
            ->where('tenant_id', $tenantId)
            ->where('id', $payerId)
            ->update($updates);

        return $this->findInTenant($tenantId, $payerId);
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

    private function toData(stdClass $row): PayerData
    {
        return new PayerData(
            payerId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            code: $this->stringValue($row->code ?? null),
            name: $this->stringValue($row->name ?? null),
            insuranceCode: $this->stringValue($row->insurance_code ?? null),
            contactName: $this->nullableString($row->contact_name ?? null),
            contactEmail: $this->nullableString($row->contact_email ?? null),
            contactPhone: $this->nullableString($row->contact_phone ?? null),
            isActive: (bool) ($row->is_active ?? false),
            notes: $this->nullableString($row->notes ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
    }
}
