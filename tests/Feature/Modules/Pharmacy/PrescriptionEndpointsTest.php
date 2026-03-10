<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/PharmacyTestSupport.php';

uses(RefreshDatabase::class);

it('manages prescription crud search export and lifecycle transitions', function (): void {
    $admin = User::factory()->create([
        'email' => 'prescriptions.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'prescriptions.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = pharmacyIssueBearerToken($this, 'prescriptions.admin@openai.com');
    $viewerToken = pharmacyIssueBearerToken($this, 'prescriptions.viewer@openai.com');
    $tenantId = pharmacyCreateTenant($this, $adminToken, 'Prescription Tenant')->json('data.id');
    pharmacyGrantPermissions($admin, $tenantId, [
        'prescriptions.view',
        'prescriptions.manage',
        'patients.manage',
        'providers.manage',
        'treatments.view',
        'treatments.manage',
    ]);
    pharmacyGrantPermissions($viewer, $tenantId, ['prescriptions.view']);

    $patientId = pharmacyCreatePatient($this, $adminToken, $tenantId)->json('data.id');
    $providerId = pharmacyCreateProvider($this, $adminToken, $tenantId)->json('data.id');
    $planId = pharmacyCreatePlan($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'title' => 'Medication plan',
    ])->json('data.id');
    $itemId = pharmacyCreateTreatmentItem($this, $adminToken, $tenantId, $planId, [
        'item_type' => 'medication',
        'title' => 'Amoxicillin course',
    ])->assertCreated()->json('data.id');
    $encounterId = pharmacyCreateEncounter($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'treatment_plan_id' => $planId,
    ])->assertCreated()->json('data.id');

    $prescriptionId = pharmacyCreatePrescription($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'encounter_id' => $encounterId,
        'treatment_item_id' => $itemId,
        'medication_name' => 'Amoxicillin 500mg',
        'medication_code' => 'AMOX-500',
        'dosage' => '500 mg',
        'route' => 'oral',
        'frequency' => 'twice_daily',
        'quantity' => '14',
        'quantity_unit' => 'capsule',
        'authorized_refills' => 1,
        'instructions' => 'Take after meals.',
        'notes' => 'Initial script',
        'starts_on' => '2026-03-10',
        'ends_on' => '2026-03-17',
    ], 'prescription-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'prescription_created')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.medication.name', 'Amoxicillin 500mg')
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/prescriptions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $prescriptionId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/prescriptions/search?q=amoxicillin&status=draft')
        ->assertOk()
        ->assertJsonPath('meta.filters.q', 'amoxicillin')
        ->assertJsonPath('meta.filters.status', 'draft')
        ->assertJsonPath('data.0.id', $prescriptionId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/prescriptions/export?format=csv')
        ->assertOk()
        ->assertJsonPath('status', 'prescription_export_created')
        ->assertJsonPath('data.row_count', 1)
        ->assertJsonPath('data.format', 'csv');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-update-1',
        ])
        ->patchJson('/api/v1/prescriptions/'.$prescriptionId, [
            'notes' => 'Updated note',
            'authorized_refills' => 2,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'prescription_updated')
        ->assertJsonPath('data.notes', 'Updated note')
        ->assertJsonPath('data.authorized_refills', 2);

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-issue-1',
        ])
        ->postJson('/api/v1/prescriptions/'.$prescriptionId.':issue')
        ->assertOk()
        ->assertJsonPath('status', 'prescription_issued')
        ->assertJsonPath('data.status', 'issued');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-update-after-issue',
        ])
        ->patchJson('/api/v1/prescriptions/'.$prescriptionId, [
            'notes' => 'Should fail',
        ])
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-dispense-1',
        ])
        ->postJson('/api/v1/prescriptions/'.$prescriptionId.':dispense')
        ->assertOk()
        ->assertJsonPath('status', 'prescription_dispensed')
        ->assertJsonPath('data.status', 'dispensed');

    $canceledPrescriptionId = pharmacyCreatePrescription($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'medication_name' => 'Ibuprofen 200mg',
        'dosage' => '200 mg',
        'route' => 'oral',
        'frequency' => 'three_times_daily',
        'quantity' => '21',
        'authorized_refills' => 0,
    ], 'prescription-create-2')
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-cancel-1',
        ])
        ->postJson('/api/v1/prescriptions/'.$canceledPrescriptionId.':cancel', [
            'reason' => 'Patient reported allergy history.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'prescription_canceled')
        ->assertJsonPath('data.status', 'canceled');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-delete-1',
        ])
        ->deleteJson('/api/v1/prescriptions/'.$canceledPrescriptionId)
        ->assertOk()
        ->assertJsonPath('status', 'prescription_deleted')
        ->assertJsonPath('data.deleted_at', fn (string $deletedAt): bool => $deletedAt !== '');

    expect(AuditEventRecord::query()->where('action', 'prescriptions.created')->where('object_id', $prescriptionId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'prescriptions.exported')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'prescriptions.issued')->where('object_id', $prescriptionId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'prescriptions.dispensed')->where('object_id', $prescriptionId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'prescriptions.canceled')->where('object_id', $canceledPrescriptionId)->exists())->toBeTrue();
});

it('enforces prescription permissions and medication treatment-item validation', function (): void {
    $manager = User::factory()->create([
        'email' => 'prescriptions.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'prescriptions.viewer.only@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = pharmacyIssueBearerToken($this, 'prescriptions.manager@openai.com');
    $viewerToken = pharmacyIssueBearerToken($this, 'prescriptions.viewer.only@openai.com');
    $tenantId = pharmacyCreateTenant($this, $managerToken, 'Prescription Validation Tenant')->json('data.id');
    pharmacyGrantPermissions($manager, $tenantId, [
        'prescriptions.view',
        'prescriptions.manage',
        'patients.manage',
        'providers.manage',
        'treatments.view',
        'treatments.manage',
    ]);
    pharmacyGrantPermissions($viewer, $tenantId, ['prescriptions.view']);

    $patientId = pharmacyCreatePatient($this, $managerToken, $tenantId)->json('data.id');
    $providerId = pharmacyCreateProvider($this, $managerToken, $tenantId)->json('data.id');
    $planId = pharmacyCreatePlan($this, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
    ])->json('data.id');
    $labItemId = pharmacyCreateTreatmentItem($this, $managerToken, $tenantId, $planId, [
        'item_type' => 'lab',
        'title' => 'Lab-only item',
    ])->assertCreated()->json('data.id');
    $encounterId = pharmacyCreateEncounter($this, $managerToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'treatment_plan_id' => $planId,
    ])->assertCreated()->json('data.id');

    $this->withToken($managerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-invalid-item',
        ])
        ->postJson('/api/v1/prescriptions', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'encounter_id' => $encounterId,
            'treatment_item_id' => $labItemId,
            'medication_name' => 'Cetirizine',
            'dosage' => '10 mg',
            'route' => 'oral',
            'frequency' => 'once_daily',
            'quantity' => '10',
            'authorized_refills' => 0,
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $this->withToken($viewerToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-viewer-create',
        ])
        ->postJson('/api/v1/prescriptions', [
            'patient_id' => $patientId,
            'provider_id' => $providerId,
            'medication_name' => 'Cetirizine',
            'dosage' => '10 mg',
            'route' => 'oral',
            'frequency' => 'once_daily',
            'quantity' => '10',
            'authorized_refills' => 0,
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});
