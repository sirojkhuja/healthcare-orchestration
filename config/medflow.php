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
    'auth' => [
        'access_token_ttl_minutes' => (int) env('AUTH_ACCESS_TOKEN_TTL_MINUTES', 15),
        'refresh_token_ttl_days' => (int) env('AUTH_REFRESH_TOKEN_TTL_DAYS', 30),
        'api_keys' => [
            'header' => env('AUTH_API_KEY_HEADER', 'X-API-Key'),
            'prefix' => env('AUTH_API_KEY_PREFIX', 'mfk'),
            'secret_bytes' => (int) env('AUTH_API_KEY_SECRET_BYTES', 32),
        ],
        'mfa' => [
            'issuer' => env('AUTH_MFA_ISSUER', 'MedFlow'),
            'digits' => (int) env('AUTH_MFA_DIGITS', 6),
            'period_seconds' => (int) env('AUTH_MFA_PERIOD_SECONDS', 30),
            'window' => (int) env('AUTH_MFA_WINDOW', 1),
            'challenge_ttl_minutes' => (int) env('AUTH_MFA_CHALLENGE_TTL_MINUTES', 10),
            'recovery_codes_count' => (int) env('AUTH_MFA_RECOVERY_CODES_COUNT', 8),
            'recovery_code_length' => (int) env('AUTH_MFA_RECOVERY_CODE_LENGTH', 10),
        ],
        'jwt' => [
            'issuer' => env('AUTH_JWT_ISSUER', 'medflow'),
            'audience' => env('AUTH_JWT_AUDIENCE', 'medflow-api'),
            'algorithm' => env('AUTH_JWT_ALGORITHM', 'HS256'),
            'secret' => env('AUTH_JWT_SECRET', ''),
        ],
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
    'lab' => [
        'webhook_signature_header' => env('LAB_WEBHOOK_SIGNATURE_HEADER', 'X-Lab-Signature'),
        'providers' => [
            'mock-lab' => [
                'secret' => env('LAB_PROVIDER_MOCK_LAB_SECRET', 'mock-lab-secret'),
                'external_order_prefix' => env('LAB_PROVIDER_MOCK_LAB_EXTERNAL_ORDER_PREFIX', 'mocklab'),
            ],
        ],
    ],
    'kafka' => [
        'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
        'client_id' => env('KAFKA_CLIENT_ID', 'medflow-app'),
        'group_id' => env('KAFKA_GROUP_ID', 'medflow-local'),
        'acks' => (int) env('KAFKA_ACKS', 1),
        'transport' => [
            'produce_retry' => (int) env('KAFKA_PRODUCE_RETRY', 3),
            'produce_retry_sleep' => (float) env('KAFKA_PRODUCE_RETRY_SLEEP', 0.1),
        ],
        'consumer' => [
            'poll_interval_seconds' => (float) env('KAFKA_CONSUMER_POLL_INTERVAL_SECONDS', 0.1),
            'group_retry' => (int) env('KAFKA_CONSUMER_GROUP_RETRY', 5),
            'group_retry_sleep' => (float) env('KAFKA_CONSUMER_GROUP_RETRY_SLEEP', 1),
        ],
        'outbox' => [
            'batch_size' => (int) env('KAFKA_OUTBOX_BATCH_SIZE', 50),
            'max_attempts' => (int) env('KAFKA_OUTBOX_MAX_ATTEMPTS', 10),
            'backoff_seconds' => (int) env('KAFKA_OUTBOX_BACKOFF_SECONDS', 5),
            'backoff_max_seconds' => (int) env('KAFKA_OUTBOX_BACKOFF_MAX_SECONDS', 300),
        ],
    ],
    'tenancy' => [
        'header' => env('TENANCY_HEADER', 'X-Tenant-Id'),
        'route_parameters' => [
            'tenantId',
            'tenant',
        ],
    ],
];
