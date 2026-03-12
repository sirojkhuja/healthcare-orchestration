<?php

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Data\AuditActor;
use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('prunes audit events older than the requested retention window', function (): void {
    $repository = app(AuditEventRepository::class);

    $repository->append(new AuditEventData(
        eventId: (string) Str::uuid(),
        tenantId: null,
        action: 'patient.updated',
        objectType: 'patient',
        objectId: 'patient-123',
        actor: new AuditActor('service', null, config('app.name')),
        requestId: (string) Str::uuid(),
        correlationId: (string) Str::uuid(),
        before: ['status' => 'draft'],
        after: ['status' => 'active'],
        metadata: [],
        occurredAt: CarbonImmutable::now()->subDays(40),
    ));

    $recentEvent = new AuditEventData(
        eventId: (string) Str::uuid(),
        tenantId: null,
        action: 'patient.updated',
        objectType: 'patient',
        objectId: 'patient-123',
        actor: new AuditActor('service', null, config('app.name')),
        requestId: (string) Str::uuid(),
        correlationId: (string) Str::uuid(),
        before: ['status' => 'active'],
        after: ['status' => 'archived'],
        metadata: [],
        occurredAt: CarbonImmutable::now()->subDays(5),
    );

    $repository->append($recentEvent);

    Artisan::call('audit:prune', ['--days' => 30]);

    expect(Artisan::output())->toContain('Pruned 1 audit events.');
    expect($repository->findById($recentEvent->eventId))->not->toBeNull();
});

it('honors tenant retention overrides during audit pruning', function (): void {
    config()->set('medflow.audit.retention_days', 30);

    $repository = app(AuditEventRepository::class);
    $tenantWithOverride = (string) Str::uuid();
    $tenantWithDisabledPrune = (string) Str::uuid();

    foreach ([$tenantWithOverride, $tenantWithDisabledPrune] as $tenantId) {
        DB::table('tenants')->insert([
            'id' => $tenantId,
            'name' => 'Tenant '.substr($tenantId, 0, 8),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    DB::table('audit_retention_settings')->insert([
        [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantWithOverride,
            'retention_days' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantWithDisabledPrune,
            'retention_days' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $defaultPruned = appendAuditEvent($repository, (string) Str::uuid(), CarbonImmutable::now()->subDays(40));
    $overridePruned = appendAuditEvent($repository, $tenantWithOverride, CarbonImmutable::now()->subDays(20));
    $disabledKept = appendAuditEvent($repository, $tenantWithDisabledPrune, CarbonImmutable::now()->subDays(120));
    $globalPruned = appendAuditEvent($repository, null, CarbonImmutable::now()->subDays(45));

    Artisan::call('audit:prune');

    expect(Artisan::output())->toContain('Pruned 3 audit events.');
    expect($repository->findById($defaultPruned->eventId))->toBeNull();
    expect($repository->findById($overridePruned->eventId))->toBeNull();
    expect($repository->findById($globalPruned->eventId))->toBeNull();
    expect($repository->findById($disabledKept->eventId))->not->toBeNull();
});

function appendAuditEvent(
    AuditEventRepository $repository,
    ?string $tenantId,
    CarbonImmutable $occurredAt,
): AuditEventData {
    $event = new AuditEventData(
        eventId: (string) Str::uuid(),
        tenantId: $tenantId,
        action: 'patient.updated',
        objectType: 'patient',
        objectId: 'patient-123',
        actor: new AuditActor('service', null, config('app.name')),
        requestId: (string) Str::uuid(),
        correlationId: (string) Str::uuid(),
        before: ['status' => 'draft'],
        after: ['status' => 'active'],
        metadata: [],
        occurredAt: $occurredAt,
    );

    $repository->append($event);

    return $event;
}
