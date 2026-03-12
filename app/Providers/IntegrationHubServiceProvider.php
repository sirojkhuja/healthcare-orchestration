<?php

namespace App\Providers;

use App\Modules\Integrations\Application\Contracts\EImzoSignRequestRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationCatalog;
use App\Modules\Integrations\Application\Contracts\IntegrationCredentialRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationLogRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationPluginWebhookDeliveryRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationStateRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationTokenRepository;
use App\Modules\Integrations\Application\Contracts\IntegrationWebhookRepository;
use App\Modules\Integrations\Application\Contracts\MyIdVerificationRepository;
use App\Modules\Integrations\Infrastructure\Config\ConfigIntegrationCatalog;
use App\Modules\Integrations\Infrastructure\Persistence\DatabaseEImzoSignRequestRepository;
use App\Modules\Integrations\Infrastructure\Persistence\DatabaseIntegrationCredentialRepository;
use App\Modules\Integrations\Infrastructure\Persistence\DatabaseIntegrationLogRepository;
use App\Modules\Integrations\Infrastructure\Persistence\DatabaseIntegrationPluginWebhookDeliveryRepository;
use App\Modules\Integrations\Infrastructure\Persistence\DatabaseIntegrationStateRepository;
use App\Modules\Integrations\Infrastructure\Persistence\DatabaseIntegrationTokenRepository;
use App\Modules\Integrations\Infrastructure\Persistence\DatabaseIntegrationWebhookRepository;
use App\Modules\Integrations\Infrastructure\Persistence\DatabaseMyIdVerificationRepository;
use Illuminate\Support\ServiceProvider;

final class IntegrationHubServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(IntegrationCatalog::class, ConfigIntegrationCatalog::class);
        $this->app->bind(IntegrationStateRepository::class, DatabaseIntegrationStateRepository::class);
        $this->app->bind(IntegrationCredentialRepository::class, DatabaseIntegrationCredentialRepository::class);
        $this->app->bind(IntegrationLogRepository::class, DatabaseIntegrationLogRepository::class);
        $this->app->bind(IntegrationWebhookRepository::class, DatabaseIntegrationWebhookRepository::class);
        $this->app->bind(IntegrationTokenRepository::class, DatabaseIntegrationTokenRepository::class);
        $this->app->bind(MyIdVerificationRepository::class, DatabaseMyIdVerificationRepository::class);
        $this->app->bind(EImzoSignRequestRepository::class, DatabaseEImzoSignRequestRepository::class);
        $this->app->bind(
            IntegrationPluginWebhookDeliveryRepository::class,
            DatabaseIntegrationPluginWebhookDeliveryRepository::class,
        );
    }
}
