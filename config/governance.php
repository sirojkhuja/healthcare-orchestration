<?php

return [
    'architecture' => [
        'file_size' => [
            'hard_limit' => 400,
            'reviewed_exceptions' => [
                'app/Modules/TenantManagement/Application/Services/ClinicAdministrationService.php' => 'Large service kept under review while clinic workflows are extracted into dedicated use-case services.',
                'app/Modules/TenantManagement/Infrastructure/Persistence/DatabaseClinicRepository.php' => 'Repository still carries multiple nested aggregates and is tracked for mapper extraction.',
                'app/Modules/Provider/Application/Services/ProviderScheduleService.php' => 'Provider schedule orchestration spans multiple documented schedule surfaces and remains under review.',
                'app/Modules/Scheduling/Application/Services/AppointmentWorkflowService.php' => 'Appointment transition orchestration is tracked for decomposition after hardening baselines are locked.',
                'app/Modules/Scheduling/Application/Services/AvailabilitySlotService.php' => 'Slot generation remains centralized pending a follow-up extraction into interval and constraint collaborators.',
                'app/Modules/Scheduling/Application/Services/ProviderCalendarService.php' => 'Calendar projection logic remains centralized while baseline behavior is frozen for release readiness.',
                'app/Modules/Lab/Application/Services/LabOrderWorkflowService.php' => 'Lab workflow orchestration still combines provider callbacks and domain transitions and is tracked for later extraction.',
                'app/Modules/TenantManagement/Application/Services/TenantAdministrationService.php' => 'Tenant lifecycle orchestration is reviewed but retained until release stabilization completes.',
                'app/Shared/Infrastructure/Observability/CacheBackedObservabilityMetricRecorder.php' => 'Metric fan-out stays centralized for hardening and observability verification.',
                'app/Modules/Lab/Infrastructure/Persistence/DatabaseLabOrderRepository.php' => 'Lab repository remains large because order hydration and result projections are still co-located.',
                'app/Modules/Scheduling/Infrastructure/Persistence/DatabaseAppointmentRepository.php' => 'Appointment repository keeps read/write projection mapping in one place pending post-release extraction.',
                'app/Modules/Integrations/Application/Services/UzumWebhookMutationService.php' => 'Uzum callback mutation handling remains centralized until gateway hardening stabilizes.',
                'app/Modules/Billing/Infrastructure/Persistence/DatabasePriceListRepository.php' => 'Price-list replacement persistence remains temporarily co-located while billing baselines are frozen.',
            ],
        ],
    ],
    'performance' => [
        'baseline_thresholds_ms' => [
            'public_ping_average' => 75.0,
            'authenticated_metrics_average' => 350.0,
            'internal_metrics_average' => 200.0,
        ],
        'iterations' => 8,
    ],
    'security' => [
        'allowed_tracked_env_files' => [
            '.env.example',
            '.env.testing',
        ],
    ],
];
