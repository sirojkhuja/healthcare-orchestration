<?php

use App\Modules\Observability\Application\Handlers\MetricsQueryHandler;
use App\Modules\Observability\Application\Queries\MetricsQuery;
use App\Shared\Infrastructure\Observability\Http\Middleware\RequirePrometheusScrapeKey;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => config('app.name'),
        'status' => 'ok',
        'docs' => url('/docs'),
    ]);
});

Route::get('/internal/metrics', function (MetricsQueryHandler $handler) {
    return response($handler->handle(new MetricsQuery), 200, [
        'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
    ]);
})->middleware(RequirePrometheusScrapeKey::class);
