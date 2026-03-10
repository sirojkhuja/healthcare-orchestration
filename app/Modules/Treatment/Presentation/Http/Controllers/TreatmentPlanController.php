<?php

namespace App\Modules\Treatment\Presentation\Http\Controllers;

use App\Modules\Treatment\Application\Commands\CreateTreatmentPlanCommand;
use App\Modules\Treatment\Application\Commands\DeleteTreatmentPlanCommand;
use App\Modules\Treatment\Application\Commands\UpdateTreatmentPlanCommand;
use App\Modules\Treatment\Application\Handlers\CreateTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Handlers\DeleteTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Handlers\GetTreatmentPlanQueryHandler;
use App\Modules\Treatment\Application\Handlers\ListTreatmentPlansQueryHandler;
use App\Modules\Treatment\Application\Handlers\UpdateTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Queries\GetTreatmentPlanQuery;
use App\Modules\Treatment\Application\Queries\ListTreatmentPlansQuery;
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
}
