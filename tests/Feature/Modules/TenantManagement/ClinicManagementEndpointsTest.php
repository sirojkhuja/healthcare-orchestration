<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates manages and deletes clinics while updating tenant usage', function (): void {
    User::factory()->create([
        'email' => 'clinic.admin+1@openai.com',
        'password' => 'secret-password',
    ]);

    $token = clinicIssueBearerToken($this, 'clinic.admin+1@openai.com');
    $tenantId = clinicCreateTenant($this, $token, 'Clinic Tenant Alpha')->json('data.id');

    $createResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics', [
            'code' => 'alpha-main',
            'name' => 'Alpha Main Clinic',
            'contact_email' => 'ops@acme.com',
            'contact_phone' => '+998901112233',
            'city_code' => 'tashkent',
            'district_code' => 'mirzo-ulugbek',
            'address_line_1' => '12 Amir Temur Street',
            'postal_code' => '100000',
            'notes' => 'Primary flagship clinic.',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'clinic_created')
        ->assertJsonPath('data.code', 'ALPHA-MAIN')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.city_code', 'tashkent');

    $clinicId = $createResponse->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/clinics?q=alpha')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $clinicId);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/clinics/'.$clinicId)
        ->assertOk()
        ->assertJsonPath('data.contact_email', 'ops@acme.com');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/clinics/'.$clinicId, [
            'name' => 'Alpha Main Clinic Updated',
            'district_code' => 'yunusabad',
            'address_line_2' => 'Suite 4B',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'clinic_updated')
        ->assertJsonPath('data.name', 'Alpha Main Clinic Updated')
        ->assertJsonPath('data.district_code', 'yunusabad')
        ->assertJsonPath('data.address_line_2', 'Suite 4B');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/clinics/'.$clinicId.'/settings')
        ->assertOk()
        ->assertJsonPath('data.default_appointment_duration_minutes', 30)
        ->assertJsonPath('data.slot_interval_minutes', 15)
        ->assertJsonPath('data.allow_walk_ins', true);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/clinics/'.$clinicId.'/settings', [
            'timezone' => 'Asia/Tashkent',
            'default_appointment_duration_minutes' => 45,
            'slot_interval_minutes' => 15,
            'allow_walk_ins' => false,
            'require_appointment_confirmation' => true,
            'telemedicine_enabled' => true,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'clinic_settings_updated')
        ->assertJsonPath('data.timezone', 'Asia/Tashkent')
        ->assertJsonPath('data.default_appointment_duration_minutes', 45)
        ->assertJsonPath('data.telemedicine_enabled', true);

    $this->withToken($token)
        ->getJson('/api/v1/tenants/'.$tenantId.'/usage')
        ->assertOk()
        ->assertJsonPath('data.clinics.used', 1)
        ->assertJsonPath('data.clinics.remaining', null);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.':deactivate')
        ->assertOk()
        ->assertJsonPath('status', 'clinic_deactivated')
        ->assertJsonPath('data.status', 'inactive');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.':activate')
        ->assertOk()
        ->assertJsonPath('status', 'clinic_activated')
        ->assertJsonPath('data.status', 'active');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/clinics/'.$clinicId)
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.':deactivate')
        ->assertOk();

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/clinics/'.$clinicId)
        ->assertOk()
        ->assertJsonPath('status', 'clinic_deleted')
        ->assertJsonPath('data.id', $clinicId);

    $this->withToken($token)
        ->getJson('/api/v1/tenants/'.$tenantId.'/usage')
        ->assertOk()
        ->assertJsonPath('data.clinics.used', 0);

    expect(AuditEventRecord::query()->where('action', 'clinics.created')->where('object_id', $clinicId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.updated')->where('object_id', $clinicId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.settings_updated')->where('object_id', $clinicId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.deactivated')->where('object_id', $clinicId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.activated')->where('object_id', $clinicId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.deleted')->where('object_id', $clinicId)->exists())->toBeTrue();
});

