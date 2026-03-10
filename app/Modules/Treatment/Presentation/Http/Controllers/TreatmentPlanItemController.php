<?php

namespace App\Modules\Treatment\Presentation\Http\Controllers;

use App\Modules\Treatment\Application\Commands\AddTreatmentItemCommand;
use App\Modules\Treatment\Application\Commands\RemoveTreatmentItemCommand;
use App\Modules\Treatment\Application\Commands\UpdateTreatmentItemCommand;
use App\Modules\Treatment\Application\Handlers\AddTreatmentItemCommandHandler;
use App\Modules\Treatment\Application\Handlers\ListTreatmentItemsQueryHandler;
use App\Modules\Treatment\Application\Handlers\RemoveTreatmentItemCommandHandler;
use App\Modules\Treatment\Application\Handlers\UpdateTreatmentItemCommandHandler;
use App\Modules\Treatment\Application\Queries\ListTreatmentItemsQuery;
use App\Modules\Treatment\Domain\TreatmentPlans\TreatmentItemType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TreatmentPlanItemController
{
    public function create(string $planId, Request $request, AddTreatmentItemCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $item = $handler->handle(new AddTreatmentItemCommand($planId, $validated));

        return response()->json([
            'status' => 'treatment_plan_item_created',
            'data' => $item->toArray(),
        ], 201);
    }

    public function delete(
        string $planId,
        string $itemId,
        RemoveTreatmentItemCommandHandler $handler,
    ): JsonResponse {
        $item = $handler->handle(new RemoveTreatmentItemCommand($planId, $itemId));

        return response()->json([
            'status' => 'treatment_plan_item_deleted',
            'data' => $item->toArray(),
        ]);
    }

    public function list(string $planId, ListTreatmentItemsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($item): array => $item->toArray(),
                $handler->handle(new ListTreatmentItemsQuery($planId)),
            ),
        ]);
    }

    public function update(
        string $planId,
        string $itemId,
        Request $request,
        UpdateTreatmentItemCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $item = $handler->handle(new UpdateTreatmentItemCommand($planId, $itemId, $validated));

        return response()->json([
            'status' => 'treatment_plan_item_updated',
            'data' => $item->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'item_type' => ['required', 'string', 'in:'.implode(',', TreatmentItemType::all())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
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
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'item_type' => ['sometimes', 'filled', 'string', 'in:'.implode(',', TreatmentItemType::all())],
            'title' => ['sometimes', 'filled', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
