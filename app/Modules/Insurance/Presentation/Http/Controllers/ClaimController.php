<?php

namespace App\Modules\Insurance\Presentation\Http\Controllers;

use App\Modules\Insurance\Application\Commands\CreateClaimCommand;
use App\Modules\Insurance\Application\Commands\DeleteClaimCommand;
use App\Modules\Insurance\Application\Commands\UpdateClaimCommand;
use App\Modules\Insurance\Application\Data\ClaimSearchCriteria;
use App\Modules\Insurance\Application\Handlers\CreateClaimCommandHandler;
use App\Modules\Insurance\Application\Handlers\DeleteClaimCommandHandler;
use App\Modules\Insurance\Application\Handlers\ExportClaimsQueryHandler;
use App\Modules\Insurance\Application\Handlers\GetClaimQueryHandler;
use App\Modules\Insurance\Application\Handlers\ListClaimsQueryHandler;
use App\Modules\Insurance\Application\Handlers\SearchClaimsQueryHandler;
use App\Modules\Insurance\Application\Handlers\UpdateClaimCommandHandler;
use App\Modules\Insurance\Application\Queries\ExportClaimsQuery;
use App\Modules\Insurance\Application\Queries\GetClaimQuery;
use App\Modules\Insurance\Application\Queries\ListClaimsQuery;
use App\Modules\Insurance\Application\Queries\SearchClaimsQuery;
use App\Modules\Insurance\Domain\Claims\ClaimStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ClaimController
{
    public function create(Request $request, CreateClaimCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $claim = $handler->handle(new CreateClaimCommand($validated));

        return response()->json([
            'status' => 'claim_created',
            'data' => $claim->toArray(),
        ], 201);
    }

    public function delete(string $claimId, DeleteClaimCommandHandler $handler): JsonResponse
    {
        $claim = $handler->handle(new DeleteClaimCommand($claimId));

        return response()->json([
            'status' => 'claim_deleted',
            'data' => $claim->toArray(),
        ]);
    }

    public function export(Request $request, ExportClaimsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 1000, 1000);
        $validated = $request->validate($this->exportRules(1000));
        /** @var array<string, mixed> $validated */
        $export = $handler->handle(new ExportClaimsQuery(
            criteria: $criteria,
            format: array_key_exists('format', $validated) && is_string($validated['format'])
                ? $validated['format']
                : 'csv',
        ));

        return response()->json([
            'status' => 'claim_export_created',
            'data' => $export->toArray(),
        ]);
    }

    public function list(Request $request, ListClaimsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($claim): array => $claim->toArray(),
                $handler->handle(new ListClaimsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function search(Request $request, SearchClaimsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($claim): array => $claim->toArray(),
                $handler->handle(new SearchClaimsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $claimId, GetClaimQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetClaimQuery($claimId))->toArray(),
        ]);
    }

    public function update(string $claimId, Request $request, UpdateClaimCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $claim = $handler->handle(new UpdateClaimCommand($claimId, $validated));

        return response()->json([
            'status' => 'claim_updated',
            'data' => $claim->toArray(),
        ]);
    }

    private function criteria(Request $request, int $defaultLimit, int $maxLimit): ClaimSearchCriteria
    {
        $validated = $request->validate($this->listRules($maxLimit));
        /** @var array<string, mixed> $validated */
        $this->assertDateRange($validated, 'service_date_from', 'service_date_to', 'service_date');
        $this->assertDateRange($validated, 'created_from', 'created_to', 'created_at');

        return new ClaimSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            status: $this->stringValue($validated, 'status'),
            payerId: $this->stringValue($validated, 'payer_id'),
            patientId: $this->stringValue($validated, 'patient_id'),
            invoiceId: $this->stringValue($validated, 'invoice_id'),
            serviceDateFrom: $this->stringValue($validated, 'service_date_from'),
            serviceDateTo: $this->stringValue($validated, 'service_date_to'),
            createdFrom: $this->stringValue($validated, 'created_from'),
            createdTo: $this->stringValue($validated, 'created_to'),
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : $defaultLimit,
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'invoice_id' => ['required', 'uuid'],
            'payer_id' => ['required', 'uuid'],
            'patient_policy_id' => ['sometimes', 'nullable', 'uuid'],
            'service_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'billed_amount' => ['sometimes', 'nullable', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function exportRules(int $maxLimit): array
    {
        return $this->listRules($maxLimit) + [
            'format' => ['sometimes', 'string', 'in:csv'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function listRules(int $maxLimit): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:'.implode(',', ClaimStatus::all())],
            'payer_id' => ['nullable', 'uuid'],
            'patient_id' => ['nullable', 'uuid'],
            'invoice_id' => ['nullable', 'uuid'],
            'service_date_from' => ['nullable', 'date_format:Y-m-d'],
            'service_date_to' => ['nullable', 'date_format:Y-m-d'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'payer_id' => ['sometimes', 'filled', 'uuid'],
            'patient_policy_id' => ['sometimes', 'nullable', 'uuid'],
            'service_date' => ['sometimes', 'filled', 'date_format:Y-m-d'],
            'billed_amount' => ['sometimes', 'filled', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function assertNonEmptyPatch(array $validated): void
    {
        if ($validated === []) {
            throw ValidationException::withMessages([
                'payload' => ['At least one updatable field is required.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertDateRange(array $validated, string $fromKey, string $toKey, string $errorKey): void
    {
        $from = $this->stringValue($validated, $fromKey);
        $to = $this->stringValue($validated, $toKey);

        if ($from !== null && $to !== null && CarbonImmutable::parse($from)->greaterThan(CarbonImmutable::parse($to))) {
            throw ValidationException::withMessages([
                $errorKey => ['The end date must be on or after the start date.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }
}