it('manages departments rooms schedules holidays and location references for a clinic', function (): void {
    User::factory()->create([
        'email' => 'clinic.admin+2@openai.com',
        'password' => 'secret-password',
    ]);

    $token = clinicIssueBearerToken($this, 'clinic.admin+2@openai.com');
    $tenantId = clinicCreateTenant($this, $token, 'Clinic Tenant Beta')->json('data.id');
    $clinicId = clinicCreateClinic($this, $token, $tenantId, 'beta-main', 'Beta Main Clinic')->json('data.id');

    $departmentResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.'/departments', [
            'code' => 'cardio',
            'name' => 'Cardiology',
            'description' => 'Heart care',
            'phone_extension' => '201',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'department_created')
        ->assertJsonPath('data.code', 'CARDIO');

    $departmentId = $departmentResponse->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/clinics/'.$clinicId.'/departments/'.$departmentId)
        ->assertOk()
        ->assertJsonPath('data.name', 'Cardiology');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/clinics/'.$clinicId.'/departments/'.$departmentId, [
            'description' => 'Advanced cardiology wing',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'department_updated')
        ->assertJsonPath('data.description', 'Advanced cardiology wing');

    $roomResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.'/rooms', [
            'department_id' => $departmentId,
            'code' => 'exam-01',
            'name' => 'Exam Room 01',
            'type' => 'consultation',
            'floor' => '2',
            'capacity' => 2,
            'notes' => 'Shared diagnostics room',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'room_created')
        ->assertJsonPath('data.department_id', $departmentId)
        ->assertJsonPath('data.code', 'EXAM-01');

    $roomId = $roomResponse->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/clinics/'.$clinicId.'/work-hours', [
            'days' => [
                'monday' => [
                    ['start_time' => '09:00', 'end_time' => '12:00'],
                    ['start_time' => '13:00', 'end_time' => '18:00'],
                ],
                'wednesday' => [
                    ['start_time' => '10:00', 'end_time' => '16:00'],
                ],
                'friday' => [
                    ['start_time' => '09:30', 'end_time' => '17:30'],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('status', 'clinic_work_hours_updated')
        ->assertJsonPath('data.days.monday.0.start_time', '09:00')
        ->assertJsonPath('data.days.tuesday', []);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/clinics/'.$clinicId.'/work-hours')
        ->assertOk()
        ->assertJsonPath('data.days.wednesday.0.end_time', '16:00')
        ->assertJsonPath('data.days.sunday', []);

    $holidayResponse = $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.'/holidays', [
            'name' => 'Navruz Closure',
            'start_date' => '2026-03-21',
            'end_date' => '2026-03-22',
            'is_closed' => true,
            'notes' => 'National holiday closure',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'clinic_holiday_created')
        ->assertJsonPath('data.name', 'Navruz Closure');

    $holidayId = $holidayResponse->json('data.id');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics/'.$clinicId.'/holidays', [
            'name' => 'Overlap Attempt',
            'start_date' => '2026-03-22',
            'end_date' => '2026-03-23',
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/clinics/'.$clinicId.'/holidays')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $holidayId);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/locations/cities?q=tash')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'tashkent');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/locations/districts?city_code=tashkent')
        ->assertOk()
        ->assertJsonFragment(['code' => 'yunusabad'])
        ->assertJsonFragment(['code' => 'mirzo-ulugbek']);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/locations/search?q=yunus')
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'district',
            'code' => 'yunusabad',
            'city_code' => 'tashkent',
        ]);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/clinics/'.$clinicId.'/departments/'.$departmentId)
        ->assertOk()
        ->assertJsonPath('status', 'department_deleted');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->getJson('/api/v1/clinics/'.$clinicId.'/rooms')
        ->assertOk()
        ->assertJsonPath('data.0.department_id', null);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->patchJson('/api/v1/clinics/'.$clinicId.'/rooms/'.$roomId, [
            'type' => 'virtual',
            'capacity' => 4,
            'department_id' => null,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'room_updated')
        ->assertJsonPath('data.type', 'virtual')
        ->assertJsonPath('data.capacity', 4);

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/clinics/'.$clinicId.'/rooms/'.$roomId)
        ->assertOk()
        ->assertJsonPath('status', 'room_deleted');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->deleteJson('/api/v1/clinics/'.$clinicId.'/holidays/'.$holidayId)
        ->assertOk()
        ->assertJsonPath('status', 'clinic_holiday_deleted');

    expect(AuditEventRecord::query()->where('action', 'clinics.department_created')->where('object_id', $departmentId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.department_updated')->where('object_id', $departmentId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.department_deleted')->where('object_id', $departmentId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.room_created')->where('object_id', $roomId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.room_updated')->where('object_id', $roomId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.room_deleted')->where('object_id', $roomId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.work_hours_updated')->where('object_id', $clinicId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.holiday_created')->where('object_id', $holidayId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'clinics.holiday_deleted')->where('object_id', $holidayId)->exists())->toBeTrue();
});

it('enforces tenant isolation for clinic resources', function (): void {
    User::factory()->create([
        'email' => 'clinic.alpha@openai.com',
        'password' => 'secret-password',
    ]);
    User::factory()->create([
        'email' => 'clinic.beta@openai.com',
        'password' => 'secret-password',
    ]);

    $alphaToken = clinicIssueBearerToken($this, 'clinic.alpha@openai.com');
    $betaToken = clinicIssueBearerToken($this, 'clinic.beta@openai.com');
    $alphaTenantId = clinicCreateTenant($this, $alphaToken, 'Alpha Clinics')->json('data.id');
    $betaTenantId = clinicCreateTenant($this, $betaToken, 'Beta Clinics')->json('data.id');
    $alphaClinicId = clinicCreateClinic($this, $alphaToken, $alphaTenantId, 'alpha-only', 'Alpha Only Clinic')->json('data.id');

    $this->withToken($betaToken)
        ->withHeader('X-Tenant-Id', $betaTenantId)
        ->getJson('/api/v1/clinics')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->withToken($betaToken)
        ->withHeader('X-Tenant-Id', $betaTenantId)
        ->getJson('/api/v1/clinics/'.$alphaClinicId)
        ->assertStatus(404)
        ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
});

function clinicCreateClinic($testCase, string $token, string $tenantId, string $code, string $name)
{
    return $testCase->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->postJson('/api/v1/clinics', [
            'code' => $code,
            'name' => $name,
            'city_code' => 'tashkent',
            'district_code' => 'yunusabad',
        ])
        ->assertCreated();
}

function clinicCreateTenant($testCase, string $token, string $name)
{
    return $testCase->withToken($token)
        ->postJson('/api/v1/tenants', [
            'name' => $name,
        ])
        ->assertCreated();
}

function clinicIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return $testCase->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('tokens.access_token');
}
