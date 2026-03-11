<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/SchedulingTestSupport.php';
require_once __DIR__.'/../Notifications/NotificationTestSupport.php';

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function appointmentNotificationFixture(
    $testCase,
    string $email,
    string $tenantName,
    bool $requireConfirmation = true,
): array {
    $admin = User::factory()->create([
        'email' => $email,
        'password' => 'secret-password',
    ]);
    $token = schedulingIssueBearerToken($testCase, $email);
    $tenantId = schedulingCreateTenant($testCase, $token, $tenantName)->json('data.id');

    patientGrantPermissions($admin, $tenantId, [
        'tenants.manage',
        'appointments.view',
        'appointments.manage',
        'patients.manage',
        'providers.manage',
        'notifications.manage',
        'notifications.view',
    ]);

    $clinicId = providerCreateClinic($testCase, $token, $tenantId, 'appointments-notify', 'Appointments Notify')->json('data.id');
    schedulingUpdateClinicSettings($testCase, $token, $tenantId, $clinicId, [
        'timezone' => 'Asia/Tashkent',
        'default_appointment_duration_minutes' => 30,
        'slot_interval_minutes' => 15,
        'allow_walk_ins' => true,
        'require_appointment_confirmation' => $requireConfirmation,
        'telemedicine_enabled' => false,
    ]);
    schedulingUpdateClinicWorkHours($testCase, $token, $tenantId, $clinicId, [
        'thursday' => [
            ['start_time' => '06:00', 'end_time' => '18:00'],
        ],
        'friday' => [
            ['start_time' => '06:00', 'end_time' => '18:00'],
        ],
    ]);

    $providerId = schedulingCreateProvider($testCase, $token, $tenantId, [
        'clinic_id' => $clinicId,
    ])->json('data.id');
    schedulingCreateRule($testCase, $token, $tenantId, $providerId, [
        'scope_type' => 'weekly',
        'availability_type' => 'available',
        'weekday' => 'thursday',
        'start_time' => '06:00',
        'end_time' => '18:00',
    ], 'appointment-notify-thursday')->assertCreated();
    schedulingCreateRule($testCase, $token, $tenantId, $providerId, [
        'scope_type' => 'weekly',
        'availability_type' => 'available',
        'weekday' => 'friday',
        'start_time' => '06:00',
        'end_time' => '18:00',
    ], 'appointment-notify-friday')->assertCreated();

    return [
        'email' => $email,
        'token' => $token,
        'tenant_id' => $tenantId,
        'clinic_id' => $clinicId,
        'provider_id' => $providerId,
    ];
}

function createAppointmentNotificationTemplates($testCase, string $token, string $tenantId): void
{
    notificationCreateTemplate($testCase, $token, $tenantId, [
        'code' => 'appointment-reminder-sms',
        'name' => 'Appointment reminder SMS',
        'channel' => 'sms',
        'body_template' => 'Reminder for {{patient.first_name}} at {{appointment.start_at}}.',
    ], 'appointment-reminder-sms-template')->assertCreated();

    notificationCreateTemplate($testCase, $token, $tenantId, [
        'code' => 'appointment-reminder-email',
        'name' => 'Appointment reminder email',
        'channel' => 'email',
        'subject_template' => 'Reminder for {{patient.first_name}}',
        'body_template' => 'Visit with {{provider.name}} at {{appointment.start_at}}.',
    ], 'appointment-reminder-email-template')->assertCreated();

    notificationCreateTemplate($testCase, $token, $tenantId, [
        'code' => 'appointment-confirmation-sms',
        'name' => 'Appointment confirmation SMS',
        'channel' => 'sms',
        'body_template' => 'Please confirm appointment {{appointment.id}}.',
    ], 'appointment-confirmation-sms-template')->assertCreated();

    notificationCreateTemplate($testCase, $token, $tenantId, [
        'code' => 'appointment-confirmation-email',
        'name' => 'Appointment confirmation email',
        'channel' => 'email',
        'subject_template' => 'Confirm {{appointment.id}}',
        'body_template' => 'Please confirm your visit at {{clinic.name}}.',
    ], 'appointment-confirmation-email-template')->assertCreated();
}

