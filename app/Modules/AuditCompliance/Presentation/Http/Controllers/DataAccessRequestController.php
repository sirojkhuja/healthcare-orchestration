<?php

namespace App\Modules\AuditCompliance\Presentation\Http\Controllers;

use App\Modules\AuditCompliance\Application\Commands\ApproveDataAccessRequestCommand;
use App\Modules\AuditCompliance\Application\Commands\CreateDataAccessRequestCommand;
use App\Modules\AuditCompliance\Application\Commands\DenyDataAccessRequestCommand;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestData;
use App\Modules\AuditCompliance\Application\Data\DataAccessRequestSearchCriteria;
use App\Modules\AuditCompliance\Application\Handlers\ApproveDataAccessRequestCommandHandler;
use App\Modules\AuditCompliance\Application\Handlers\CreateDataAccessRequestCommandHandler;
use App\Modules\AuditCompliance\Application\Handlers\DenyDataAccessRequestCommandHandler;
use App\Modules\AuditCompliance\Application\Handlers\GetDataAccessRequestQueryHandler;
use App\Modules\AuditCompliance\Application\Handlers\ListDataAccessRequestsQueryHandler;
use App\Modules\AuditCompliance\Application\Queries\GetDataAccessRequestQuery;
use App\Modules\AuditCompliance\Application\Queries\ListDataAccessRequestsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class DataAccessRequestController
{
    public function approve(
        string $requestId,
        Request $request,
        ApproveDataAccessRequestCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'decision_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'data_access_request_approved',
            'data' => $handler->handle(new ApproveDataAccessRequestCommand(
                requestId: $requestId,
                decisionNotes: $this->nullableString($validated, 'decision_notes'),
            ))->toArray(),
        ]);
    }

    public function create(Request $request, CreateDataAccessRequestCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'uuid'],
            'request_type' => ['required', 'string', 'max:64'],
            'requested_by_name' => ['required', 'string', 'max:160'],
            'requested_by_relationship' => ['nullable', 'string', 'max:120'],
            'requested_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'data_access_request_created',
            'data' => $handler->handle(new CreateDataAccessRequestCommand([
                'patient_id' => $validated['patient_id'],
                'request_type' => $this->normalizedIdentifier($validated, 'request_type'),
                'requested_by_name' => $validated['requested_by_name'],
                'requested_by_relationship' => $this->nullableString($validated, 'requested_by_relationship'),
                'requested_at' => $this->stringValue($validated, 'requested_at'),
                'reason' => $this->nullableString($validated, 'reason'),
                'notes' => $this->nullableString($validated, 'notes'),
            ]))->toArray(),
        ], 201);
    }

    public function deny(
        string $requestId,
        Request $request,
        DenyDataAccessRequestCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
            'decision_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'data_access_request_denied',
            'data' => $handler->handle(new DenyDataAccessRequestCommand(
                requestId: $requestId,
                reason: $this->requiredString($validated, 'reason'),
                decisionNotes: $this->nullableString($validated, 'decision_notes'),
            ))->toArray(),
        ]);
    }

    public function list(Request $request, ListDataAccessRequestsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'patient_id' => ['nullable', 'uuid'],
            'request_type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'in:submitted,approved,denied'],
            'requested_from' => ['nullable', 'date'],
            'requested_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        $criteria = new DataAccessRequestSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            patientId: $this->stringValue($validated, 'patient_id'),
            requestType: $this->normalizedIdentifier($validated, 'request_type'),
            status: $this->stringValue($validated, 'status'),
            requestedFrom: $this->dateValue($validated, 'requested_from'),
            requestedTo: $this->dateValue($validated, 'requested_to'),
            limit: $this->integerValue($validated, 'limit', 50),
        );

        if ($criteria->requestedFrom instanceof CarbonImmutable && $criteria->requestedTo instanceof CarbonImmutable && $criteria->requestedFrom->gt($criteria->requestedTo)) {
            throw ValidationException::withMessages([
                'requested_at' => ['The end timestamp must be on or after the start timestamp.'],
            ]);
        }

        return response()->json([
            'data' => array_map(
                static fn (DataAccessRequestData $requestData): array => $requestData->toArray(),
                $handler->handle(new ListDataAccessRequestsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $requestId, GetDataAccessRequestQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetDataAccessRequestQuery($requestId))->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function dateValue(array $validated, string $key): ?CarbonImmutable
    {
        $value = $this->stringValue($validated, $key);

        return $value !== null ? CarbonImmutable::parse($value) : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function integerValue(array $validated, string $key, int $default): int
    {
        return array_key_exists($key, $validated) && is_numeric($validated[$key])
            ? (int) $validated[$key]
            : $default;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function normalizedIdentifier(array $validated, string $key): ?string
    {
        $value = $this->stringValue($validated, $key);

        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value));
        $result = trim(is_string($normalized) ? $normalized : '', '_');

        return $result !== '' ? $result : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        $value = $this->stringValue($validated, $key);

        return $value !== null ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function requiredString(array $validated, string $key): string
    {
        return $this->stringValue($validated, $key) ?? '';
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && trim($validated[$key]) !== ''
            ? trim($validated[$key])
            : null;
    }
}
