<?php

namespace App\Providers;

use App\Modules\Reporting\Application\Contracts\ReportRepository;
use App\Modules\Reporting\Infrastructure\Persistence\DatabaseReportRepository;
use App\Shared\Application\Contracts\ReferenceCatalogRepository;
use App\Shared\Infrastructure\Reference\ConfigReferenceCatalogRepository;
use Illuminate\Support\ServiceProvider;

final class ReferenceSearchReportingServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(ReferenceCatalogRepository::class, ConfigReferenceCatalogRepository::class);
        $this->app->bind(ReportRepository::class, DatabaseReportRepository::class);
    }
}
