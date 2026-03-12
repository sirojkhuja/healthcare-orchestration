<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV')),
    'release' => env('SENTRY_RELEASE', env('APP_GIT_SHA')),
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null
        ? null
        : (float) env('SENTRY_TRACES_SAMPLE_RATE'),
    'send_default_pii' => (bool) env('SENTRY_SEND_DEFAULT_PII', false),
];
