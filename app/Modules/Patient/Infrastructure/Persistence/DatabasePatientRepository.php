<?php

namespace App\Modules\Patient\Infrastructure\Persistence;

use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Modules\Patient\Application\Data\PatientSearchCriteria;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabasePatientRepository implements PatientRepository
{
    #[\Override]
    public function create(string $tenantId, array $attributes): PatientData
    {
        $now = CarbonImmutable::now();
        $patientId = (string) Str::uuid();

        DB::table('patients')->insert([
            'id' => $patientId,
            'tenant_id' => $tenantId,
            'first_name' => $attributes['first_name'],
            'last_name' => $attributes['last_name'],
            'middle_name' => $attributes['middle_name'],
            'preferred_name' => $attributes['preferred_name'],
            'sex' => $attributes['sex'],
            'birth_date' => $attributes['birth_date'],
            'national_id' => $attributes['national_id'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'city_code' => $attributes['city_code'],
            'district_code' => $attributes['district_code'],
            'address_line_1' => $attributes['address_line_1'],
            'address_line_2' => $attributes['address_line_2'],
            'postal_code' => $attributes['postal_code'],
            'notes' => $attributes['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $patientId) ?? throw new \LogicException('Created patient could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $patientId, bool $withDeleted = false): ?PatientData
    {
        $query = $this->baseQuery($tenantId, $withDeleted)->where('id', $patientId);
        $row = $query->first();

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
    public function search(string $tenantId, PatientSearchCriteria $criteria): array
    {
        $query = $this->baseQuery($tenantId);
        $this->applyStructuredFilters($query, $criteria);
        $this->applySearchTokens($query, $criteria);

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('created_at')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function nationalIdExists(string $tenantId, string $nationalId, ?string $ignorePatientId = null): bool
    {
        $query = DB::table('patients')
            ->where('tenant_id', $tenantId)
            ->where('national_id', $nationalId)
            ->whereNull('deleted_at');

        if (is_string($ignorePatientId) && $ignorePatientId !== '') {
            $query->where('id', '!=', $ignorePatientId);
        }

        return $query->exists();
    }

    #[\Override]
    public function softDelete(string $tenantId, string $patientId, CarbonImmutable $deletedAt): bool
    {
        return DB::table('patients')
            ->where('tenant_id', $tenantId)
            ->where('id', $patientId)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $deletedAt,
                'updated_at' => $deletedAt,
            ]) > 0;
    }

    #[\Override]
    public function update(string $tenantId, string $patientId, array $updates): ?PatientData
    {
        if ($updates === []) {
            return $this->findInTenant($tenantId, $patientId);
        }

        $updates['updated_at'] = CarbonImmutable::now();

        $updated = DB::table('patients')
            ->where('tenant_id', $tenantId)
            ->where('id', $patientId)
            ->whereNull('deleted_at')
            ->update($updates);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $patientId);
        }

        return $this->findInTenant($tenantId, $patientId);
    }

    private function applyStructuredFilters(Builder $query, PatientSearchCriteria $criteria): void
    {
        if ($criteria->sex !== null) {
            $query->where('sex', $criteria->sex);
        }

        if ($criteria->cityCode !== null) {
            $query->where('city_code', $criteria->cityCode);
        }

        if ($criteria->districtCode !== null) {
            $query->where('district_code', $criteria->districtCode);
        }

        if ($criteria->birthDateFrom !== null) {
            $query->whereDate('birth_date', '>=', $criteria->birthDateFrom);
        }

        if ($criteria->birthDateTo !== null) {
            $query->whereDate('birth_date', '<=', $criteria->birthDateTo);
        }

        if ($criteria->createdFrom !== null) {
            $query->where('created_at', '>=', CarbonImmutable::parse($criteria->createdFrom)->startOfDay());
        }

        if ($criteria->createdTo !== null) {
            $query->where('created_at', '<=', CarbonImmutable::parse($criteria->createdTo)->endOfDay());
        }

        if ($criteria->hasEmail !== null) {
            $criteria->hasEmail
                ? $query->whereNotNull('email')
                : $query->whereNull('email');
        }

        if ($criteria->hasPhone !== null) {
            $criteria->hasPhone
                ? $query->whereNotNull('phone')
                : $query->whereNull('phone');
        }
    }

    private function applySearchTokens(Builder $query, PatientSearchCriteria $criteria): void
    {
        foreach ($criteria->tokens() as $token) {
            $pattern = '%'.$token.'%';

            $query->where(function (Builder $nested) use ($pattern): void {
                $nested
                    ->whereRaw('LOWER(first_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(middle_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(preferred_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(national_id, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$pattern]);
            });
        }
    }

    private function baseQuery(string $tenantId, bool $withDeleted = false): Builder
    {
        $query = DB::table('patients')
            ->where('tenant_id', $tenantId)
            ->select([
                'id',
                'tenant_id',
                'first_name',
                'last_name',
                'middle_name',
                'preferred_name',
                'sex',
                'birth_date',
                'national_id',
                'email',
                'phone',
                'city_code',
                'district_code',
                'address_line_1',
                'address_line_2',
                'postal_code',
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

    private function toData(stdClass $row): PatientData
    {
        return new PatientData(
            patientId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            firstName: $this->stringValue($row->first_name ?? null),
            lastName: $this->stringValue($row->last_name ?? null),
            middleName: $this->nullableString($row->middle_name ?? null),
            preferredName: $this->nullableString($row->preferred_name ?? null),
            sex: $this->stringValue($row->sex ?? null),
            birthDate: $this->dateTime($row->birth_date ?? null),
            nationalId: $this->nullableString($row->national_id ?? null),
            email: $this->nullableString($row->email ?? null),
            phone: $this->nullableString($row->phone ?? null),
            cityCode: $this->nullableString($row->city_code ?? null),
            districtCode: $this->nullableString($row->district_code ?? null),
            addressLine1: $this->nullableString($row->address_line_1 ?? null),
            addressLine2: $this->nullableString($row->address_line_2 ?? null),
            postalCode: $this->nullableString($row->postal_code ?? null),
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
