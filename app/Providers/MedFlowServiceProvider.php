<?php

namespace App\Providers;

use App\Shared\Application\Contracts\FileStorageManager;
use App\Shared\Infrastructure\Storage\FilesystemFileStorageManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

final class MedFlowServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(FileStorageManager::class, FilesystemFileStorageManager::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict($this->app->environment() !== 'production');
    }
}
