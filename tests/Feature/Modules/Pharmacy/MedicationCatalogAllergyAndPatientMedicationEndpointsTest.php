<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/PharmacyTestSupport.php';

uses(RefreshDatabase::class);

it('manages medication catalogs allergies and patient medication views', function (): void {
    $admin = User::factory()->create([
        'email' => 'pharmacy.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'pharmacy.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = pharmacyIssueBearerToken($this, 'pharmacy.admin@openai.com');
    $viewerToken = pharmacyIssueBearerToken($this, 'pharmacy.viewer@openai.com');
    $tenantId = pharmacyCreateTenant($this, $adminToken, 'Pharmacy Tenant')->json('data.id');

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
        'title' => 'Medication tracking plan',
    ])->json('data.id');
    $itemId = pharmacyCreateTreatmentItem($this, $adminToken, $tenantId, $planId, [
        'item_type' => 'medication',
        'title' => 'Tracked medication item',
    ])->assertCreated()->json('data.id');
    $encounterId = pharmacyCreateEncounter($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'treatment_plan_id' => $planId,
    ])->assertCreated()->json('data.id');

    $amoxId = pharmacyCreateMedication($this, $adminToken, $tenantId, [
        'code' => 'amox-500',
        'name' => 'Amoxicillin 500 mg',
        'generic_name' => 'Amoxicillin',
        'form' => 'capsule',
        'strength' => '500 mg',
    ], 'medication-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'medication_created')
        ->assertJsonPath('data.code', 'AMOX-500')
        ->json('data.id');

    $ibuId = pharmacyCreateMedication($this, $adminToken, $tenantId, [
        'code' => 'ibu-200',
        'name' => 'Ibuprofen 200 mg',
        'generic_name' => 'Ibuprofen',
        'form' => 'tablet',
        'strength' => '200 mg',
    ], 'medication-create-2')
        ->assertCreated()
        ->json('data.id');

    pharmacyUpdateMedication($this, $adminToken, $tenantId, $ibuId, [
        'is_active' => false,
        'description' => 'Inactive backup catalog entry.',
    ], 'medication-update-1')
        ->assertOk()
        ->assertJsonPath('status', 'medication_updated')
        ->assertJsonPath('data.is_active', false);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/medications?is_active=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $amoxId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/medications/search?q=amox')
        ->assertOk()
        ->assertJsonPath('meta.filters.q', 'amox')
        ->assertJsonPath('data.0.id', $amoxId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/medications/'.$amoxId)
        ->assertOk()
        ->assertJsonPath('data.id', $amoxId)
        ->assertJsonPath('data.name', 'Amoxicillin 500 mg');

    $amoxAllergyId = pharmacyAddAllergy($this, $adminToken, $tenantId, $patientId, [
        'medication_id' => $amoxId,
        'reaction' => 'Rash and itching.',
        'severity' => 'moderate',
        'noted_at' => '2026-03-10T08:00:00+05:00',
    ], 'allergy-create-1')
        ->assertCreated()
        ->assertJsonPath('status', 'patient_allergy_created')
        ->assertJsonPath('data.allergen_name', 'Amoxicillin 500 mg')
        ->assertJsonPath('data.medication.id', $amoxId)
        ->json('data.id');

    $penicillinAllergyId = pharmacyAddAllergy($this, $adminToken, $tenantId, $patientId, [
        'allergen_name' => 'Penicillin',
        'severity' => 'life_threatening',
        'notes' => 'Historic anaphylaxis.',
    ], 'allergy-create-2')
        ->assertCreated()
        ->json('data.id');

    pharmacyAddAllergy($this, $adminToken, $tenantId, $patientId, [
        'allergen_name' => 'Penicillin',
    ], 'allergy-create-duplicate')
        ->assertConflict()
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/allergies')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $penicillinAllergyId)
        ->assertJsonPath('data.1.id', $amoxAllergyId);

    $draftPrescriptionId = pharmacyCreatePrescription($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'medication_name' => 'Amoxicillin 500 mg',
        'medication_code' => 'amox-500',
        'dosage' => '500 mg',
        'route' => 'oral',
        'frequency' => 'twice_daily',
        'quantity' => '14',
        'authorized_refills' => 0,
    ], 'prescription-create-draft')
        ->assertCreated()
        ->json('data.id');

    $issuedPrescriptionId = pharmacyCreatePrescription($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'encounter_id' => $encounterId,
        'treatment_item_id' => $itemId,
        'medication_name' => 'Amoxicillin 500 mg',
        'medication_code' => 'AMOX-500',
        'dosage' => '500 mg',
        'route' => 'oral',
        'frequency' => 'twice_daily',
        'quantity' => '14',
        'authorized_refills' => 1,
    ], 'prescription-create-issued')
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-issue-t047',
        ])
        ->postJson('/api/v1/prescriptions/'.$issuedPrescriptionId.':issue')
        ->assertOk()
        ->assertJsonPath('status', 'prescription_issued');

    $canceledPrescriptionId = pharmacyCreatePrescription($this, $adminToken, $tenantId, [
        'patient_id' => $patientId,
        'provider_id' => $providerId,
        'medication_name' => 'Ibuprofen 200 mg',
        'medication_code' => 'IBU-200',
        'dosage' => '200 mg',
        'route' => 'oral',
        'frequency' => 'three_times_daily',
        'quantity' => '21',
        'authorized_refills' => 0,
    ], 'prescription-create-canceled')
        ->assertCreated()
        ->json('data.id');

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'prescription-cancel-t047',
        ])
        ->postJson('/api/v1/prescriptions/'.$canceledPrescriptionId.':cancel', [
            'reason' => 'Changed treatment approach.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'prescription_canceled');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/medications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissing(['prescription_id' => $draftPrescriptionId])
        ->assertJsonPath('meta.filters.limit', 25);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/medications?status=issued')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.filters.status', 'issued')
        ->assertJsonPath('data.0.prescription_id', $issuedPrescriptionId)
        ->assertJsonPath('data.0.medication.catalog.id', $amoxId);

    pharmacyDeleteMedication($this, $adminToken, $tenantId, $amoxId, 'medication-delete-1')
        ->assertOk()
        ->assertJsonPath('status', 'medication_deleted')
        ->assertJsonPath('data.id', $amoxId);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/allergies')
        ->assertOk()
        ->assertJsonPath('data.1.id', $amoxAllergyId)
        ->assertJsonPath('data.1.allergen_name', 'Amoxicillin 500 mg')
        ->assertJsonPath('data.1.medication', null);

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/medications?status=issued')
        ->assertOk()
        ->assertJsonPath('data.0.prescription_id', $issuedPrescriptionId)
        ->assertJsonPath('data.0.medication.catalog', null);

    $this->withToken($adminToken)
        ->withHeaders([
            'X-Tenant-Id' => $tenantId,
            'Idempotency-Key' => 'allergy-delete-1',
        ])
        ->deleteJson('/api/v1/patients/'.$patientId.'/allergies/'.$penicillinAllergyId)
        ->assertOk()
        ->assertJsonPath('status', 'patient_allergy_deleted')
        ->assertJsonPath('data.id', $penicillinAllergyId);

    expect(AuditEventRecord::query()->where('action', 'medications.created')->where('object_id', $amoxId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'medications.updated')->where('object_id', $ibuId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'medications.deleted')->where('object_id', $amoxId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'patient_allergies.created')->where('object_id', $amoxAllergyId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'patient_allergies.deleted')->where('object_id', $penicillinAllergyId)->exists())->toBeTrue();
});

