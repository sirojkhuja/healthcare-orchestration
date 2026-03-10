<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/SchedulingTestSupport.php';

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('manages appointment participants and notes with tenant scoped permissions', function (): void {
    $admin = User::factory()->create([
        'email' => 'appointments.collab.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'appointments.collab.viewer@openai.com',
        'password' => 'secret-password',
        'name' => 'Viewer User',
    ]);
    $blocked = User::factory()->create([
        'email' => 'appointments.collab.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = schedulingIssueBearerToken($this, 'appointments.collab.admin@openai.com');
    $viewerToken = schedulingIssueBearerToken($this, 'appointments.collab.viewer@openai.com');
    $blockedToken = schedulingIssueBearerToken($this, 'appointments.collab.blocked@openai.com');
    $tenantId = schedulingCreateTenant($this, $adminToken, 'Appointment Collaboration Tenant')->json('data.id');

    patientGrantPermissions($admin, $tenantId, [
        'tenants.manage',
        'appointments.view',
        'appointments.manage',
        'patients.manage',
        'providers.manage',
    ]);
    patientGrantPermissions($viewer, $tenantId, ['appointments.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = schedulingCreatePatient($this, $adminToken, $tenantId)->json('data.id');
    $providerId = schedulingCreateProvider($this, $adminToken, $tenantId)->json('data.id');
    $appointmentId = schedulingCreateAppointment(
        $this,
        $adminToken,
        $tenantId,
        [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'scheduled_start_at' => '2026-03-17T09:00:00+05:00',
            'scheduled_end_at' => '2026-03-17T09:30:00+05:00',
            'timezone' => 'Asia/Tashkent',
        ],
        'appointments-collab-create',
    )->assertCreated()->json('data.id');

    $userParticipantId = $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-participant-user',
        ])
        ->postJson('/api/v1/appointments/'.$appointmentId.'/participants', [
            'participant_type' => 'user',
            'reference_id' => (string) $viewer->getAuthIdentifier(),
            'role' => 'observer',
            'required' => true,
            'notes' => 'Needs read access during intake.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'appointment_participant_created')
        ->assertJsonPath('data.participant_type', 'user')
        ->assertJsonPath('data.display_name', 'Viewer User')
        ->assertJsonPath('data.required', true)
        ->json('data.id');

    $externalParticipantId = $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-participant-external',
        ])
        ->postJson('/api/v1/appointments/'.$appointmentId.'/participants', [
            'participant_type' => 'external',
            'display_name' => 'Interpreter A',
            'role' => 'interpreter',
        ])
        ->assertCreated()
        ->assertJsonPath('data.participant_type', 'external')
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId.'/participants')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $userParticipantId)
        ->assertJsonPath('data.1.id', $externalParticipantId);

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-participant-duplicate',
        ])
        ->postJson('/api/v1/appointments/'.$appointmentId.'/participants', [
            'participant_type' => 'external',
            'display_name' => 'Interpreter A',
            'role' => 'interpreter',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-participant-viewer-denied',
        ])
        ->postJson('/api/v1/appointments/'.$appointmentId.'/participants', [
            'participant_type' => 'external',
            'display_name' => 'Family Member',
            'role' => 'guest',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $noteId = $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-note-create',
        ])
        ->postJson('/api/v1/appointments/'.$appointmentId.'/notes', [
            'body' => '  Initial intake note.  ',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'appointment_note_created')
        ->assertJsonPath('data.author.user_id', (string) $admin->getAuthIdentifier())
        ->assertJsonPath('data.author.email', 'appointments.collab.admin@openai.com')
        ->assertJsonPath('data.body', 'Initial intake note.')
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId.'/notes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $noteId);

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId.'/notes')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-note-viewer-denied',
        ])
        ->patchJson('/api/v1/appointments/'.$appointmentId.'/notes/'.$noteId, [
            'body' => 'Viewer should not update this.',
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-note-update',
        ])
        ->patchJson('/api/v1/appointments/'.$appointmentId.'/notes/'.$noteId, [
            'body' => 'Updated intake note.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'appointment_note_updated')
        ->assertJsonPath('data.id', $noteId)
        ->assertJsonPath('data.body', 'Updated intake note.')
        ->assertJsonPath('data.author.user_id', (string) $admin->getAuthIdentifier());

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-note-delete',
        ])
        ->deleteJson('/api/v1/appointments/'.$appointmentId.'/notes/'.$noteId)
        ->assertOk()
        ->assertJsonPath('status', 'appointment_note_deleted')
        ->assertJsonPath('data.id', $noteId);

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-participant-delete',
        ])
        ->deleteJson('/api/v1/appointments/'.$appointmentId.'/participants/'.$externalParticipantId)
        ->assertOk()
        ->assertJsonPath('status', 'appointment_participant_deleted')
        ->assertJsonPath('data.id', $externalParticipantId);

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'appointments-collab-delete',
        ])
        ->deleteJson('/api/v1/appointments/'.$appointmentId)
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId.'/participants')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/appointments/'.$appointmentId.'/notes')
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});
