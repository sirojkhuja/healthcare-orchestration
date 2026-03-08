<?php

namespace App\Providers;

use App\Modules\AuditCompliance\Application\Contracts\AuditActorResolver;
use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Services\ContextualAuditTrailWriter;
use App\Modules\AuditCompliance\Infrastructure\AuthAuditActorResolver;
use App\Modules\AuditCompliance\Infrastructure\Persistence\DatabaseAuditEventRepository;
use App\Shared\Application\Contracts\EventContextFactory;
use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Infrastructure\Context\ContextBackedRequestMetadataContext;
use App\Shared\Infrastructure\Context\StandardEventContextFactory;
use App\Shared\Infrastructure\Persistence\TenantScope;
use App\Shared\Infrastructure\Storage\FilesystemFileStorageManager;
use App\Shared\Infrastructure\Tenancy\RequestTenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class MedFlowServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(FileStorageManager::class, FilesystemFileStorageManager::class);
        $this->app->bind(AuditActorResolver::class, AuthAuditActorResolver::class);
        $this->app->bind(AuditEventRepository::class, DatabaseAuditEventRepository::class);
        $this->app->bind(AuditTrailWriter::class, ContextualAuditTrailWriter::class);
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
    }
}
