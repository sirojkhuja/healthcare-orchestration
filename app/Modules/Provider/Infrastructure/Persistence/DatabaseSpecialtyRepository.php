<?php

namespace App\Modules\Provider\Infrastructure\Persistence;

use App\Modules\Provider\Application\Contracts\SpecialtyRepository;
use App\Modules\Provider\Application\Data\ProviderSpecialtyData;
use App\Modules\Provider\Application\Data\SpecialtyData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseSpecialtyRepository implements SpecialtyRepository
{
    #[\Override]
    public function create(string $tenantId, string $name, ?string $description): SpecialtyData
    {
        $specialtyId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('specialties')->insert([
            'id' => $specialtyId,
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $specialtyId)
            ?? throw new \LogicException('Specialty could not be reloaded.');
    }

    #[\Override]
    public function delete(string $tenantId, string $specialtyId): bool
    {
        return DB::table('specialties')
            ->where('tenant_id', $tenantId)
            ->where('id', $specialtyId)
            ->delete() > 0;
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $specialtyId): ?SpecialtyData
    {
        $row = DB::table('specialties')
            ->where('tenant_id', $tenantId)
            ->where('id', $specialtyId)
            ->first();

        return $row instanceof stdClass ? $this->mapSpecialty($row) : null;
    }

    #[\Override]
    public function hasAssignments(string $tenantId, string $specialtyId): bool
    {
        return DB::table('provider_specialties')
            ->where('tenant_id', $tenantId)
            ->where('specialty_id', $specialtyId)
            ->exists();
    }

    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('specialties')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->orderBy('created_at')
            ->get()
            ->all();

        return array_map($this->mapSpecialty(...), $rows);
    }

    #[\Override]
    public function listForProvider(string $tenantId, string $providerId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('provider_specialties as assignments')
            ->join('specialties', 'specialties.id', '=', 'assignments.specialty_id')
            ->where('assignments.tenant_id', $tenantId)
            ->where('assignments.provider_id', $providerId)
            ->select([
                'assignments.provider_id',
                'assignments.specialty_id',
                'assignments.tenant_id',
                'assignments.is_primary',
                'assignments.assigned_at',
                'specialties.name',
                'specialties.description',
            ])
            ->orderByDesc('assignments.is_primary')
            ->orderBy('specialties.name')
            ->orderBy('assignments.assigned_at')
            ->get()
            ->all();

        return array_map($this->mapProviderSpecialty(...), $rows);
    }

    #[\Override]
    public function nameExists(string $tenantId, string $name, ?string $exceptSpecialtyId = null): bool
    {
        $query = DB::table('specialties')
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if (is_string($exceptSpecialtyId) && $exceptSpecialtyId !== '') {
            $query->where('id', '!=', $exceptSpecialtyId);
        }

        return $query->exists();
    }

    #[\Override]
    public function replaceProviderAssignments(string $tenantId, string $providerId, array $assignments): array
    {
        DB::table('provider_specialties')
            ->where('tenant_id', $tenantId)
            ->where('provider_id', $providerId)
            ->delete();

        if ($assignments !== []) {
            $now = CarbonImmutable::now();

            DB::table('provider_specialties')->insert(array_map(
                static fn (array $assignment): array => [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'provider_id' => $providerId,
                    'specialty_id' => $assignment['specialty_id'],
                    'is_primary' => $assignment['is_primary'],
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $assignments,
            ));
        }

        return $this->listForProvider($tenantId, $providerId);
    }

    #[\Override]
    public function update(string $tenantId, string $specialtyId, string $name, ?string $description): ?SpecialtyData
    {
        $updated = DB::table('specialties')
            ->where('tenant_id', $tenantId)
            ->where('id', $specialtyId)
            ->update([
                'name' => $name,
                'description' => $description,
                'updated_at' => CarbonImmutable::now(),
            ]);

        if ($updated === 0) {
            return $this->findInTenant($tenantId, $specialtyId);
        }

        return $this->findInTenant($tenantId, $specialtyId);
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

    private function mapProviderSpecialty(stdClass $row): ProviderSpecialtyData
    {
        return new ProviderSpecialtyData(
            providerId: $this->stringValue($row->provider_id ?? null),
            specialtyId: $this->stringValue($row->specialty_id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            name: $this->stringValue($row->name ?? null),
            description: $this->nullableString($row->description ?? null),
            isPrimary: (bool) ($row->is_primary ?? false),
            assignedAt: $this->dateTime($row->assigned_at ?? null),
        );
    }

    private function mapSpecialty(stdClass $row): SpecialtyData
    {
        return new SpecialtyData(
            specialtyId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            name: $this->stringValue($row->name ?? null),
            description: $this->nullableString($row->description ?? null),
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
