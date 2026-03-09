<?php

namespace App\Modules\TenantManagement\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Contracts\PermissionCatalog;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionInvalidationDispatcher;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Contracts\TenantIpAllowlistRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use App\Modules\Scheduling\Application\Contracts\AvailabilityCacheInvalidator;
use App\Modules\TenantManagement\Application\Contracts\TenantConfigurationRepository;
use App\Modules\TenantManagement\Application\Contracts\TenantMetricsRepository;
use App\Modules\TenantManagement\Application\Contracts\TenantRepository;
use App\Modules\TenantManagement\Application\Data\TenantData;
use App\Modules\TenantManagement\Application\Data\TenantLimitsData;
use App\Modules\TenantManagement\Application\Data\TenantSettingsData;
use App\Modules\TenantManagement\Application\Data\TenantUsageData;
use App\Modules\TenantManagement\Domain\Tenants\TenantStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TenantAdministrationService
{
    public function __construct(
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly TenantRepository $tenantRepository,
        private readonly TenantConfigurationRepository $tenantConfigurationRepository,
        private readonly TenantMetricsRepository $tenantMetricsRepository,
        private readonly ManagedUserRepository $managedUserRepository,
        private readonly RoleRepository $roleRepository,
        private readonly UserRoleAssignmentRepository $userRoleAssignmentRepository,
        private readonly PermissionCatalog $permissionCatalog,
        private readonly PermissionProjectionInvalidationDispatcher $permissionProjectionInvalidationDispatcher,
        private readonly TenantIpAllowlistRepository $tenantIpAllowlistRepository,
        private readonly AvailabilityCacheInvalidator $availabilityCacheInvalidator,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    /**
     * @return list<TenantData>
     */
    public function list(?string $search = null, ?string $status = null): array
    {
        return $this->tenantRepository->listVisibleToUser($this->currentUserId(), $search, $status);
    }

    public function create(string $name, ?string $contactEmail, ?string $contactPhone): TenantData
    {
        $userId = $this->currentUserId();

        /** @var TenantData $tenant */
        $tenant = DB::transaction(function () use ($userId, $name, $contactEmail, $contactPhone): TenantData {
            $tenant = $this->tenantRepository->create(
                trim($name),
                $this->normalizedEmail($contactEmail),
                $this->normalizedPhone($contactPhone),
                TenantStatus::ACTIVE,
            );

            $this->tenantConfigurationRepository->replaceSettings($tenant->tenantId, $this->defaultSettings());
            $this->tenantConfigurationRepository->replaceLimits($tenant->tenantId, new TenantLimitsData);
            $this->managedUserRepository->attachToTenant($userId, $tenant->tenantId, 'active');

            $role = $this->roleRepository->create(
                $tenant->tenantId,
                'Tenant Administrator',
                'Bootstrap administrator role created during tenant provisioning.',
            );
            $this->roleRepository->replacePermissions($role->roleId, $tenant->tenantId, $this->bootstrapPermissions());
            $this->userRoleAssignmentRepository->replaceRolesForUser($userId, $tenant->tenantId, [$role->roleId]);
            $this->permissionProjectionInvalidationDispatcher->invalidate($userId, $tenant->tenantId);

            $visible = $this->tenantRepository->findVisibleToUser($tenant->tenantId, $userId);

            if ($visible === null) {
                throw new LogicException('The created tenant could not be reloaded for the authenticated actor.');
            }

            $this->auditTrailWriter->record(new AuditRecordInput(
                action: 'tenants.created',
                objectType: 'tenant',
                objectId: $visible->tenantId,
                after: $visible->toArray(),
                metadata: [
                    'source' => 'api',
                    'bootstrap_user_id' => $userId,
                    'bootstrap_role_id' => $role->roleId,
                ],
            ));

            return $visible;
        });

        return $tenant;
    }

    public function get(string $tenantId): TenantData
    {
        return $this->tenantOrFail($tenantId);
    }

    public function update(
        string $tenantId,
        bool $nameProvided,
        ?string $name,
        bool $contactEmailProvided,
        ?string $contactEmail,
        bool $contactPhoneProvided,
        ?string $contactPhone,
    ): TenantData {
        $tenant = $this->tenantOrFail($tenantId);
        $updates = $this->tenantUpdates(
            $tenant,
            $nameProvided,
            $name,
            $contactEmailProvided,
            $contactEmail,
            $contactPhoneProvided,
            $contactPhone,
        );

        if ($updates === []) {
            return $tenant;
        }

        $updated = $this->persistTenantUpdate($tenant->tenantId, $updates);
        $this->auditChange('tenants.updated', $tenant, $updated);

        return $updated;
    }

    public function activate(string $tenantId): TenantData
    {
        return $this->transition($tenantId, TenantStatus::ACTIVE, 'tenants.activated');
    }

    public function suspend(string $tenantId): TenantData
    {
        return $this->transition($tenantId, TenantStatus::SUSPENDED, 'tenants.suspended');
    }

    public function delete(string $tenantId): TenantData
    {
        $tenant = $this->tenantOrFail($tenantId);

        if (! TenantStatus::isDeletable($tenant->status)) {
            throw new ConflictHttpException('Only suspended tenants may be deleted.');
        }

        $memberUserIds = $this->tenantRepository->memberUserIds($tenantId);

        DB::transaction(function () use ($tenant, $memberUserIds): void {
            $this->tenantConfigurationRepository->deleteForTenant($tenant->tenantId);
            $this->tenantIpAllowlistRepository->replaceForTenant($tenant->tenantId, []);
            $this->deleteTenantRoles($tenant->tenantId);
            $this->deleteTenantMemberships($tenant->tenantId, $memberUserIds);

            if (! $this->tenantRepository->delete($tenant->tenantId)) {
                throw new LogicException('The tenant could not be deleted.');
            }
        });

        $this->permissionProjectionInvalidationDispatcher->invalidateMany($memberUserIds, $tenant->tenantId);
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'tenants.deleted',
            objectType: 'tenant',
            objectId: $tenant->tenantId,
            before: $tenant->toArray(),
            metadata: [
                'source' => 'api',
                'deleted_memberships' => count($memberUserIds),
            ],
        ));

        return $tenant;
    }

    public function limits(string $tenantId): TenantLimitsData
    {
        $this->tenantOrFail($tenantId);

        return $this->tenantConfigurationRepository->limits($tenantId);
    }

    public function updateLimits(string $tenantId, TenantLimitsData $limits): TenantLimitsData
    {
        $this->tenantOrFail($tenantId);
        $before = $this->tenantConfigurationRepository->limits($tenantId);
        $after = $this->tenantConfigurationRepository->replaceLimits($tenantId, $limits);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'tenants.limits_updated',
            objectType: 'tenant',
            objectId: $tenantId,
            before: $before->toArray(),
            after: $after->toArray(),
            metadata: ['source' => 'api'],
        ));

        return $after;
    }

    public function settings(string $tenantId): TenantSettingsData
    {
        $this->tenantOrFail($tenantId);

        return $this->tenantConfigurationRepository->settings($tenantId);
    }

    public function updateSettings(string $tenantId, TenantSettingsData $settings): TenantSettingsData
    {
        $this->tenantOrFail($tenantId);
        $before = $this->tenantConfigurationRepository->settings($tenantId);
        $after = $this->tenantConfigurationRepository->replaceSettings($tenantId, $this->normalizedSettings($settings));

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'tenants.settings_updated',
            objectType: 'tenant',
            objectId: $tenantId,
            before: $before->toArray(),
            after: $after->toArray(),
            metadata: ['source' => 'api'],
        ));
        $this->availabilityCacheInvalidator->invalidate($tenantId);

        return $after;
    }

    public function usage(string $tenantId): TenantUsageData
    {
        $tenant = $this->tenantOrFail($tenantId);
        $limits = $this->tenantConfigurationRepository->limits($tenantId);

        return new TenantUsageData(
            tenantId: $tenant->tenantId,
            usersUsed: $this->tenantMetricsRepository->users($tenantId),
            usersLimit: $limits->users,
            clinicsUsed: $this->tenantMetricsRepository->clinics($tenantId),
            clinicsLimit: $limits->clinics,
            providersUsed: $this->tenantMetricsRepository->providers($tenantId),
            providersLimit: $limits->providers,
            patientsUsed: $this->tenantMetricsRepository->patients($tenantId),
            patientsLimit: $limits->patients,
            storageGbUsed: $this->tenantMetricsRepository->storageGb($tenantId),
            storageGbLimit: $limits->storageGb,
            monthlyNotificationsUsed: $this->tenantMetricsRepository->monthlyNotifications($tenantId),
            monthlyNotificationsLimit: $limits->monthlyNotifications,
        );
    }

    /**
     * @return array<string, string|null>
     */
    private function tenantUpdates(
        TenantData $tenant,
        bool $nameProvided,
        ?string $name,
        bool $contactEmailProvided,
        ?string $contactEmail,
        bool $contactPhoneProvided,
        ?string $contactPhone,
    ): array {
        $updates = [];

        if ($nameProvided) {
            $resolvedName = trim((string) $name);

            if ($resolvedName !== '' && $resolvedName !== $tenant->name) {
                $updates['name'] = $resolvedName;
            }
        }

        if ($contactEmailProvided) {
            $resolvedEmail = $this->normalizedEmail($contactEmail);

            if ($resolvedEmail !== $tenant->contactEmail) {
                $updates['contact_email'] = $resolvedEmail;
            }
        }

        if ($contactPhoneProvided) {
            $resolvedPhone = $this->normalizedPhone($contactPhone);

            if ($resolvedPhone !== $tenant->contactPhone) {
                $updates['contact_phone'] = $resolvedPhone;
            }
        }

        return $updates;
    }

    /**
     * @param  array<string, CarbonImmutable|string|null>  $updates
     */
    private function persistTenantUpdate(string $tenantId, array $updates): TenantData
    {
        $updated = $this->tenantRepository->update($tenantId, $updates);

        if (! $updated) {
            throw new LogicException('The tenant update could not be persisted.');
        }

        return $this->tenantOrFail($tenantId);
    }

    private function transition(string $tenantId, string $targetStatus, string $auditAction): TenantData
    {
        $tenant = $this->tenantOrFail($tenantId);

        if (! TenantStatus::canTransition($tenant->status, $targetStatus)) {
            throw new ConflictHttpException('The requested tenant lifecycle transition is not allowed from the current state.');
        }

        $timestamp = CarbonImmutable::now();
        $updated = $this->persistTenantUpdate($tenantId, [
            'status' => $targetStatus,
            'activated_at' => $targetStatus === TenantStatus::ACTIVE ? $timestamp : $tenant->activatedAt,
            'suspended_at' => $targetStatus === TenantStatus::SUSPENDED ? $timestamp : null,
        ]);
        $this->auditChange($auditAction, $tenant, $updated);

        return $updated;
    }

    private function auditChange(string $action, TenantData $before, TenantData $after): void
    {
        $this->auditTrailWriter->record(new AuditRecordInput(
            action: $action,
            objectType: 'tenant',
            objectId: $after->tenantId,
            before: $before->toArray(),
            after: $after->toArray(),
            metadata: ['source' => 'api'],
        ));
    }

    private function tenantOrFail(string $tenantId): TenantData
    {
        $tenant = $this->tenantRepository->findVisibleToUser($tenantId, $this->currentUserId());

        if ($tenant === null) {
            throw new NotFoundHttpException('The requested tenant does not belong to the authenticated actor.');
        }

        return $tenant;
    }

    private function currentUserId(): string
    {
        return $this->authenticatedRequestContext->current()->user->id;
    }

    /**
     * @param  list<string>  $memberUserIds
     */
    private function deleteTenantMemberships(string $tenantId, array $memberUserIds): void
    {
        foreach ($memberUserIds as $userId) {
            $this->managedUserRepository->deleteFromTenant($userId, $tenantId);
        }
    }

    private function deleteTenantRoles(string $tenantId): void
    {
        foreach ($this->roleRepository->listForTenant($tenantId) as $role) {
            $this->roleRepository->deleteInTenant($role->roleId, $tenantId);
        }
    }

    private function defaultSettings(): TenantSettingsData
    {
        return new TenantSettingsData(
            locale: $this->configString('app.locale'),
            timezone: $this->configString('app.timezone'),
            currency: null,
        );
    }

    /**
     * @return list<string>
     */
    private function bootstrapPermissions(): array
    {
        return array_map(
            static fn ($definition): string => $definition->name,
            $this->permissionCatalog->all(),
        );
    }

    private function normalizedEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $normalized = strtolower(trim($email));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizedPhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $normalized = trim($phone);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizedSettings(TenantSettingsData $settings): TenantSettingsData
    {
        $currency = $settings->currency !== null ? strtoupper(trim($settings->currency)) : null;

        return new TenantSettingsData(
            locale: $this->nullableTrimmed($settings->locale),
            timezone: $this->nullableTrimmed($settings->timezone),
            currency: $currency !== '' ? $currency : null,
        );
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function configString(string $key): ?string
    {
        /** @var mixed $value */
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
