<?php

use App\Models\User;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('creates tenant memberships, bootstraps tenant administration, and lists visible tenants', function (): void {
    $creator = User::factory()->create([
        'email' => 'tenant.creator+1@example.test',
        'password' => 'secret-password',
    ]);
    $token = tenantIssueBearerToken($this, 'tenant.creator+1@example.test');

    $tenantResponse = $this->withToken($token)
        ->postJson('/api/v1/tenants', [
            'name' => 'Acme Health',
            'contact_email' => 'ops@acme.com',
            'contact_phone' => '+998901112233',
        ])
        ->assertCreated()
        ->assertJsonPath('status', 'tenant_created')
        ->assertJsonPath('data.name', 'Acme Health')
        ->assertJsonPath('data.membership_status', 'active');

    $tenantId = $tenantResponse->json('data.id');

    $this->withToken($token)
        ->getJson('/api/v1/tenants')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $tenantId)
        ->assertJsonPath('data.0.membership_status', 'active');

    $this->withToken($token)
        ->getJson('/api/v1/tenants/'.$tenantId)
        ->assertOk()
        ->assertJsonPath('data.id', $tenantId)
        ->assertJsonPath('data.contact_email', 'ops@acme.com')
        ->assertJsonPath('data.contact_phone', '+998901112233');

    expect(DB::table('tenant_user_memberships')->where('tenant_id', $tenantId)->where('user_id', (string) $creator->getAuthIdentifier())->exists())->toBeTrue();
    expect(DB::table('roles')->where('tenant_id', $tenantId)->where('name', 'Tenant Administrator')->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'tenants.created')->where('object_id', $tenantId)->exists())->toBeTrue();
});

