<?php

use App\Models\User;
use App\Modules\AuditCompliance\Application\Contracts\AuditEventRepository;
use App\Modules\AuditCompliance\Application\Data\AuditActor;
use App\Modules\AuditCompliance\Application\Data\AuditEventData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

require_once __DIR__.'/../Treatment/TreatmentTestSupport.php';

function auditComplianceCreateContext($testCase, string $suffix, array $permissions): array
{
    $user = User::factory()->create([
        'email' => sprintf('audit.compliance.%s@openai.com', $suffix),
        'password' => 'secret-password',
    ]);

    $token = treatmentIssueBearerToken($testCase, sprintf('audit.compliance.%s@openai.com', $suffix));
    $tenantId = treatmentCreateTenant($testCase, $token, 'Audit Compliance '.$suffix)->json('data.id');
    treatmentGrantPermissions($user, $tenantId, $permissions);

    return [$user, $token, $tenantId];
}

function auditComplianceAttachUser($testCase, string $tenantId, string $suffix, array $permissions): array
{
    $user = User::factory()->create([
        'email' => sprintf('audit.compliance.%s@openai.com', $suffix),
        'password' => 'secret-password',
    ]);

    treatmentEnsureMembership($user, $tenantId);
    treatmentGrantPermissions($user, $tenantId, $permissions);

    return [$user, treatmentIssueBearerToken($testCase, sprintf('audit.compliance.%s@openai.com', $suffix))];
}

function auditComplianceAppendEvent(
    ?string $tenantId,
    string $action,
    string $objectType,
    string $objectId,
    array $before = [],
    array $after = [],
    array $metadata = [],
    ?CarbonImmutable $occurredAt = null,
): AuditEventData {
    $event = new AuditEventData(
        eventId: (string) Str::uuid(),
        tenantId: $tenantId,
        action: $action,
        objectType: $objectType,
        objectId: $objectId,
        actor: new AuditActor('user', (string) Str::uuid(), 'Compliance Tester'),
        requestId: (string) Str::uuid(),
        correlationId: (string) Str::uuid(),
        before: $before,
        after: $after,
        metadata: $metadata,
        occurredAt: $occurredAt ?? CarbonImmutable::now(),
    );

    app(AuditEventRepository::class)->append($event);

    return $event;
}
