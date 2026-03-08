<?php

namespace App\Modules\IdentityAccess\Infrastructure\Users\Persistence;

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

final class DatabaseManagedUserRepository implements ManagedUserRepository
{
    #[\Override]
    public function attachToTenant(string $userId, string $tenantId, string $status): ManagedUserData
    {
        DB::table('tenant_user_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'status' => $status,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

        return $this->findInTenant($userId, $tenantId) ?? throw new \LogicException('Attached user membership could not be reloaded.');
    }

    #[\Override]
    public function createAccount(string $name, string $email, string $password): string
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        /** @var mixed $userId */
        $userId = $user->getAuthIdentifier();

        return is_string($userId) ? $userId : '';
    }

    #[\Override]
    public function deleteFromTenant(string $userId, string $tenantId): bool
    {
        return DB::table('tenant_user_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    #[\Override]
    public function emailExists(string $email, ?string $ignoreUserId = null): bool
    {
        $query = User::query()->where('email', $email);

        if (is_string($ignoreUserId) && $ignoreUserId !== '') {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }

    #[\Override]
    public function findAccountIdByEmail(string $email): ?string
    {
        /** @var string|null $userId */
        $userId = User::query()->where('email', $email)->value('id');

        return is_string($userId) ? $userId : null;
    }

    #[\Override]
    public function findByEmailInTenant(string $email, string $tenantId): ?ManagedUserData
    {
        $row = $this->baseQuery($tenantId)
            ->where('users.email', $email)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function findInTenant(string $userId, string $tenantId): ?ManagedUserData
    {
        $row = $this->baseQuery($tenantId)
            ->where('users.id', $userId)
            ->first();

        return $row instanceof stdClass ? $this->toData($row) : null;
    }

    #[\Override]
    public function listForTenant(string $tenantId, ?string $search = null, ?string $status = null): array
    {
        $query = $this->baseQuery($tenantId);

        if (is_string($status) && $status !== '') {
            $query->where('tenant_user_memberships.status', $status);
        }

        if (is_string($search) && trim($search) !== '') {
            $pattern = '%'.mb_strtolower(trim($search)).'%';
            $query->where(function (Builder $builder) use ($pattern): void {
                $builder
                    ->whereRaw('LOWER(users.name) like ?', [$pattern])
                    ->orWhereRaw('LOWER(users.email) like ?', [$pattern]);
            });
        }

        /** @var list<stdClass> $rows */
        $rows = $query
            ->orderBy('users.name')
            ->orderBy('users.email')
            ->get()
            ->all();

        return array_map($this->toData(...), $rows);
    }

    #[\Override]
    public function updateAccount(string $userId, ?string $name, ?string $email): bool
    {
        $updates = [];

        if (is_string($name) && $name !== '') {
            $updates['name'] = $name;
        }

        if (is_string($email) && $email !== '') {
            $updates['email'] = $email;
        }

        if ($updates === []) {
            return false;
        }

        return User::query()->whereKey($userId)->update($updates) > 0;
    }

    #[\Override]
    public function updatePassword(string $userId, string $password): bool
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User) {
            return false;
        }

        $user->password = $password;

        return $user->save();
    }

    #[\Override]
    public function updateStatusInTenant(string $userId, string $tenantId, string $status): bool
    {
        return DB::table('tenant_user_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->update([
                'status' => $status,
                'updated_at' => CarbonImmutable::now(),
            ]) > 0;
    }

    private function baseQuery(string $tenantId): Builder
    {
        return DB::table('tenant_user_memberships')
            ->join('users', 'tenant_user_memberships.user_id', '=', 'users.id')
            ->where('tenant_user_memberships.tenant_id', $tenantId)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.email_verified_at',
                'users.created_at',
                'users.updated_at',
                'tenant_user_memberships.tenant_id',
                'tenant_user_memberships.status',
                'tenant_user_memberships.created_at as joined_at',
                'tenant_user_memberships.updated_at as membership_updated_at',
            ]);
    }

    private function toData(stdClass $row): ManagedUserData
    {
        return new ManagedUserData(
            userId: $this->stringValue($row->id ?? null),
            tenantId: $this->stringValue($row->tenant_id ?? null),
            name: $this->stringValue($row->name ?? null),
            email: $this->stringValue($row->email ?? null),
            status: $this->stringValue($row->status ?? null),
            emailVerifiedAt: $this->nullableDateTime($row->email_verified_at ?? null),
            createdAt: $this->dateTime($row->created_at ?? null),
            updatedAt: $this->dateTime($row->updated_at ?? null),
            joinedAt: $this->dateTime($row->joined_at ?? null),
            membershipUpdatedAt: $this->dateTime($row->membership_updated_at ?? null),
        );
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
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
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
