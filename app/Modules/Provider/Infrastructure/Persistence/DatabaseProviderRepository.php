<?php

namespace App\Modules\Provider\Infrastructure\Persistence;

use App\Modules\Provider\Application\Contracts\ProviderRepository;
use App\Modules\Provider\Application\Data\ProviderData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseProviderRepository implements ProviderRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): ProviderData
    {
        $now = CarbonImmutable::now();
        $providerId = (string) Str::uuid();

        DB::table('providers')->insert([
            'id' => $providerId,
            'tenant_id' => $tenantId,
            'first_name' => $attributes['first_name'],
            'last_name' => $attributes['last_name'],
            'middle_name' => $attributes['middle_name'],
            'preferred_name' => $attributes['preferred_name'],
            'provider_type' => $attributes['provider_type'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'clinic_id' => $attributes['clinic_id'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $providerId) ?? throw new \LogicException('Created provider could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $providerId, bool $withDeleted = false): ?ProviderData
    {
        $row = $this->baseQuery($tenantId, $withDeleted)
            ->where('id', $providerId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var list<stdClass> $rows */
        $rows = $this->baseQuery($tenantId)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function softDelete(string $tenantId, string $providerId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('providers')
            ->where('tenant_id', $tenantId)
            ->where('id', $providerId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function update(string $tenantId, string $providerId, array $updates): ?ProviderData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $providerId);
        }

        $updates['updated_at'] = CarbonImmutable::now();

        $updated = DB::table('providers')
            ->where('tenant_id', $tenantId)
            ->where('id', $providerId)
            ->whereNull('deleted_at')
            ->update($updates);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $providerId);
        }

        return $this->findInTenant($tenantId, $providerId);
    }

    private function baseQuery(string $tenantId, bool $withDeleted = false): Builder
    {
        $query = DB::table('providers')
            ->where('tenant_id', $tenantId)
            ->select([
                'id',
                'tenant_id',
                'first_name',
                'last_name',
                'middle_name',
                'preferred_name',
                'provider_type',
                'email',
                'phone',
                'clinic_id',
                'notes',
                'deleted_at',
                'created_at',
                'updated_at',
            ]);

        if (! $withDeleted) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    private function toData(stdClass $row): ProviderData
    {
        return new ProviderData(
            providerId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            firstName: $this->stringValue($row->first_name ?? null),
            lastName: $this->stringValue($row->last_name ?? null),
            middleName: $this->nullableString($row->middle_name ?? null),
            preferredName: $this->nullableString($row->preferred_name ?? null),
            providerType: $this->stringValue($row->provider_type ?? null),
            email: $this->nullableString($row->email ?? null),
            phone: $this->nullableString($row->phone ?? null),
            clinicId: $this->nullableString($row->clinic_id ?? null),
            notes: $this->nullableString($row->notes ?? null),
            deletedAt: $this->nullableDateTime($row->deleted_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
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
}
