<?php

namespace App\Providers;

use App\Modules\AuditCompliance\Application\Contracts\AuditActorResolver;
use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Services\ContextualAuditTrailWriter;
use App\Modules\AuditCompliance\Infrastructure\AuthAuditActorResolver;
use App\Modules\AuditCompliance\Infrastructure\Persistence\DatabaseAuditEventRepository;
use App\Modules\IdentityAccess\Application\Contracts\AccessTokenService;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\IdentityAccess\Application\Contracts\AuthSessionRepository;
use App\Modules\IdentityAccess\Application\Contracts\IdentityUserProvider;
use App\Modules\IdentityAccess\Application\Contracts\PasswordResetManager;
use App\Modules\IdentityAccess\Application\Contracts\PermissionAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PermissionProjectionRepository;
use App\Modules\IdentityAccess\Application\Events\PermissionProjectionInvalidated;
use App\Modules\IdentityAccess\Infrastructure\Auth\EloquentIdentityUserProvider;
use App\Modules\IdentityAccess\Infrastructure\Auth\FirebaseJwtAccessTokenService;
use App\Modules\IdentityAccess\Infrastructure\Auth\JwtRequestAuthenticator;
use App\Modules\IdentityAccess\Infrastructure\Auth\LaravelAuthenticatedRequestContext;
use App\Modules\IdentityAccess\Infrastructure\Auth\LaravelPasswordResetManager;
use App\Modules\IdentityAccess\Infrastructure\Auth\Persistence\DatabaseAuthSessionRepository;
use App\Modules\IdentityAccess\Infrastructure\Authorization\CachedPermissionAuthorizer;
use App\Modules\IdentityAccess\Infrastructure\Authorization\NullPermissionProjectionRepository;
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
        $this->app->scoped(AuthenticatedRequestContext::class, LaravelAuthenticatedRequestContext::class);
        $this->app->bind(AuthSessionRepository::class, DatabaseAuthSessionRepository::class);
        $this->app->singleton(CacheKeyBuilder::class, TenantCacheKeyBuilder::class);
        $this->app->singleton(ConsumerReceiptStore::class, DatabaseConsumerReceiptStore::class);
        $this->app->singleton(FileStorageManager::class, FilesystemFileStorageManager::class);
        $this->app->bind(IdentityUserProvider::class, EloquentIdentityUserProvider::class);
        $this->app->bind(PasswordResetManager::class, LaravelPasswordResetManager::class);
        $this->app->bind(IdempotencyStore::class, DatabaseIdempotencyStore::class);
        $this->app->singleton(KafkaProducer::class, LongLangKafkaProducer::class);
        $this->app->singleton(OutboxRepository::class, DatabaseOutboxRepository::class);
        $this->app->singleton(ExponentialBackoffRetryStrategy::class, ExponentialBackoffRetryStrategy::class);
        $this->app->singleton(IdempotentKafkaConsumerBus::class, IdempotentKafkaConsumerBus::class);
        $this->app->singleton(OutboxRelay::class, OutboxRelay::class);
        $this->app->singleton(TenantCache::class, VersionedTenantCache::class);
        $this->app->bind(AuditActorResolver::class, AuthAuditActorResolver::class);
        $this->app->bind(AuditEventRepository::class, DatabaseAuditEventRepository::class);
        $this->app->bind(AuditTrailWriter::class, ContextualAuditTrailWriter::class);
        $this->app->bind(PermissionProjectionRepository::class, NullPermissionProjectionRepository::class);
        $this->app->singleton(PermissionAuthorizer::class, CachedPermissionAuthorizer::class);
        $this->app->scoped(RequestMetadataContext::class, ContextBackedRequestMetadataContext::class);
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
        $this->app->make(Dispatcher::class)->listen(function (PermissionProjectionInvalidated $event): void {
            $this->app->make(PermissionAuthorizer::class)->forget($event->userId, $event->tenantId);
        });
    }
}
