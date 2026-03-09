<?php

namespace App\Providers;

use App\Modules\AuditCompliance\Application\Contracts\AuditActorResolver;
use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Contracts\SecurityEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\SecurityEventWriter;
use App\Modules\AuditCompliance\Application\Services\ContextualAuditTrailWriter;
use App\Modules\AuditCompliance\Application\Services\ContextualSecurityEventWriter;
use App\Modules\AuditCompliance\Infrastructure\AuthAuditActorResolver;
use App\Modules\AuditCompliance\Infrastructure\Persistence\DatabaseAuditEventRepository;
use App\Modules\AuditCompliance\Infrastructure\Persistence\DatabaseSecurityEventRepository;
use App\Modules\IdentityAccess\Application\Contracts\AccessTokenService;
use App\Modules\IdentityAccess\Application\Contracts\ApiKeyRepository;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Contracts\DeviceRepository;
use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Contracts\ManagedUserRepository;
use App\Modules\IdentityAccess\Application\Contracts\MfaChallengeRepository;
use App\Modules\IdentityAccess\Application\Contracts\MfaCredentialRepository;
use App\Modules\IdentityAccess\Application\Contracts\MfaTotpService;
use App\Modules\IdentityAccess\Application\Contracts\PasswordResetManager;
use App\Modules\IdentityAccess\Application\Contracts\PermissionAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PermissionCatalog;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionInvalidationDispatcher;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionRepository;
use App\Modules\IdentityAccess\Application\Contracts\ProfileAvatarStore;
use App\Modules\IdentityAccess\Application\Contracts\ProfileRepository;
use App\Modules\IdentityAccess\Application\Contracts\RoleRepository;
use App\Modules\IdentityAccess\Application\Contracts\TenantIpAllowlistRepository;
use App\Modules\IdentityAccess\Application\Contracts\UserRoleAssignmentRepository;
use App\Modules\IdentityAccess\Application\Events\PermissionProjectionInvalidated;
use App\Modules\IdentityAccess\Infrastructure\Auth\ApiKeyRequestAuthenticator;
use App\Modules\IdentityAccess\Infrastructure\Auth\EloquentIdentityUserProvider;
use App\Modules\IdentityAccess\Infrastructure\Auth\FirebaseJwtAccessTokenService;
use App\Modules\IdentityAccess\Infrastructure\Auth\JwtRequestAuthenticator;
use App\Modules\IdentityAccess\Infrastructure\Auth\LaravelAuthenticatedRequestContext;
use App\Modules\IdentityAccess\Infrastructure\Auth\LaravelPasswordResetManager;
use App\Modules\IdentityAccess\Infrastructure\Auth\Persistence\DatabaseApiKeyRepository;
use App\Modules\IdentityAccess\Infrastructure\Auth\Persistence\DatabaseAuthSessionRepository;
use App\Modules\IdentityAccess\Infrastructure\Auth\Persistence\DatabaseMfaChallengeRepository;
use App\Modules\IdentityAccess\Infrastructure\Auth\Persistence\DatabaseMfaCredentialRepository;
use App\Modules\IdentityAccess\Infrastructure\Auth\TotpMfaService;
use App\Modules\IdentityAccess\Infrastructure\Authorization\CachedPermissionAuthorizer;
use App\Modules\IdentityAccess\Infrastructure\Authorization\ConfigPermissionCatalog;
use App\Modules\IdentityAccess\Infrastructure\Authorization\LaravelPermissionProjectionInvalidationDispatcher;
use App\Modules\IdentityAccess\Infrastructure\Authorization\Persistence\DatabasePermissionProjectionRepository;
use App\Modules\IdentityAccess\Infrastructure\Authorization\Persistence\DatabaseRoleRepository;
use App\Modules\IdentityAccess\Infrastructure\Authorization\Persistence\DatabaseUserRoleAssignmentRepository;
use App\Modules\IdentityAccess\Infrastructure\Devices\Persistence\DatabaseDeviceRepository;
use App\Modules\IdentityAccess\Infrastructure\Profiles\Persistence\DatabaseProfileRepository;
use App\Modules\IdentityAccess\Infrastructure\Profiles\Storage\AttachmentBackedProfileAvatarStore;
use App\Modules\IdentityAccess\Infrastructure\Security\CidrMatcher;
use App\Modules\IdentityAccess\Infrastructure\Security\Persistence\DatabaseTenantIpAllowlistRepository;
use App\Modules\IdentityAccess\Infrastructure\Users\Persistence\DatabaseManagedUserRepository;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Infrastructure\Persistence\DatabasePatientRepository;
use App\Modules\TenantManagement\Application\Contracts\ClinicRepository;
use App\Modules\TenantManagement\Application\Contracts\LocationReferenceRepository;
use App\Modules\TenantManagement\Application\Contracts\TenantConfigurationRepository;
use App\Modules\TenantManagement\Application\Contracts\TenantMetricsRepository;
use App\Modules\TenantManagement\Application\Contracts\TenantRepository;
use App\Modules\TenantManagement\Infrastructure\Persistence\DatabaseClinicRepository;
use App\Modules\TenantManagement\Infrastructure\Persistence\DatabaseTenantConfigurationRepository;
use App\Modules\TenantManagement\Infrastructure\Persistence\DatabaseTenantMetricsRepository;
use App\Modules\TenantManagement\Infrastructure\Persistence\DatabaseTenantRepository;
use App\Modules\TenantManagement\Infrastructure\Reference\ConfigLocationReferenceRepository;
use App\Shared\Application\Contracts\CacheKeyBuilder;
use App\Shared\Application\Contracts\ConsumerReceiptStore;
use App\Shared\Application\Contracts\EventContextFactory;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\IdempotencyStore;
use App\Shared\Application\Contracts\KafkaProducer;
use App\Shared\Application\Contracts\OutboxRepository;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantCache;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Services\ExponentialBackoffRetryStrategy;
use App\Shared\Application\Services\IdempotentKafkaConsumerBus;
use App\Shared\Application\Services\OutboxRelay;
use App\Shared\Infrastructure\Cache\TenantCacheKeyBuilder;
use App\Shared\Infrastructure\Cache\VersionedTenantCache;
use App\Shared\Infrastructure\Context\ContextBackedRequestMetadataContext;
use App\Shared\Infrastructure\Context\StandardEventContextFactory;
use App\Shared\Infrastructure\Idempotency\Persistence\DatabaseIdempotencyStore;
use App\Shared\Infrastructure\Messaging\Kafka\LongLangKafkaProducer;
use App\Shared\Infrastructure\Messaging\Persistence\DatabaseConsumerReceiptStore;
use App\Shared\Infrastructure\Messaging\Persistence\DatabaseOutboxRepository;
use App\Shared\Infrastructure\Persistence\TenantScope;
use App\Shared\Infrastructure\Storage\FilesystemFileStorageManager;
use App\Shared\Infrastructure\Tenancy\RequestTenantContext;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