it('enforces medication and allergy permissions plus allergy validation', function (): void {
    $manager = User::factory()->create([
        'email' => 'pharmacy.manager@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'pharmacy.viewer.only@openai.com',
        'password' => 'secret-password',
    ]);
    $manageOnly = User::factory()->create([
        'email' => 'pharmacy.manage.only@openai.com',
        'password' => 'secret-password',
    ]);

    $managerToken = pharmacyIssueBearerToken($this, 'pharmacy.manager@openai.com');
    $viewerToken = pharmacyIssueBearerToken($this, 'pharmacy.viewer.only@openai.com');
    $manageOnlyToken = pharmacyIssueBearerToken($this, 'pharmacy.manage.only@openai.com');
    $tenantId = pharmacyCreateTenant($this, $managerToken, 'Pharmacy Permission Tenant')->json('data.id');

    pharmacyGrantPermissions($manager, $tenantId, [
        'prescriptions.view',
        'prescriptions.manage',
        'patients.manage',
        'providers.manage',
    ]);
    pharmacyGrantPermissions($viewer, $tenantId, ['prescriptions.view']);
    pharmacyGrantPermissions($manageOnly, $tenantId, ['prescriptions.manage']);

    $patientId = pharmacyCreatePatient($this, $managerToken, $tenantId)->json('data.id');

    pharmacyCreateMedication($this, $managerToken, $tenantId, [
        'code' => 'cet-10',
        'name' => 'Cetirizine 10 mg',
    ], 'medication-create-permissions')
        ->assertCreated();

    pharmacyCreateMedication($this, $managerToken, $tenantId, [
        'code' => 'CET-10',
        'name' => 'Duplicate Cetirizine',
    ], 'medication-create-duplicate')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    pharmacyCreateMedication($this, $viewerToken, $tenantId, [
        'code' => 'viewer-med',
        'name' => 'Viewer Medication',
    ], 'medication-create-viewer')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    pharmacyAddAllergy($this, $viewerToken, $tenantId, $patientId, [
        'allergen_name' => 'Dust',
    ], 'allergy-create-viewer')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    pharmacyAddAllergy($this, $managerToken, $tenantId, $patientId, [
        'reaction' => 'Sneezing',
    ], 'allergy-create-invalid')
        ->assertStatus(422)
        ->assertJsonPath('code', 'UNPROCESSABLE_ENTITY');

    $this->withToken($manageOnlyToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/medications')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');
});
