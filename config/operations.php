<?php

return [
    'health' => [
        'outbox_warning_age_seconds' => (int) env('OPS_HEALTH_OUTBOX_WARNING_AGE_SECONDS', 60),
    ],
    'cache' => [
        'domains' => [
            'permissions',
            'availability',
            'settings',
            'reference-data',
            'integrations',
            'feature-flags',
            'rate-limits',
            'ops',
        ],
    ],
    'feature_flags' => [
        'myid' => [
            'name' => 'MyID',
            'description' => 'Enable optional MyID identity verification flows for the active tenant.',
            'module' => 'Integrations',
        ],
        'eimzo' => [
            'name' => 'E-IMZO',
            'description' => 'Enable optional E-IMZO signing flows for the active tenant.',
            'module' => 'Integrations',
        ],
    ],
    'rate_limits' => [
        'auth.login' => [
            'name' => 'Auth Login',
            'description' => 'Interactive login attempts for JWT issuance.',
            'requests_per_minute' => 10,
            'burst' => 5,
        ],
        'payments.initiate' => [
            'name' => 'Payments Initiate',
            'description' => 'Tenant payment initiation requests.',
            'requests_per_minute' => 30,
            'burst' => 10,
        ],
        'appointments.mutate' => [
            'name' => 'Appointments Mutate',
            'description' => 'Appointment create, reschedule, and lifecycle actions.',
            'requests_per_minute' => 60,
            'burst' => 20,
        ],
        'notifications.send' => [
            'name' => 'Notifications Send',
            'description' => 'Queue-first notification dispatch and retry actions.',
            'requests_per_minute' => 60,
            'burst' => 20,
        ],
        'webhooks.inbound' => [
            'name' => 'Webhooks Inbound',
            'description' => 'Inbound verified webhook deliveries per tenant.',
            'requests_per_minute' => 180,
            'burst' => 60,
        ],
        'admin.actions' => [
            'name' => 'Admin Actions',
            'description' => 'Administrative mutations under /admin.',
            'requests_per_minute' => 30,
            'burst' => 10,
        ],
    ],
    'logging_pipelines' => [
        'app-json' => [
            'name' => 'Application JSON Logs',
            'destination' => 'stdout',
            'enabled' => true,
        ],
        'otel-export' => [
            'name' => 'OpenTelemetry Export',
            'destination' => 'otel-collector',
            'enabled' => true,
        ],
        'prometheus-scrape' => [
            'name' => 'Prometheus Scrape',
            'destination' => 'prometheus',
            'enabled' => true,
        ],
        'elastic-shipper' => [
            'name' => 'Elastic Shipper',
            'destination' => 'elastic',
            'enabled' => true,
        ],
    ],
    'runtime' => [
        'git_sha' => env('APP_GIT_SHA'),
    ],
];
