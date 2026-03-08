<?php

namespace App\Modules\TenantManagement\Infrastructure\Persistence;

use App\Modules\TenantManagement\Application\Contracts\TenantRepository;
use App\Modules\TenantManagement\Application\Data\TenantData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseTenantRepository implements TenantRepository
{
    #[\Override]
    public function create(string $name, ?string $contactEmail, ?string $contactPhone, string $status): TenantData
    {
        /** @var string $tenantId */
        $tenantId = (string) Str::uuid();
        $timestamp = CarbonImmutable::now();

        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => $name,
            'status' => $status,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'activated_at' => $status === 'active' ? $timestamp : null,
            'suspended_at' => $status === 'suspended' ? $timestamp : null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return $this->find($tenantId) ?? throw new \LogicException('The tenant could not be reloaded after creation.');
    }

    #[\Override]
    public function delete(string $tenantId): bool
    {
        return DB::table('tenants')
            ->where('id', $tenantId)
            ->delete() > 0;
    }

    #[\Override]
    public function find(string $tenantId): ?TenantData
    {
        $row = $this->baseQuery()
            ->where('tenants.id', $tenantId)
            ->first();

        return $row !== null ? $this->mapTenant($row) : null;
    }

    #[\Override]
    public function findVisibleToUser(string $tenantId, string $userId): ?TenantData
    {
        $row = $this->visibleToUserQuery($userId)
            ->where('tenants.id', $tenantId)
            ->first();

        return $row !== null ? $this->mapTenant($row) : null;
    }

    #[\Override]
    public function listVisibleToUser(string $userId, ?string $search = null, ?string $status = null): array
    {
        $query = $this->visibleToUserQuery($userId);

        if (is_string($status) && $status !== '') {
            $query->where('tenants.status', $status);
        }

        if (is_string($search) && $search !== '') {
            $term = '%'.strtolower($search).'%';
            $query->where(static function (Builder $builder) use ($term): void {
                $builder->whereRaw('LOWER(tenants.name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(tenants.contact_email, \'\')) LIKE ?', [$term]);
            });
        }

        return array_values(array_map(
            fn (object $row): TenantData => $this->mapTenant($row),
            $query->orderBy('tenants.name')->get()->all(),
        ));
    }

    #[\Override]
    public function memberUserIds(string $tenantId): array
    {
        /** @var array<int, mixed> $userIds */
        $userIds = DB::table('tenant_user_memberships')
            ->where('tenant_id', $tenantId)
            ->orderBy('user_id')
            ->pluck('user_id')
            ->all();

        $members = [];

        foreach ($userIds as $userId) {
            if (! is_string($userId) || $userId === '') {
                continue;
            }

            $members[] = strtolower($userId);
        }

        return $members;
    }

    #[\Override]
    /**
     * @param  array<string, CarbonImmutable|string|null>  $attributes
     */
    public function update(string $tenantId, array $attributes): bool
    {
        return DB::table('tenants')
            ->where('id', $tenantId)
            ->update($attributes + ['updated_at' => CarbonImmutable::now()]) > 0;
    }

    private function baseQuery(): Builder
    {
        return DB::table('tenants')->select([
            'tenants.id',
            'tenants.name',
            'tenants.status',
            'tenants.contact_email',
            'tenants.contact_phone',
            'tenants.activated_at',
            'tenants.suspended_at',
            'tenants.created_at',
            'tenants.updated_at',
        ]);
    }

    private function visibleToUserQuery(string $userId): Builder
    {
        return $this->baseQuery()
            ->join('tenant_user_memberships', function (JoinClause $join) use ($userId): void {
                $join->on('tenant_user_memberships.tenant_id', '=', 'tenants.id')
                    ->where('tenant_user_memberships.user_id', '=', $userId);
            })
            ->addSelect('tenant_user_memberships.status as membership_status');
    }

    private function mapTenant(object $row): TenantData
    {
        return new TenantData(
            tenantId: $this->uuidString($row->id ?? null),
            name: $this->requiredString($row->name ?? null),
            status: $this->requiredString($row->status ?? null),
            membershipStatus: $this->optionalLowercaseString($row->membership_status ?? null),
            contactEmail: $this->optionalLowercaseString($row->contact_email ?? null),
            contactPhone: $this->optionalString($row->contact_phone ?? null),
            activatedAt: $this->nullableTimestamp($row->activated_at ?? null),
            suspendedAt: $this->nullableTimestamp($row->suspended_at ?? null),
            createdAt: $this->timestamp($row->created_at ?? null),
            updatedAt: $this->timestamp($row->updated_at ?? null),
        );
    }

    private function optionalLowercaseString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? strtolower($value) : null;
    }

    private function nullableTimestamp(mixed $value): ?CarbonImmutable
    {
        return is_string($value) || $value instanceof \DateTimeInterface ? CarbonImmutable::parse($value) : null;
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function requiredString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function uuidString(mixed $value): string
    {
        return is_string($value) ? strtolower($value) : '';
    }

    private function timestamp(mixed $value): CarbonImmutable
    {
        if (! is_string($value) && ! $value instanceof \DateTimeInterface) {
            throw new \LogicException('Tenant timestamps must be string or date-time instances.');
        }

        return CarbonImmutable::parse($value);
    }
}
