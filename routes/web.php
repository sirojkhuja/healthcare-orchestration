<?php

use App\Modules\Observability\Application\Handlers\MetricsQueryHandler;
use App\Modules\Observability\Application\Queries\MetricsQuery;
use App\Shared\Infrastructure\Observability\Http\Middleware\RequirePrometheusScrapeKey;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Return JSON for API clients, SPA shell for browsers
    if (request()->expectsJson()) {
        return response()->json([
            'service' => config('app.name'),
            'status' => 'ok',
            'docs' => url('/docs'),
        ]);
    }
    return view('app');
});

// SPA catch-all — must come after all other web routes
Route::get('/{any}', fn () => view('app'))->where('any', '^(?!api|internal).*$');

Route::get('/internal/metrics', function (MetricsQueryHandler $handler) {
    return response($handler->handle(new MetricsQuery), 200, [
        'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
    ]);
})->middleware(RequirePrometheusScrapeKey::class);
