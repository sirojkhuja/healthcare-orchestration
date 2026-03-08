<?php

namespace App\Modules\IdentityAccess\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionInvalidationDispatcher;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use App\Modules\IdentityAccess\Application\Data\BulkImportUsersResultData;
use App\Modules\IdentityAccess\Application\Data\BulkUserActionResultData;
use App\Modules\IdentityAccess\Application\Data\ManagedUserData;
use App\Modules\IdentityAccess\Domain\Users\TenantUserStatus;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TenantManagedUserService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ManagedUserRepository $managedUserRepository,
        private readonly UserRoleAssignmentRepository $userRoleAssignmentRepository,
        private readonly PermissionProjectionInvalidationDispatcher $permissionProjectionInvalidationDispatcher,
        private readonly AuditTrailWriter $auditTrailWriter,
        private readonly AuthSessionRepository $authSessionRepository,
    ) {}

    public function create(string $name, string $email, ?string $password, string $status): ManagedUserData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $normalizedStatus = $this->creatableStatus($status);
        $normalizedEmail = $this->normalizedEmail($email);
        $existingMembership = $this->managedUserRepository->findByEmailInTenant($normalizedEmail, $tenantId);

        if ($existingMembership !== null) {
            throw new ConflictHttpException('The requested user already belongs to the active tenant.');
        }

        /** @var ManagedUserData $user */
        $user = DB::transaction(function () use ($name, $normalizedEmail, $password, $normalizedStatus, $tenantId): ManagedUserData {
            $existingUserId = $this->managedUserRepository->findAccountIdByEmail($normalizedEmail);
            $createdAccount = $existingUserId === null;

            if ($createdAccount) {
                $userId = $this->managedUserRepository->createAccount(
                    $name,
                    $normalizedEmail,
                    $this->requiredPassword($password),
                );
            } else {
                /** @var string $existingUserId */
                $userId = $existingUserId;
            }

            $user = $this->managedUserRepository->attachToTenant($userId, $tenantId, $normalizedStatus);
            $this->invalidatePermissions($user->userId, $tenantId);
            $this->recordCreatedOrAttachedAudit($user, $createdAccount ? 'users.created' : 'users.attached', 'api');

            return $user;
        });

        return $user;
    }

    public function update(string $userId, ?string $name, ?string $email): ManagedUserData
    {
        $user = $this->tenantUserOrFail($userId);
        $normalizedEmail = $email !== null ? $this->normalizedEmail($email) : $user->email;
        $resolvedName = is_string($name) && $name !== '' ? $name : $user->name;

        if ($normalizedEmail !== $user->email && $this->managedUserRepository->emailExists($normalizedEmail, $user->userId)) {
            throw new ConflictHttpException('A user with this email already exists.');
        }

        if ($resolvedName === $user->name && $normalizedEmail === $user->email) {
            return $user;
        }

        $updatedAccount = $this->managedUserRepository->updateAccount($user->userId, $resolvedName, $normalizedEmail);

        if (! $updatedAccount) {
            throw new LogicException('The user account update could not be persisted.');
        }

        $updated = $this->tenantUserOrFail($user->userId);
        $this->auditChange('users.updated', $user, $updated, 'api');

        return $updated;
    }

    public function delete(string $userId): ManagedUserData
    {
        /** @var ManagedUserData $user */
        $user = DB::transaction(fn (): ManagedUserData => $this->deleteMembership($userId, 'api'));

        return $user;
    }

    /**
     * @return array{user: ManagedUserData, revoked_sessions: int}
     */
    public function resetPassword(string $userId, string $password): array
    {
        $user = $this->tenantUserOrFail($userId);
        $updatedPassword = $this->managedUserRepository->updatePassword($user->userId, $password);

        if (! $updatedPassword) {
            throw new LogicException('The user password could not be updated.');
        }

        $revokedSessions = $this->authSessionRepository->revokeAllForUser($user->userId, CarbonImmutable::now());

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'users.password_reset_admin',
            objectType: 'user',
            objectId: $user->userId,
            metadata: [
                'source' => 'api',
                'revoked_sessions' => $revokedSessions,
            ],
        ));

        return [
            'user' => $this->tenantUserOrFail($user->userId),
            'revoked_sessions' => $revokedSessions,
        ];
    }

    public function activate(string $userId): ManagedUserData
    {
        return $this->transition($userId, TenantUserStatus::ACTIVE, 'users.activated');
    }

    public function deactivate(string $userId): ManagedUserData
    {
        return $this->transition($userId, TenantUserStatus::INACTIVE, 'users.deactivated');
    }

    public function lock(string $userId): ManagedUserData
    {
        return $this->transition($userId, TenantUserStatus::LOCKED, 'users.locked');
    }

    public function unlock(string $userId): ManagedUserData
    {
        return $this->transition($userId, TenantUserStatus::ACTIVE, 'users.unlocked');
    }

    /**
     * @param  list<array{name: string, email: string, password?: string|null, status?: string|null}>  $rows
     */
    public function bulkImport(array $rows): BulkImportUsersResultData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $createdCount = 0;
        $attachedCount = 0;
        $existingCount = 0;
        $users = [];

        DB::transaction(function () use ($rows, $tenantId, &$createdCount, &$attachedCount, &$existingCount, &$users): void {
            foreach ($rows as $index => $row) {
                $status = $this->creatableStatus($row['status'] ?? null);
                $email = $this->normalizedEmail($row['email']);
                $name = $row['name'];
                $existingMembership = $this->managedUserRepository->findByEmailInTenant($email, $tenantId);

                if ($existingMembership !== null) {
                    $existingCount++;
                    $users[] = $existingMembership;

                    continue;
                }

                $existingUserId = $this->managedUserRepository->findAccountIdByEmail($email);

                if ($existingUserId === null) {
                    $existingUserId = $this->managedUserRepository->createAccount(
                        $name,
                        $email,
                        $this->requiredPassword($row['password'] ?? null, $index),
                    );
                    $createdCount++;
                    $auditAction = 'users.created';
                } else {
                    $attachedCount++;
                    $auditAction = 'users.attached';
                }

                $user = $this->managedUserRepository->attachToTenant($existingUserId, $tenantId, $status);
                $users[] = $user;
                $this->invalidatePermissions($user->userId, $tenantId);
                $this->recordCreatedOrAttachedAudit($user, $auditAction, 'bulk_import');
            }
        });

        return new BulkImportUsersResultData(count($rows), $createdCount, $attachedCount, $existingCount, $users);
    }

    /**
     * @param  list<string>  $userIds
     */
    public function bulkAction(string $action, array $userIds): BulkUserActionResultData
    {
        $normalizedUserIds = array_values(array_unique(array_filter(
            $userIds,
            static fn (string $userId): bool => $userId !== '',
        )));
        $users = [];

        DB::transaction(function () use ($action, $normalizedUserIds, &$users): void {
            foreach ($normalizedUserIds as $userId) {
                $users[] = match ($action) {
                    'activate' => $this->transition($userId, TenantUserStatus::ACTIVE, 'users.activated', 'bulk_update'),
                    'deactivate' => $this->transition($userId, TenantUserStatus::INACTIVE, 'users.deactivated', 'bulk_update'),
                    'lock' => $this->transition($userId, TenantUserStatus::LOCKED, 'users.locked', 'bulk_update'),
                    'unlock' => $this->transition($userId, TenantUserStatus::ACTIVE, 'users.unlocked', 'bulk_update'),
                    'delete' => $this->deleteMembership($userId, 'bulk_update'),
                    default => throw ValidationException::withMessages([
                        'action' => ['Unsupported bulk action.'],
                    ]),
                };
            }
        });

        return new BulkUserActionResultData($action, count($normalizedUserIds), $users);
    }

    private function transition(string $userId, string $targetStatus, string $auditAction, string $source = 'api'): ManagedUserData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $user = $this->tenantUserOrFail($userId);

        if (! TenantUserStatus::canTransition($user->status, $targetStatus)) {
            throw new ConflictHttpException('The requested lifecycle transition is not allowed from the current state.');
        }

        $updatedMembership = $this->managedUserRepository->updateStatusInTenant($user->userId, $tenantId, $targetStatus);

        if (! $updatedMembership) {
            throw new LogicException('The tenant user membership status could not be updated.');
        }

        $updated = $this->tenantUserOrFail($user->userId);
        $this->invalidatePermissions($updated->userId, $tenantId);
        $this->auditChange($auditAction, $user, $updated, $source);

        return $updated;
    }

    private function deleteMembership(string $userId, string $source): ManagedUserData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $user = $this->tenantUserOrFail($userId);

        $this->userRoleAssignmentRepository->replaceRolesForUser($user->userId, $tenantId, []);
        $deleted = $this->managedUserRepository->deleteFromTenant($user->userId, $tenantId);

        if (! $deleted) {
            throw new NotFoundHttpException('The requested user does not belong to the active tenant.');
        }

        $this->invalidatePermissions($user->userId, $tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'users.deleted',
            objectType: 'user',
            objectId: $user->userId,
            before: $user->toArray(),
            metadata: ['source' => $source],
        ));

        return $user;
    }

    private function tenantUserOrFail(string $userId): ManagedUserData
    {
        $user = $this->managedUserRepository->findInTenant($userId, $this->tenantContext->requireTenantId());

        if ($user === null) {
            throw new NotFoundHttpException('The requested user does not belong to the active tenant.');
        }

        return $user;
    }

    private function normalizedEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function creatableStatus(?string $status): string
    {
        $resolvedStatus = is_string($status) && $status !== '' ? $status : TenantUserStatus::ACTIVE;

        if (! in_array($resolvedStatus, TenantUserStatus::creatable(), true)) {
            throw ValidationException::withMessages([
                'status' => ['User creation accepts only active or inactive status.'],
            ]);
        }

        return $resolvedStatus;
    }

    private function requiredPassword(?string $password, ?int $rowIndex = null): string
    {
        if (is_string($password) && $password !== '') {
            return $password;
        }

        $field = $rowIndex === null ? 'password' : sprintf('users.%d.password', $rowIndex);

        throw ValidationException::withMessages([
            $field => ['A password is required when creating a new account.'],
        ]);
    }

    private function auditChange(string $action, ManagedUserData $before, ManagedUserData $after, string $source): void
    {
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $action,
            objectType: 'user',
            objectId: $after->userId,
            before: $before->toArray(),
            after: $after->toArray(),
            metadata: ['source' => $source],
        ));
    }

    private function invalidatePermissions(string $userId, string $tenantId): void
    {
        $this->permissionProjectionInvalidationDispatcher->invalidate($userId, $tenantId);
    }

    private function recordCreatedOrAttachedAudit(ManagedUserData $user, string $action, string $source): void
    {
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $action,
            objectType: 'user',
            objectId: $user->userId,
            after: $user->toArray(),
            metadata: ['source' => $source],
        ));
    }
}
