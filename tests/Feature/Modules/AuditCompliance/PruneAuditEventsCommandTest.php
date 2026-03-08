<?php

use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Data\AuditActor;
use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
