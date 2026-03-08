<?php

use App\Models\User;
use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\AuditCompliance\Infrastructure\Persistence\AuditEventRecord;
use App\Shared\Application\Contracts\RequestMetadataContext;
use App\Shared\Application\Contracts\TenantContext;
use App\Shared\Application\Data\RequestMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('persists immutable audit events with actor, request metadata, and before/after values', function (): void {
    $tenantId = (string) Str::uuid();
    $requestId = (string) Str::uuid();
    $correlationId = (string) Str::uuid();
    $user = User::factory()->create([
        'name' => 'Audit User',
    ]);

    $this->actingAs($user);

    app(RequestMetadataContext::class)->initialize(new RequestMetadata(
        requestId: $requestId,
        correlationId: $correlationId,
        causationId: (string) Str::uuid(),
    ));
    app(TenantContext::class)->initialize($tenantId, 'test');

    $event = app(AuditTrailWriter::class)->record(new AuditRecordInput(
        action: 'patient.updated',
        objectType: 'patient',
        objectId: 'patient-123',
        before: ['status' => 'draft'],
        after: ['status' => 'active'],
        metadata: ['source' => 'feature-test'],
    ));

    $stored = app(AuditEventRepository::class)->findById($event->eventId);

    expect($stored)->not->toBeNull();
    expect($stored?->tenantId)->toBe($tenantId);
    expect($stored?->actor->type)->toBe('user');
    expect($stored?->actor->id)->toBe((string) $user->getAuthIdentifier());
    expect($stored?->actor->name)->toBe('Audit User');
    expect($stored?->requestId)->toBe($requestId);
    expect($stored?->correlationId)->toBe($correlationId);
    expect($stored?->before)->toBe(['status' => 'draft']);
    expect($stored?->after)->toBe(['status' => 'active']);
    expect($stored?->metadata)->toBe(['source' => 'feature-test']);
});

test('audit event records cannot be updated or deleted after they are written', function (): void {
    app(RequestMetadataContext::class)->initialize(new RequestMetadata(
        requestId: (string) Str::uuid(),
        correlationId: (string) Str::uuid(),
        causationId: (string) Str::uuid(),
    ));

    $event = app(AuditTrailWriter::class)->record(new AuditRecordInput(
        action: 'patient.created',
        objectType: 'patient',
        objectId: 'patient-123',
    ));

    $record = AuditEventRecord::query()->findOrFail($event->eventId);

    expect(fn () => $record->forceFill(['action' => 'tampered'])->save())->toThrow(\LogicException::class);
    expect(fn () => $record->delete())->toThrow(\LogicException::class);
});
