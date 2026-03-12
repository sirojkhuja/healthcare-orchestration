<?php

return [
    'groups' => [
        'rbac' => [
            'name' => 'RBAC',
            'description' => 'Role, permission, and access-control administration.',
            'permissions' => [
                'rbac.view' => 'View roles, permission groups, permission catalogs, and RBAC audit history.',
                'rbac.manage' => 'Create, update, delete, and assign RBAC roles and permissions.',
            ],
        ],
        'users' => [
            'name' => 'Users',
            'description' => 'User lifecycle and tenant staff administration.',
            'permissions' => [
                'users.view' => 'View users and user summaries.',
                'users.manage' => 'Create, update, and transition user lifecycle state.',
            ],
        ],
        'profiles' => [
            'name' => 'Profiles',
            'description' => 'User profile and avatar management.',
            'permissions' => [
                'profiles.view' => 'View profiles.',
                'profiles.manage' => 'Update profiles and avatars.',
            ],
        ],
        'security' => [
            'name' => 'Security',
            'description' => 'Security events, sessions, API keys, and allowlists.',
            'permissions' => [
                'security.view' => 'View security data and security events.',
                'security.manage' => 'Manage sessions, API keys, devices, and allowlists.',
            ],
        ],
        'tenants' => [
            'name' => 'Tenants',
            'description' => 'Tenant, clinic, and organizational administration.',
            'permissions' => [
                'tenants.view' => 'View tenants, clinics, and organization settings.',
                'tenants.manage' => 'Manage tenants, clinics, departments, rooms, and settings.',
            ],
        ],
        'patients' => [
            'name' => 'Patients',
            'description' => 'Patient directories, records, and related artifacts.',
            'permissions' => [
                'patients.view' => 'View patients, summaries, timelines, contacts, consents, insurance links, documents, tags, and external references.',
                'patients.manage' => 'Create and update patients, contacts, consents, insurance links, documents, tags, and external references.',
            ],
        ],
        'providers' => [
            'name' => 'Providers',
            'description' => 'Provider profiles, specialties, availability, and calendars.',
            'permissions' => [
                'providers.view' => 'View providers and provider availability.',
                'providers.manage' => 'Manage providers, specialties, and calendars.',
            ],
        ],
        'appointments' => [
            'name' => 'Appointments',
            'description' => 'Scheduling, waitlists, recurrence, and reminders.',
            'permissions' => [
                'appointments.view' => 'View appointments and schedules.',
                'appointments.manage' => 'Create, update, and transition appointments.',
            ],
        ],
        'clinical' => [
            'name' => 'Clinical',
            'description' => 'Treatment plans, encounters, diagnoses, labs, and prescriptions.',
            'permissions' => [
                'treatments.view' => 'View treatment plans and encounters.',
                'treatments.manage' => 'Manage treatment plans, encounters, and procedures.',
                'labs.view' => 'View lab orders and lab results.',
                'labs.manage' => 'Manage lab orders, catalogs, and result intake.',
                'prescriptions.view' => 'View prescriptions, medication catalogs, allergies, and patient medication records.',
                'prescriptions.manage' => 'Manage prescriptions, medication catalogs, and allergy records.',
            ],
        ],
        'finance' => [
            'name' => 'Finance',
            'description' => 'Billing, payments, and insurance claims.',
            'permissions' => [
                'billing.view' => 'View invoices, payments, and billing catalogs.',
                'billing.manage' => 'Manage invoices, payments, and billing catalogs.',
                'claims.view' => 'View insurance claims and payer data.',
                'claims.manage' => 'Manage insurance claims, payers, and rules.',
            ],
        ],
        'notifications' => [
            'name' => 'Notifications',
            'description' => 'Templates, delivery, and communication channels.',
            'permissions' => [
                'notifications.view' => 'View templates, notifications, and delivery history.',
                'notifications.manage' => 'Manage templates, sends, retries, and channels.',
            ],
        ],
        'integrations' => [
            'name' => 'Integrations',
            'description' => 'External providers, credentials, and health checks.',
            'permissions' => [
                'integrations.view' => 'View integrations, credentials, and health.',
                'integrations.manage' => 'Manage integration credentials, tokens, and webhooks.',
            ],
        ],
        'ops' => [
            'name' => 'Operations',
            'description' => 'Reporting, observability, and operational controls.',
            'permissions' => [
                'reports.view' => 'View reports and operational summaries.',
                'reports.manage' => 'Generate reports and operate admin tooling.',
                'admin.view' => 'View health, metrics, and operational state.',
                'admin.manage' => 'Operate admin actions, retries, and cache controls.',
            ],
        ],
        'compliance' => [
            'name' => 'Compliance',
            'description' => 'Audit history, retention policies, and PII governance.',
            'permissions' => [
                'audit.view' => 'View tenant-scoped audit events, object history, and retention settings.',
                'audit.manage' => 'Export audit events and manage tenant audit retention policies.',
                'compliance.view' => 'View tenant PII field registry entries and compliance reports.',
                'compliance.manage' => 'Manage tenant PII field registry entries and compliance operations.',
            ],
        ],
    ],
];