it('dispatches appointment reminders by window with idempotent notification linkage', function (): void {
    $fixture = appointmentNotificationFixture($this, 'appointments.notify.reminder@openai.com', 'Appointment Reminder Tenant');
    createAppointmentNotificationTemplates($this, $fixture['token'], $fixture['tenant_id']);

    $patientId = schedulingCreatePatient($this, $fixture['token'], $fixture['tenant_id'], [
        'first_name' => 'Amina',
        'last_name' => 'Rasulova',
        'sex' => 'female',
        'birth_date' => '1993-05-02',
        'email' => null,
        'phone' => null,
    ])->json('data.id');

    $this->withToken($fixture['token'])
        ->withHeader('X-Tenant-Id', $fixture['tenant_id'])
        ->postJson('/api/v1/patients/'.$patientId.'/contacts', [
            'name' => 'Amina Rasulova',
            'phone' => '+998901112233',
            'email' => 'amina@openai.com',
            'is_primary' => true,
        ])
        ->assertCreated();

    $advanceAppointmentId = schedulingCreateAppointment($this, $fixture['token'], $fixture['tenant_id'], [
        'patient_id' => $patientId,
        'provider_id' => $fixture['provider_id'],
        'clinic_id' => $fixture['clinic_id'],
        'scheduled_start_at' => '2026-03-19T10:00:00+05:00',
        'scheduled_end_at' => '2026-03-19T10:30:00+05:00',
        'timezone' => 'Asia/Tashkent',
    ], 'appointment-reminder-create')->json('data.id');

    $sameDayAppointmentId = schedulingCreateAppointment($this, $fixture['token'], $fixture['tenant_id'], [
        'patient_id' => $patientId,
        'provider_id' => $fixture['provider_id'],
        'clinic_id' => $fixture['clinic_id'],
        'scheduled_start_at' => '2026-03-12T10:00:00+05:00',
        'scheduled_end_at' => '2026-03-12T10:30:00+05:00',
        'timezone' => 'Asia/Tashkent',
    ], 'appointment-reminder-create-same-day')->json('data.id');

    schedulingAppointmentAction($this, $fixture['token'], $fixture['tenant_id'], $advanceAppointmentId, 'schedule', 'appointment-reminder-schedule')
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');
    schedulingAppointmentAction($this, $fixture['token'], $fixture['tenant_id'], $sameDayAppointmentId, 'schedule', 'appointment-reminder-schedule-same-day')
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled');

    $advance = schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $advanceAppointmentId,
        'send-reminder',
        'appointment-reminder-send-advance-1',
    )
        ->assertOk()
        ->assertJsonPath('status', 'appointment_reminder_sent')
        ->assertJsonPath('data.notification_type', 'reminder')
        ->assertJsonPath('data.window_key', 'advance')
        ->assertJsonCount(2, 'data.notifications');

    $advanceIds = array_column($advance->json('data.notifications'), 'id');
    expect($advance->json('data.notifications.0.template.channel'))->toBe('sms');
    expect($advance->json('data.notifications.1.template.channel'))->toBe('email');

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $advanceAppointmentId,
        'send-reminder',
        'appointment-reminder-send-advance-2',
    )
        ->assertOk()
        ->assertJsonPath('data.window_key', 'advance')
        ->assertJsonPath('data.notifications.0.id', $advanceIds[0])
        ->assertJsonPath('data.notifications.1.id', $advanceIds[1]);

    expect(DB::table('appointment_notifications')
        ->where('appointment_id', $advanceAppointmentId)
        ->where('notification_type', 'reminder')
        ->where('window_key', 'advance')
        ->count())->toBe(2);

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $sameDayAppointmentId,
        'send-reminder',
        'appointment-reminder-send-same-day',
    )
        ->assertOk()
        ->assertJsonPath('data.window_key', 'same_day')
        ->assertJsonCount(2, 'data.notifications');

    expect(DB::table('appointment_notifications')
        ->whereIn('appointment_id', [$advanceAppointmentId, $sameDayAppointmentId])
        ->where('notification_type', 'reminder')
        ->count())->toBe(4);
    expect(AuditEventRecord::query()->where('action', 'appointments.reminder_sent')->where('object_id', $advanceAppointmentId)->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'appointments.reminder_sent')->where('object_id', $sameDayAppointmentId)->count())->toBe(1);
});

