<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

use App\Modules\IdentityAccess\Application\Data\ManagedUserData;

interface ManagedUserRepository
{
    public function attachToTenant(string $userId, string $tenantId, string $status): ManagedUserData;

    public function createAccount(string $name, string $email, string $password): string;

    public function deleteFromTenant(string $userId, string $tenantId): bool;

    public function emailExists(string $email, ?string $ignoreUserId = null): bool;

    public function findAccountIdByEmail(string $email): ?string;

    public function findByEmailInTenant(string $email, string $tenantId): ?ManagedUserData;

    public function findInTenant(string $userId, string $tenantId): ?ManagedUserData;

    /**
     * @return list<ManagedUserData>
     */
    public function listForTenant(string $tenantId, ?string $search = null, ?string $status = null): array;

    public function updateAccount(string $userId, ?string $name, ?string $email): bool;

    public function updatePassword(string $userId, string $password): bool;

    public function updateStatusInTenant(string $userId, string $tenantId, string $status): bool;
}
