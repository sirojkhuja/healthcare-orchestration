<?php

namespace App\Modules\Provider\Infrastructure\Persistence;

use App\Modules\Provider\Application\Contracts\ProviderGroupRepository;
use App\Modules\Provider\Application\Data\ProviderGroupData;
use App\Modules\Provider\Application\Data\ProviderGroupMemberData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseProviderGroupRepository implements ProviderGroupRepository
{
    #[\Override]
    public function create(string $tenantId, string $name, ?string $description, ?string $clinicId): ProviderGroupData
    {
        $groupId = (string) Str::uuid();
        $now = CarbonImmutable::now();

        DB::table('provider_groups')->insert([
            'id' => $groupId,
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => $description,
            'clinic_id' => $clinicId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findInTenant($tenantId, $groupId)
            ?? throw new \LogicException('Provider group could not be reloaded.');
    }

    #[\Override]
    public function findInTenant(string $tenantId, string $groupId): ?ProviderGroupData
    {
        $row = DB::table('provider_groups')
            ->where('tenant_id', $tenantId)
            ->where('id', $groupId)
            ->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        return $this->mapGroup($row, $this->membersForGroups($tenantId, [$groupId])[$groupId] ?? []);
    }

    #[\Override]
    public function listForTenant(string $tenantId): array
    {
        /** @var list<stdClass> $rows */
        $rows = DB::table('provider_groups')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->orderBy('created_at')
            ->get()
            ->all();

        $groupIds = array_map(
            fn (stdClass $row): string => $this->stringValue($row->id ?? null),
            $rows,
        );
        $members = $this->membersForGroups($tenantId, $groupIds);

        return array_map(
            fn (stdClass $row): ProviderGroupData => $this->mapGroup(
                $row,
                $members[$this->stringValue($row->id ?? null)] ?? [],
            ),
            $rows,
        );
    }

    #[\Override]
    public function nameExists(string $tenantId, string $name, ?string $exceptGroupId = null): bool
    {
        $query = DB::table('provider_groups')
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]);

        if (is_string($exceptGroupId) && $exceptGroupId !== '') {
            $query->where('id', '!=', $exceptGroupId);
        }

        return $query->exists();
    }

    #[\Override]
    public function replaceMembers(string $tenantId, string $groupId, array $providerIds): ProviderGroupData
    {
        DB::table('provider_group_members')
            ->where('tenant_id', $tenantId)
            ->where('group_id', $groupId)
            ->delete();

        if ($providerIds !== []) {
            $now = CarbonImmutable::now();

            DB::table('provider_group_members')->insert(array_map(
                static fn (string $providerId): array => [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'group_id' => $groupId,
                    'provider_id' => $providerId,
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $providerIds,
            ));
        }

        return $this->findInTenant($tenantId, $groupId)
            ?? throw new \LogicException('Provider group could not be reloaded.');
    }

    /**
     * @param  list<string>  $groupIds
     * @return array<string, list<ProviderGroupMemberData>>
     */
    private function membersForGroups(string $tenantId, array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        /** @var list<stdClass> $rows */
        $rows = DB::table('provider_group_members as memberships')
            ->join('providers', 'providers.id', '=', 'memberships.provider_id')
            ->where('memberships.tenant_id', $tenantId)
            ->whereIn('memberships.group_id', $groupIds)
            ->whereNull('providers.deleted_at')
            ->select([
                'memberships.group_id',
                'providers.id as provider_id',
                'providers.first_name',
                'providers.last_name',
                'providers.preferred_name',
                'providers.provider_type',
                'providers.clinic_id',
            ])
            ->orderBy('memberships.group_id')
            ->orderBy('providers.last_name')
            ->orderBy('providers.first_name')
            ->orderBy('providers.created_at')
            ->get()
            ->all();

        $members = [];

        foreach ($rows as $row) {
            $groupId = $this->stringValue($row->group_id ?? null);
            $members[$groupId] ??= [];
            $members[$groupId][] = new ProviderGroupMemberData(
                providerId: $this->stringValue($row->provider_id ?? null),
                firstName: $this->stringValue($row->first_name ?? null),
                lastName: $this->stringValue($row->last_name ?? null),
                preferredName: $this->nullableString($row->preferred_name ?? null),
                providerType: $this->stringValue($row->provider_type ?? null),
                clinicId: $this->nullableString($row->clinic_id ?? null),
            );
        }

        return $members;
    }

    /**
     * @param  list<ProviderGroupMemberData>  $members
     */
    private function mapGroup(stdClass $row, array $members): ProviderGroupData
    {
        return new ProviderGroupData(
            groupId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            name: $this->stringValue($row->name ?? null),
            description: $this->nullableString($row->description ?? null),
            clinicId: $this->nullableString($row->clinic_id ?? null),
            members: $members,
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

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
