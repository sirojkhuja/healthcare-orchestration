<?php

namespace App\Modules\AuditCompliance\Application\Services;

use App\Modules\AuditCompliance\Application\Contracts\AuditTrailWriter;
use App\Modules\AuditCompliance\Application\Contracts\DataAccessRequestRepository;
use App\Modules\AuditCompliance\Application\Data\AuditRecordInput;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestData;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestSearchCriteria;
use App\Modules\IdentityAccess\Application\Contracts\AuthenticatedRequestContext;
use App\Modules\Patient\Application\Contracts\PatientRepository;
use App\Modules\Patient\Application\Data\PatientData;
use App\Shared\Application\Contracts\TenantContext;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class DataAccessRequestService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PatientRepository $patientRepository,
        private readonly DataAccessRequestRepository $dataAccessRequestRepository,
        private readonly AuthenticatedRequestContext $authenticatedRequestContext,
        private readonly AuditTrailWriter $auditTrailWriter,
    ) {}

    public function approve(string $requestId, ?string $decisionNotes): DataAccessRequestData
    {
        $request = $this->requestOrFail($requestId);

        if ($request->status !== 'submitted') {
            throw new ConflictHttpException('Only submitted data access requests may be approved.');
        }

        $actor = $this->authenticatedRequestContext->current()->user;
        $approved = $this->dataAccessRequestRepository->approve(
            $this->tenantContext->requireTenantId(),
            $request->requestId,
            [
                'approved_at' => CarbonImmutable::now(),
                'approved_by_user_id' => $actor->id,
                'approved_by_name' => $actor->name,
                'decision_notes' => $this->nullableString($decisionNotes),
            ],
        );

        if (! $approved instanceof DataAccessRequestData) {
            throw new \LogicException('Approved data access request could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'compliance.data_access_request_approved',
            objectType: 'data_access_request',
            objectId: $approved->requestId,
            before: $request->toArray(),
            after: $approved->toArray(),
            metadata: [
                'patient_id' => $approved->patientId,
                'request_type' => $approved->requestType,
            ],
        ));

        return $approved;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DataAccessRequestData
    {
        $tenantId = $this->tenantContext->requireTenantId();
        $patient = $this->patientOrFail($this->requiredString($attributes['patient_id'] ?? null, 'patient_id'));
        $normalized = $this->normalizeCreateAttributes($attributes);
        $request = $this->dataAccessRequestRepository->create($tenantId, [
            ...$normalized,
            'patient_id' => $patient->patientId,
        ]);

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'compliance.data_access_request_created',
            objectType: 'data_access_request',
            objectId: $request->requestId,
            after: $request->toArray(),
            metadata: [
                'patient_id' => $request->patientId,
                'request_type' => $request->requestType,
            ],
        ));

        return $request;
    }

    public function deny(string $requestId, string $reason, ?string $decisionNotes): DataAccessRequestData
    {
        $request = $this->requestOrFail($requestId);

        if ($request->status !== 'submitted') {
            throw new ConflictHttpException('Only submitted data access requests may be denied.');
        }

        $actor = $this->authenticatedRequestContext->current()->user;
        $denied = $this->dataAccessRequestRepository->deny(
            $this->tenantContext->requireTenantId(),
            $request->requestId,
            [
                'denied_at' => CarbonImmutable::now(),
                'denied_by_user_id' => $actor->id,
                'denied_by_name' => $actor->name,
                'denial_reason' => $this->requiredString($reason, 'reason'),
                'decision_notes' => $this->nullableString($decisionNotes),
            ],
        );

        if (! $denied instanceof DataAccessRequestData) {
            throw new \LogicException('Denied data access request could not be reloaded.');
        }

        $this->auditTrailWriter->record(new AuditRecordInput(
            action: 'compliance.data_access_request_denied',
            objectType: 'data_access_request',
            objectId: $denied->requestId,
            before: $request->toArray(),
            after: $denied->toArray(),
            metadata: [
                'patient_id' => $denied->patientId,
                'request_type' => $denied->requestType,
            ],
        ));

        return $denied;
    }

    public function get(string $requestId): DataAccessRequestData
    {
        return $this->requestOrFail($requestId);
    }

    /**
     * @return list<DataAccessRequestData>
     */
    public function list(DataAccessRequestSearchCriteria $criteria): array
    {
        return $this->dataAccessRequestRepository->listForTenant(
            $this->tenantContext->requireTenantId(),
            $criteria,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     request_type: string,
     *     requested_by_name: string,
     *     requested_by_relationship: ?string,
     *     requested_at: CarbonImmutable,
     *     reason: ?string,
     *     notes: ?string
     * }
     */
    private function normalizeCreateAttributes(array $attributes): array
    {
        return [
            'request_type' => $this->normalizedIdentifier($attributes['request_type'] ?? null, 'request_type'),
            'requested_by_name' => $this->requiredString($attributes['requested_by_name'] ?? null, 'requested_by_name'),
            'requested_by_relationship' => $this->nullableString($attributes['requested_by_relationship'] ?? null),
            'requested_at' => $this->dateTimeOrNow($attributes['requested_at'] ?? null),
            'reason' => $this->nullableString($attributes['reason'] ?? null),
            'notes' => $this->nullableString($attributes['notes'] ?? null),
        ];
    }

    private function normalizedIdentifier(mixed $value, string $label): string
    {
        $string = $this->requiredString($value, $label);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($string));
        $result = trim(is_string($normalized) ? $normalized : '', '_');

        if ($result === '') {
            throw new UnprocessableEntityHttpException('The '.$label.' value is not valid.');
        }

        return $result;
    }

    private function patientOrFail(string $patientId): PatientData
    {
        $patient = $this->patientRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $patientId,
        );

        if (! $patient instanceof PatientData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $patient;
    }

    private function requestOrFail(string $requestId): DataAccessRequestData
    {
        $request = $this->dataAccessRequestRepository->findInTenant(
            $this->tenantContext->requireTenantId(),
            $requestId,
        );

        if (! $request instanceof DataAccessRequestData) {
            throw new NotFoundHttpException('The requested resource does not exist in the current tenant scope.');
        }

        return $request;
    }

    private function dateTimeOrNow(mixed $value): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::now();
        }

        if (! is_string($value)) {
            throw new UnprocessableEntityHttpException('The datetime value is not valid.');
        }

        return CarbonImmutable::parse($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function requiredString(mixed $value, string $label): string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            throw new UnprocessableEntityHttpException('The '.$label.' field is required.');
        }

        return $normalized;
    }
}
