<?php

namespace App\Modules\Provider\Infrastructure\Persistence;

use App\Modules\Provider\Application\Contracts\ProviderLicenseRepository;
use App\Modules\Provider\Application\Data\ProviderLicenseData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseProviderLicenseRepository implements ProviderLicenseRepository
{
    #[\Override]
    public function create(string $tenantId, string $providerId, array $attributes): ProviderLicenseData
    {
        $licenseId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('provider_licenses')->insert([
            'id' => $licenseId,
            'tenant_id' => $tenantId,
            'provider_id' => $providerId,
            'license_type' => $attributes['license_type'],
            'license_number' => $attributes['license_number'],
            'issuing_authority' => $attributes['issuing_authority'],
            'jurisdiction' => $attributes['jurisdiction'],
            'issued_on' => $attributes['issued_on']?->toDateString(),
            'expires_on' => $attributes['expires_on']?->toDateString(),
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $providerId, $licenseId)
            ?? throw new \LogicException('Provider license could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $providerId, string $licenseId): bool
    {
        return DB::table('provider_licenses')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->where('id', $licenseId)
            ->delete() > 0;
    }

    #[\Override]
    public function existsDuplicate(
        string $tenantId,
        string $providerId,
        string $licenseType,
        string $licenseNumber,
    ): bool {
        return DB::table('provider_licenses')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->where('license_type', $licenseType)
            ->where('license_number', $licenseNumber)
            ->exists();
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $providerId, string $licenseId): ?ProviderLicenseData
    {
        $row = DB::table('provider_licenses')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->where('id', $licenseId)
            ->first();

        return $row instanceof stdClass ? $this->map($row) : null;
    }

    #[\Override]
    public function listForProvider(string $tenantId, string $providerId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('provider_licenses')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->orderByRaw(
                'CASE WHEN expires_on IS NOT NULL AND expires_on < CURRENT_DATE THEN 1 ELSE 0 END ASC',
            )
            ->orderBy('expires_on')
            ->orderBy('created_at')
            ->get()
            ->all();

        return array_map($this->map(...), $rows);
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($this->stringValue($value));
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

    private function map(stdClass $row): ProviderLicenseData
    {
        return new ProviderLicenseData(
            licenseId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            providerId: $this->stringValue($row->provider_id ?? null),
            licenseType: $this->stringValue($row->license_type ?? null),
            licenseNumber: $this->stringValue($row->license_number ?? null),
            issuingAuthority: $this->stringValue($row->issuing_authority ?? null),
            jurisdiction: $this->nullableString($row->jurisdiction ?? null),
            issuedOn: $this->date($row->issued_on ?? null),
            expiresOn: $this->date($row->expires_on ?? null),
            notes: $this->nullableString($row->notes ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
        );
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
