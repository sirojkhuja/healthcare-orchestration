<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/PatientTestSupport.php';

uses(RefreshDatabase::class);

it('uploads lists shows and deletes patient document metadata on the attachments disk', function (): void {
    Storage::fake('attachments');

    User::factory()->create([
        'email' => 'patient.documents.admin@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.documents.viewer@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.documents.admin@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.documents.viewer@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Documents Tenant')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Saida',
            'last_name' => 'Nurmurodova',
            'sex' => 'female',
            'birth_date' => '1994-05-12',
        ])
        ->assertCreated()
        ->json('data.id');

    $uploadResponse = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->post('/api/v1/patients/'.$patientId.'/documents', [
            'document' => patientDocumentUpload('cbc-results.pdf', 'application/pdf'),
            'document_type' => 'lab_result',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'patient_document_uploaded')
        ->assertJsonPath('data.title', 'cbc-results.pdf')
        ->assertJsonPath('data.document_type', 'lab_result')
        ->assertJsonPath('data.file_name', 'cbc-results.pdf');

    $documentId = $uploadResponse->json('data.id');
    $auditAfter = AuditEventRecord::query()
        ->where('action', 'patients.document_uploaded')
        ->where('object_id', $patientId)
        ->latest('occurred_at')
        ->firstOrFail();
    $storedPath = (string) data_get($auditAfter->getAttribute('after_values'), 'document.file_name');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/documents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $documentId)
        ->assertJsonPath('data.0.file_name', 'cbc-results.pdf');

    $showResponse = $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/documents/'.$documentId)
        ->assertOk()
        ->assertJsonPath('data.id', $documentId)
        ->assertJsonPath('data.mime_type', 'application/pdf');

    expect($showResponse->json('data.storage_disk'))->toBeNull();
    expect($showResponse->json('data.storage_path'))->toBeNull();

    $persistedPath = (string) \Illuminate\Support\Facades\DB::table('patient_documents')
        ->where('id', $documentId)
        ->value('storage_path');
    Storage::disk('attachments')->assertExists($persistedPath);

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/patients/'.$patientId.'/documents/'.$documentId)
        ->assertOk()
        ->assertJsonPath('status', 'patient_document_deleted')
        ->assertJsonPath('data.id', $documentId);

    Storage::disk('attachments')->assertMissing($persistedPath);
    expect($storedPath)->toBe('cbc-results.pdf');
    expect(AuditEventRecord::query()->where('action', 'patients.document_uploaded')->where('object_id', $patientId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'patients.document_deleted')->where('object_id', $patientId)->exists())->toBeTrue();
});

it('enforces patient document permissions tenant scope and upload validation', function (): void {
    Storage::fake('attachments');

    User::factory()->create([
        'email' => 'patient.documents.admin+2@openai.com',
        'password' => 'secret-password',
    ]);
    $viewer = User::factory()->create([
        'email' => 'patient.documents.viewer+2@openai.com',
        'password' => 'secret-password',
    ]);
    $blocked = User::factory()->create([
        'email' => 'patient.documents.blocked@openai.com',
        'password' => 'secret-password',
    ]);

    $adminToken = patientIssueBearerToken($this, 'patient.documents.admin+2@openai.com');
    $viewerToken = patientIssueBearerToken($this, 'patient.documents.viewer+2@openai.com');
    $blockedToken = patientIssueBearerToken($this, 'patient.documents.blocked@openai.com');
    $tenantId = patientCreateTenant($this, $adminToken, 'Patient Documents Tenant Two')->json('data.id');

    patientGrantPermissions($viewer, $tenantId, ['patients.view']);
    patientEnsureMembership($blocked, $tenantId);

    $patientId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/patients', [
            'first_name' => 'Jasur',
            'last_name' => 'Karimov',
            'sex' => 'male',
            'birth_date' => '1990-10-10',
        ])
        ->assertCreated()
        ->json('data.id');

    $documentId = $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->post('/api/v1/patients/'.$patientId.'/documents', [
            'document' => patientDocumentUpload('triage-note.pdf', 'application/pdf'),
            'title' => 'Triage Note',
        ])
        ->assertCreated()
        ->json('data.id');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/documents')
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/documents/'.$documentId)
        ->assertOk();

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Accept', 'application/json')
        ->post('/api/v1/patients/'.$patientId.'/documents', [
            'document' => patientDocumentUpload('denied.pdf', 'application/pdf'),
        ])
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($viewerToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/patients/'.$patientId.'/documents/'.$documentId)
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($blockedToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/documents')
        ->assertForbidden()
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->withHeader('Accept', 'application/json')
        ->post('/api/v1/patients/'.$patientId.'/documents', [
            'document' => patientDocumentUpload('blocked.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');

    $otherTenantId = patientCreateTenant($this, $adminToken, 'Patient Documents Tenant Three')->json('data.id');

    $this->withToken($adminToken)
        ->withHeader('X-Tenant-Id', $otherTenantId)
        ->getJson('/api/v1/patients/'.$patientId.'/documents/'.$documentId)
        ->assertNotFound()
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});

function patientDocumentUpload(string $name, string $mimeType): UploadedFile
{
    return UploadedFile::fake()->create($name, 128, $mimeType);
}