it('dispatches appointment confirmations once and reuses the existing links across requests', function (): void {
    $fixture = appointmentNotificationFixture($this, 'appointments.notify.confirmation@openai.com', 'Appointment Confirmation Tenant');
    createAppointmentNotificationTemplates($this, $fixture['token'], $fixture['tenant_id']);

    $patientId = schedulingCreatePatient($this, $fixture['token'], $fixture['tenant_id'], [
        'first_name' => 'Bekzod',
        'last_name' => 'Qodirov',
        'sex' => 'male',
        'birth_date' => '1988-09-09',
        'email' => 'bekzod@openai.com',
        'phone' => '+998909998877',
    ])->json('data.id');

    $appointmentId = schedulingCreateAppointment($this, $fixture['token'], $fixture['tenant_id'], [
        'patient_id' => $patientId,
        'provider_id' => $fixture['provider_id'],
        'clinic_id' => $fixture['clinic_id'],
        'scheduled_start_at' => '2026-03-20T11:00:00+05:00',
        'scheduled_end_at' => '2026-03-20T11:30:00+05:00',
        'timezone' => 'Asia/Tashkent',
    ], 'appointment-confirmation-create')->json('data.id');

    schedulingAppointmentAction($this, $fixture['token'], $fixture['tenant_id'], $appointmentId, 'schedule', 'appointment-confirmation-schedule')
        ->assertOk();

    $first = schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'send-confirmation',
        'appointment-confirmation-send-1',
    )
        ->assertOk()
        ->assertJsonPath('status', 'appointment_confirmation_sent')
        ->assertJsonPath('data.notification_type', 'confirmation')
        ->assertJsonPath('data.window_key', null)
        ->assertJsonCount(2, 'data.notifications');

    $confirmationIds = array_column($first->json('data.notifications'), 'id');

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'send-confirmation',
        'appointment-confirmation-send-2',
    )
        ->assertOk()
        ->assertJsonPath('data.notifications.0.id', $confirmationIds[0])
        ->assertJsonPath('data.notifications.1.id', $confirmationIds[1]);

    expect(DB::table('appointment_notifications')
        ->where('appointment_id', $appointmentId)
        ->where('notification_type', 'confirmation')
        ->count())->toBe(2);
    expect(AuditEventRecord::query()->where('action', 'appointments.confirmation_sent')->where('object_id', $appointmentId)->count())->toBe(2);
});

it('enforces appointment confirmation settings and recipient availability for appointment-linked notifications', function (): void {
    $fixture = appointmentNotificationFixture(
        $this,
        'appointments.notify.guards@openai.com',
        'Appointment Notification Guard Tenant',
        false,
    );
    createAppointmentNotificationTemplates($this, $fixture['token'], $fixture['tenant_id']);

    $patientId = schedulingCreatePatient($this, $fixture['token'], $fixture['tenant_id'], [
        'first_name' => 'Laylo',
        'last_name' => 'Usmanova',
        'sex' => 'female',
        'birth_date' => '1992-02-02',
        'email' => null,
        'phone' => null,
    ])->json('data.id');

    $appointmentId = schedulingCreateAppointment($this, $fixture['token'], $fixture['tenant_id'], [
        'patient_id' => $patientId,
        'provider_id' => $fixture['provider_id'],
        'clinic_id' => $fixture['clinic_id'],
        'scheduled_start_at' => '2026-03-20T09:00:00+05:00',
        'scheduled_end_at' => '2026-03-20T09:30:00+05:00',
        'timezone' => 'Asia/Tashkent',
    ], 'appointment-guards-create')->json('data.id');

    schedulingAppointmentAction($this, $fixture['token'], $fixture['tenant_id'], $appointmentId, 'schedule', 'appointment-guards-schedule')
        ->assertOk();

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'send-confirmation',
        'appointment-guards-confirmation',
    )
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    schedulingAppointmentAction(
        $this,
        $fixture['token'],
        $fixture['tenant_id'],
        $appointmentId,
        'send-reminder',
        'appointment-guards-reminder',
    )
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');
});