final class MedFlowServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(AccessTokenService::class, FirebaseJwtAccessTokenService::class);
        $this->app->bind(ApiKeyRepository::class, DatabaseApiKeyRepository::class);
        $this->app->scoped(AuthenticatedRequestContext::class, LaravelAuthenticatedRequestContext::class);
        $this->app->bind(AuthSessionRepository::class, DatabaseAuthSessionRepository::class);
        $this->app->bind(DeviceRepository::class, DatabaseDeviceRepository::class);
        $this->app->bind(MfaChallengeRepository::class, DatabaseMfaChallengeRepository::class);
        $this->app->bind(MfaCredentialRepository::class, DatabaseMfaCredentialRepository::class);
        $this->app->bind(ManagedUserRepository::class, DatabaseManagedUserRepository::class);
        $this->app->singleton(MfaTotpService::class, TotpMfaService::class);
        $this->app->singleton(CidrMatcher::class, CidrMatcher::class);
        $this->app->singleton(CacheKeyBuilder::class, TenantCacheKeyBuilder::class);
        $this->app->singleton(ConsumerReceiptStore::class, DatabaseConsumerReceiptStore::class);
        $this->app->singleton(FileStorageManager::class, FilesystemFileStorageManager::class);
        $this->app->bind(IdentityUserProvider::class, EloquentIdentityUserProvider::class);
        $this->app->bind(PasswordResetManager::class, LaravelPasswordResetManager::class);
        $this->app->bind(IdempotencyStore::class, DatabaseIdempotencyStore::class);
        $this->app->bind(ProfileAvatarStore::class, AttachmentBackedProfileAvatarStore::class);
        $this->app->bind(ProfileRepository::class, DatabaseProfileRepository::class);
        $this->app->singleton(KafkaProducer::class, LongLangKafkaProducer::class);
        $this->app->singleton(OutboxRepository::class, DatabaseOutboxRepository::class);
        $this->app->singleton(ExponentialBackoffRetryStrategy::class, ExponentialBackoffRetryStrategy::class);
        $this->app->singleton(IdempotentKafkaConsumerBus::class, IdempotentKafkaConsumerBus::class);
        $this->app->singleton(OutboxRelay::class, OutboxRelay::class);
        $this->app->singleton(TenantCache::class, VersionedTenantCache::class);
        $this->app->bind(AuditActorResolver::class, AuthAuditActorResolver::class);
        $this->app->bind(AuditEventRepository::class, DatabaseAuditEventRepository::class);
        $this->app->bind(AuditTrailWriter::class, ContextualAuditTrailWriter::class);
        $this->app->bind(ClinicRepository::class, DatabaseClinicRepository::class);
        $this->app->bind(LocationReferenceRepository::class, ConfigLocationReferenceRepository::class);
        $this->app->bind(PatientRepository::class, DatabasePatientRepository::class);
        $this->app->bind(SecurityEventRepository::class, DatabaseSecurityEventRepository::class);
        $this->app->bind(SecurityEventWriter::class, ContextualSecurityEventWriter::class);
        $this->app->singleton(PermissionCatalog::class, ConfigPermissionCatalog::class);
        $this->app->singleton(PermissionAuthorizer::class, CachedPermissionAuthorizer::class);
        $this->app->bind(PermissionProjectionInvalidationDispatcher::class, LaravelPermissionProjectionInvalidationDispatcher::class);
        $this->app->bind(PermissionProjectionRepository::class, DatabasePermissionProjectionRepository::class);
        $this->app->bind(RoleRepository::class, DatabaseRoleRepository::class);
        $this->app->scoped(RequestMetadataContext::class, ContextBackedRequestMetadataContext::class);
        $this->app->bind(TenantConfigurationRepository::class, DatabaseTenantConfigurationRepository::class);
        $this->app->bind(TenantIpAllowlistRepository::class, DatabaseTenantIpAllowlistRepository::class);
        $this->app->bind(TenantMetricsRepository::class, DatabaseTenantMetricsRepository::class);
        $this->app->bind(TenantRepository::class, DatabaseTenantRepository::class);
        $this->app->bind(UserRoleAssignmentRepository::class, DatabaseUserRoleAssignmentRepository::class);
        $this->app->scoped(TenantContext::class, RequestTenantContext::class);
        $this->app->scoped(TenantScope::class, fn () => new TenantScope($this->app->make(TenantContext::class)));
        $this->app->bind(EventContextFactory::class, fn () => new StandardEventContextFactory(
            $this->app->make(RequestMetadataContext::class),
            $this->app->make(TenantContext::class),
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict($this->app->environment() !== 'production');
        Auth::viaRequest('medflow-jwt', fn (Request $request) => $this->app->make(JwtRequestAuthenticator::class)->authenticate($request));
        Auth::viaRequest('medflow-api-key', fn (Request $request) => $this->app->make(ApiKeyRequestAuthenticator::class)->authenticate($request));
        $this->app->make(Dispatcher::class)->listen(function (PermissionProjectionInvalidated $event): void {
            $this->app->make(PermissionAuthorizer::class)->forget($event->userId, $event->tenantId);
        });
    }
}
