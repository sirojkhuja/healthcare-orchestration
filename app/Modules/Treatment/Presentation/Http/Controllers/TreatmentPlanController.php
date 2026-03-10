<?php

namespace App\Modules\Treatment\Presentation\Http\Controllers;

use App\Modules\Treatment\Application\Commands\CreateTreatmentPlanCommand;
use App\Modules\Treatment\Application\Commands\DeleteTreatmentPlanCommand;
use App\Modules\Treatment\Application\Commands\UpdateTreatmentPlanCommand;
use App\Modules\Treatment\Application\Data\TreatmentPlanSearchCriteria;
use App\Modules\Treatment\Application\Handlers\CreateTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Handlers\DeleteTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Handlers\GetTreatmentPlanQueryHandler;
use App\Modules\Treatment\Application\Handlers\ListTreatmentPlansQueryHandler;
use App\Modules\Treatment\Application\Handlers\SearchTreatmentPlansQueryHandler;
use App\Modules\Treatment\Application\Handlers\UpdateTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Queries\GetTreatmentPlanQuery;
use App\Modules\Treatment\Application\Queries\ListTreatmentPlansQuery;
use App\Modules\Treatment\Application\Queries\SearchTreatmentPlansQuery;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentPlanStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TreatmentPlanController
{
    public function create(Request $request, CreateTreatmentPlanCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $plan = $handler->handle(new CreateTreatmentPlanCommand($validated));

        return response()->json([
            'status' => 'treatment_plan_created',
            'data' => $plan->toArray(),
        ], 201);
    }

    public function delete(string $planId, DeleteTreatmentPlanCommandHandler $handler): JsonResponse
    {
        $plan = $handler->handle(new DeleteTreatmentPlanCommand($planId));

        return response()->json([
            'status' => 'treatment_plan_deleted',
            'data' => $plan->toArray(),
        ]);
    }

    public function list(ListTreatmentPlansQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($plan): array => $plan->toArray(),
                $handler->handle(new ListTreatmentPlansQuery),
            ),
        ]);
    }

    public function search(Request $request, SearchTreatmentPlansQueryHandler $handler): JsonResponse
    {
        $criteria = $this->searchCriteria($request, 25, 100);

        return response()->json([
            'data' => array_map(
                static fn ($plan): array => $plan->toArray(),
                $handler->handle(new SearchTreatmentPlansQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $planId, GetTreatmentPlanQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetTreatmentPlanQuery($planId))->toArray(),
        ]);
    }

    public function update(string $planId, Request $request, UpdateTreatmentPlanCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $plan = $handler->handle(new UpdateTreatmentPlanCommand($planId, $validated));

        return response()->json([
            'status' => 'treatment_plan_updated',
            'data' => $plan->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'provider_id' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'goals' => ['nullable', 'string', 'max:5000'],
            'planned_start_date' => ['nullable', 'date_format:Y-m-d'],
            'planned_end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:planned_start_date'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'patient_id' => ['sometimes', 'filled', 'uuid'],
            'provider_id' => ['sometimes', 'filled', 'uuid'],
            'title' => ['sometimes', 'filled', 'string', 'max:255'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'goals' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'planned_start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'planned_end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:planned_start_date'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function searchRules(int $maxLimit): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:'.implode(',', TreatmentPlanStatus::all())],
            'patient_id' => ['nullable', 'uuid'],
            'provider_id' => ['nullable', 'uuid'],
            'planned_from' => ['nullable', 'date_format:Y-m-d'],
            'planned_to' => ['nullable', 'date_format:Y-m-d'],
            'created_from' => ['nullable', 'date_format:Y-m-d'],
            'created_to' => ['nullable', 'date_format:Y-m-d'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
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

        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::withMessages([
                $errorKey => ['The end date must be on or after the start date.'],
            ]);
        }
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

    private function searchCriteria(Request $request, int $defaultLimit, int $maxLimit): TreatmentPlanSearchCriteria
    {
        $validated = $request->validate($this->searchRules($maxLimit));
        /** @var array<string, mixed> $validated */

        return $this->searchCriteriaFromValidated($validated, $defaultLimit);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function searchCriteriaFromValidated(array $validated, int $defaultLimit): TreatmentPlanSearchCriteria
    {
        $this->assertDateRange($validated, 'planned_from', 'planned_to', 'planned_date');
        $this->assertDateRange($validated, 'created_from', 'created_to', 'created_at');

        return new TreatmentPlanSearchCriteria(
            query: $this->stringValue($validated, 'q'),
            status: $this->stringValue($validated, 'status'),
            patientId: $this->stringValue($validated, 'patient_id'),
            providerId: $this->stringValue($validated, 'provider_id'),
            plannedFrom: $this->stringValue($validated, 'planned_from'),
            plannedTo: $this->stringValue($validated, 'planned_to'),
            createdFrom: $this->stringValue($validated, 'created_from'),
            createdTo: $this->stringValue($validated, 'created_to'),
            limit: $this->integerValue($validated, 'limit', $defaultLimit),
        );
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