it('updates tenant details, settings, limits, usage, and lifecycle with audit coverage', function (): void {
    $creator = User::factory()->create([
        'email' => 'tenant.creator+2@example.test',
        'password' => 'secret-password',
    ]);
    $token = tenantIssueBearerToken($this, 'tenant.creator+2@example.test');
    $tenantId = tenantCreate($this, $token, 'North Clinic')->json('data.id');

    $this->withToken($token)
        ->patchJson('/api/v1/tenants/'.$tenantId, [
            'name' => 'North Clinic Updated',
            'contact_phone' => '+998907770011',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'tenant_updated')
        ->assertJsonPath('data.name', 'North Clinic Updated')
        ->assertJsonPath('data.contact_phone', '+998907770011');

    $this->withToken($token)
        ->withHeader('X-Tenant-Id', $tenantId)
        ->putJson('/api/v1/tenants/'.$tenantId.'/settings', [
            'locale' => 'uz',
            'timezone' => 'Asia/Tashkent',
            'currency' => 'uzs',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'tenant_settings_updated')
        ->assertJsonPath('data.locale', 'uz')
        ->assertJsonPath('data.timezone', 'Asia/Tashkent')
        ->assertJsonPath('data.currency', 'UZS');

    $this->withToken($token)
        ->putJson('/api/v1/tenants/'.$tenantId.'/limits', [
            'users' => 25,
            'clinics' => 4,
            'providers' => 80,
            'patients' => 1000,
            'storage_gb' => 150,
            'monthly_notifications' => 10000,
        ])
        ->assertOk()
        ->assertJsonPath('status', 'tenant_limits_updated')
        ->assertJsonPath('data.users', 25)
        ->assertJsonPath('data.storage_gb', 150);

    $this->withToken($token)
        ->getJson('/api/v1/tenants/'.$tenantId.'/usage')
        ->assertOk()
        ->assertJsonPath('data.tenant_id', $tenantId)
        ->assertJsonPath('data.users.used', 1)
        ->assertJsonPath('data.users.limit', 25)
        ->assertJsonPath('data.users.remaining', 24)
        ->assertJsonPath('data.clinics.used', 0)
        ->assertJsonPath('data.storage_gb.used', 0)
        ->assertJsonPath('data.monthly_notifications.limit', 10000);

    $this->withToken($token)
        ->postJson('/api/v1/tenants/'.$tenantId.':suspend')
        ->assertOk()
        ->assertJsonPath('status', 'tenant_suspended')
        ->assertJsonPath('data.status', 'suspended');

    $this->withToken($token)
        ->postJson('/api/v1/tenants/'.$tenantId.':activate')
        ->assertOk()
        ->assertJsonPath('status', 'tenant_activated')
        ->assertJsonPath('data.status', 'active');

    $this->withToken($token)
        ->getJson('/api/v1/tenants/'.$tenantId.'/settings')
        ->assertOk()
        ->assertJsonPath('data.currency', 'UZS');

    $this->withToken($token)
        ->getJson('/api/v1/tenants/'.$tenantId.'/limits')
        ->assertOk()
        ->assertJsonPath('data.patients', 1000);

    expect(AuditEventRecord::query()->where('action', 'tenants.updated')->where('object_id', $tenantId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'tenants.settings_updated')->where('object_id', $tenantId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'tenants.limits_updated')->where('object_id', $tenantId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'tenants.suspended')->where('object_id', $tenantId)->exists())->toBeTrue();
    expect(AuditEventRecord::query()->where('action', 'tenants.activated')->where('object_id', $tenantId)->exists())->toBeTrue();
});

it('isolates tenant visibility and rejects conflicting tenant route scope', function (): void {
    $alphaAdmin = User::factory()->create([
        'email' => 'tenant.alpha@example.test',
        'password' => 'secret-password',
    ]);
    $betaAdmin = User::factory()->create([
        'email' => 'tenant.beta@example.test',
        'password' => 'secret-password',
    ]);

    $alphaToken = tenantIssueBearerToken($this, 'tenant.alpha@example.test');
    $betaToken = tenantIssueBearerToken($this, 'tenant.beta@example.test');
    $alphaTenantId = tenantCreate($this, $alphaToken, 'Alpha Tenant')->json('data.id');
    $betaTenantId = tenantCreate($this, $betaToken, 'Beta Tenant')->json('data.id');

    $this->withToken($alphaToken)
        ->getJson('/api/v1/tenants')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $alphaTenantId);

    $this->withToken($alphaToken)
        ->getJson('/api/v1/tenants/'.$betaTenantId)
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

    $this->withToken($alphaToken)
        ->withHeader('X-Tenant-Id', $betaTenantId)
        ->getJson('/api/v1/tenants/'.$alphaTenantId)
        ->assertStatus(403)
        ->assertJsonPath('code', 'TENANT_SCOPE_VIOLATION');

    expect(DB::table('tenant_user_memberships')->where('tenant_id', $alphaTenantId)->where('user_id', (string) $betaAdmin->getAuthIdentifier())->exists())->toBeFalse();
});

it('deletes only suspended tenants and cleans up tenant-owned records', function (): void {
    $creator = User::factory()->create([
        'email' => 'tenant.creator+3@example.test',
        'password' => 'secret-password',
    ]);
    $token = tenantIssueBearerToken($this, 'tenant.creator+3@example.test');
    $tenantId = tenantCreate($this, $token, 'Cleanup Tenant')->json('data.id');

    $this->withToken($token)
        ->deleteJson('/api/v1/tenants/'.$tenantId)
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    $this->withToken($token)
        ->postJson('/api/v1/tenants/'.$tenantId.':suspend')
        ->assertOk();

    $this->withToken($token)
        ->deleteJson('/api/v1/tenants/'.$tenantId)
        ->assertOk()
        ->assertJsonPath('status', 'tenant_deleted')
        ->assertJsonPath('data.id', $tenantId);

    $this->withToken($token)
        ->getJson('/api/v1/tenants')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    expect(DB::table('tenants')->where('id', $tenantId)->exists())->toBeFalse();
    expect(DB::table('tenant_settings')->where('tenant_id', $tenantId)->exists())->toBeFalse();
    expect(DB::table('tenant_limits')->where('tenant_id', $tenantId)->exists())->toBeFalse();
    expect(DB::table('tenant_user_memberships')->where('tenant_id', $tenantId)->exists())->toBeFalse();
    expect(DB::table('roles')->where('tenant_id', $tenantId)->exists())->toBeFalse();
    expect(AuditEventRecord::query()->where('action', 'tenants.deleted')->where('object_id', $tenantId)->exists())->toBeTrue();
});

function tenantCreate($testCase, string $token, string $name)
{
    return $testCase->withToken($token)->postJson('/api/v1/tenants', [
        'name' => $name,
    ])->assertCreated();
}

function tenantIssueBearerToken($testCase, string $email, string $password = 'secret-password'): string
{
    return $testCase->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ])->assertOk()->json('tokens.access_token');
}
