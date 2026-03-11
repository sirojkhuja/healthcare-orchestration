<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/NotificationTestSupport.php';

uses(RefreshDatabase::class);

it('manages versioned notification templates and renders test payloads', function (): void {
    $admin = User::factory()->create([
        'email' => 'notifications.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'notifications.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = notificationIssueBearerToken($this, 'notifications.admin@openai.com');
    $viewerToken = notificationIssueBearerToken($this, 'notifications.viewer@openai.com');
    $tenantId = notificationCreateTenant($this, $adminToken, 'Notification Tenant')->json('data.id');

    notificationGrantPermissions($admin, $tenantId, ['notifications.view', 'notifications.manage']);
    notificationGrantPermissions($viewer, $tenantId, ['notifications.view']);

    $templateId = notificationCreateTemplate($this, $adminToken, $tenantId, [
        'code' => 'appointment-reminder',
        'name' => 'Appointment reminder',
        'channel' => 'email',
        'description' => 'Reminder before the visit.',
        'subject_template' => 'Reminder for {{patient.first_name}}',
        'body_template' => <<<'TEXT'
Hello {{patient.first_name}},

Your visit at {{clinic.name}} starts at {{appointment.start_at}}.
TEXT,
    ], 'notifications-template-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'notification_template_created')
        ->assertJsonPath('data.code', 'APPOINTMENT-REMINDER')
        ->assertJsonPath('data.current_version', 1)
        ->assertJsonPath('data.placeholders', [
            'appointment.start_at',
            'clinic.name',
            'patient.first_name',
        ])
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/templates?q=reminder&channel=email&is_active=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.filters.q', 'reminder')
        ->assertJsonPath('data.0.id', $templateId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/templates/'.$templateId)
        ->assertOk()
        ->assertJsonPath('data.id', $templateId)
        ->assertJsonPath('data.versions.0.version', 1);

    notificationTestRenderTemplate($this, $adminToken, $tenantId, $templateId, [
        'variables' => [
            'patient' => [
                'first_name' => 'Amina',
            ],
            'clinic' => [
                'name' => 'Downtown Clinic',
            ],
            'appointment' => [
                'start_at' => '2026-03-12 09:00',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('status', 'notification_template_rendered')
        ->assertJsonPath('data.template_id', $templateId)
        ->assertJsonPath('data.rendered_subject', 'Reminder for Amina')
        ->assertJsonPath('data.rendered_body', "Hello Amina,\n\nYour visit at Downtown Clinic starts at 2026-03-12 09:00.");

    notificationUpdateTemplate($this, $adminToken, $tenantId, $templateId, [
        'body_template' => <<<'TEXT'
Hello {{patient.first_name}},

Your visit at {{clinic.name}} with {{provider.name}} starts at {{appointment.start_at}}.
TEXT,
        'is_active' => false,
    ], 'notifications-template-update-1')
        ->assertOk()
        ->assertJsonPath('status', 'notification_template_updated')
        ->assertJsonPath('data.current_version', 2)
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.placeholders', [
            'appointment.start_at',
            'clinic.name',
            'patient.first_name',
            'provider.name',
        ]);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/templates/'.$templateId)
        ->assertOk()
        ->assertJsonPath('data.current_version', 2)
        ->assertJsonCount(2, 'data.versions')
        ->assertJsonPath('data.versions.0.version', 2)
        ->assertJsonPath('data.versions.1.version', 1);

    notificationDeleteTemplate($this, $adminToken, $tenantId, $templateId, 'notifications-template-delete-1')
        ->assertOk()
        ->assertJsonPath('status', 'notification_template_deleted')
        ->assertJsonPath('data.id', $templateId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/templates/'.$templateId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    notificationCreateTemplate($this, $adminToken, $tenantId, [
        'code' => 'APPOINTMENT-REMINDER',
        'name' => 'Appointment reminder SMS',
        'channel' => 'sms',
        'subject_template' => 'ignored',
        'body_template' => 'Reminder for {{patient.first_name}}',
    ], 'notifications-template-create-2')
        ->assertCreated()
        ->assertJsonPath('data.code', 'APPOINTMENT-REMINDER')
        ->assertJsonPath('data.channel', 'sms')
        ->assertJsonPath('data.subject_template', null);

    expect(AuditEventRecord::query()->where('action', 'notification_templates.created')->where('object_id', $templateId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'notification_templates.updated')->where('object_id', $templateId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'notification_templates.deleted')->where('object_id', $templateId)->exists())->toBeTrue();
});

it('enforces notification permissions and render validation rules', function (): void {
    $manager = User::factory()->create([
        'email' => 'notifications.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'notifications.viewer.only@openai.com',
        'password' => 'secret-password',
    ]);
    $manageOnly = User::factory()->create([
        'email' => 'notifications.manage.only@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = notificationIssueBearerToken($this, 'notifications.manager@openai.com');
    $viewerToken = notificationIssueBearerToken($this, 'notifications.viewer.only@openai.com');
    $manageOnlyToken = notificationIssueBearerToken($this, 'notifications.manage.only@openai.com');
    $tenantId = notificationCreateTenant($this, $managerToken, 'Notification Validation Tenant')->json('data.id');

    notificationGrantPermissions($manager, $tenantId, ['notifications.view', 'notifications.manage']);
    notificationGrantPermissions($viewer, $tenantId, ['notifications.view']);
    notificationGrantPermissions($manageOnly, $tenantId, ['notifications.manage']);

    notificationCreateTemplate($this, $viewerToken, $tenantId, [
        'code' => 'viewer-template',
        'name' => 'Viewer template',
        'channel' => 'email',
        'subject_template' => 'Hello {{patient.first_name}}',
        'body_template' => 'Body',
    ], 'notifications-viewer-create')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($manageOnlyToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/templates')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    notificationCreateTemplate($this, $managerToken, $tenantId, [
        'code' => 'missing-subject',
        'name' => 'Missing subject',
        'channel' => 'email',
        'body_template' => 'Hello {{patient.first_name}}',
    ], 'notifications-missing-subject')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $templateId = notificationCreateTemplate($this, $managerToken, $tenantId, [
        'code' => 'payment-reminder',
        'name' => 'Payment reminder',
        'channel' => 'telegram',
        'body_template' => 'Hello {{patient.first_name}}, invoice {{invoice.number}} is due.',
    ], 'notifications-template-valid')
        ->assertCreated()
        ->json('data.id');

    notificationCreateTemplate($this, $managerToken, $tenantId, [
        'code' => 'PAYMENT-REMINDER',
        'name' => 'Duplicate payment reminder',
        'channel' => 'telegram',
        'body_template' => 'Duplicate',
    ], 'notifications-template-duplicate')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    notificationTestRenderTemplate($this, $managerToken, $tenantId, $templateId, [
        'variables' => [
            'patient' => [
                'first_name' => 'Amina',
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    notificationTestRenderTemplate($this, $managerToken, $tenantId, $templateId, [
        'variables' => [
            'patient' => [
                'first_name' => ['not', 'scalar'],
            ],
            'invoice' => [
                'number' => 'INV-000001',
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');
});
