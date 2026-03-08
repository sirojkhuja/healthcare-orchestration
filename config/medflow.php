<?php

return [
    'version' => env('APP_VERSION', '0.1.0'),
    'modules' => [
        'IdentityAccess',
        'TenantManagement',
        'Patient',
        'Provider',
        'Scheduling',
        'Treatment',
        'Lab',
        'Pharmacy',
        'Billing',
        'Insurance',
        'Notifications',
        'Integrations',
        'AuditCompliance',
        'Observability',
    ],
    'storage' => [
        'attachments_disk' => 'attachments',
        'exports_disk' => 'exports',
        'artifacts_disk' => 'artifacts',
    ],
    'audit' => [
        'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 0),
    ],
    'request_context' => [
        'headers' => [
            'request_id' => env('REQUEST_ID_HEADER', 'X-Request-Id'),
            'correlation_id' => env('CORRELATION_ID_HEADER', 'X-Correlation-Id'),
            'causation_id' => env('CAUSATION_ID_HEADER', 'X-Causation-Id'),
        ],
    ],
    'idempotency' => [
        'header' => env('IDEMPOTENCY_HEADER', 'Idempotency-Key'),
        'replay_header' => env('IDEMPOTENCY_REPLAY_HEADER', 'X-Idempotent-Replay'),
        'retention_hours' => (int) env('IDEMPOTENCY_RETENTION_HOURS', 24),
    ],
    'cache' => [
        'namespace' => env('MEDFLOW_CACHE_NAMESPACE', 'medflow'),
    ],
    'tenancy' => [
        'header' => env('TENANCY_HEADER', 'X-Tenant-Id'),
        'route_parameters' => [
            'tenantId',
            'tenant',
        ],
    ],
];
