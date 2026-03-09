<?php

namespace App\Modules\Scheduling\Presentation\Http\Controllers;

use App\Modules\Scheduling\Application\Commands\CreateAvailabilityRuleCommand;
use App\Modules\Scheduling\Application\Commands\DeleteAvailabilityRuleCommand;
use App\Modules\Scheduling\Application\Commands\RebuildAvailabilityCacheCommand;
use App\Modules\Scheduling\Application\Commands\UpdateAvailabilityRuleCommand;
use App\Modules\Scheduling\Application\Handlers\CreateAvailabilityRuleCommandHandler;
use App\Modules\Scheduling\Application\Handlers\DeleteAvailabilityRuleCommandHandler;
use App\Modules\Scheduling\Application\Handlers\GetAvailabilitySlotsQueryHandler;
use App\Modules\Scheduling\Application\Handlers\ListAvailabilityRulesQueryHandler;
use App\Modules\Scheduling\Application\Handlers\RebuildAvailabilityCacheCommandHandler;
use App\Modules\Scheduling\Application\Handlers\UpdateAvailabilityRuleCommandHandler;
use App\Modules\Scheduling\Application\Queries\GetAvailabilitySlotsQuery;
use App\Modules\Scheduling\Application\Queries\ListAvailabilityRulesQuery;
use App\Modules\Scheduling\Domain\Availability\AvailabilityScopeType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityType;
use App\Modules\Scheduling\Domain\Availability\AvailabilityWeekday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AvailabilityController
{
    public function create(
        string $providerId,
        Request $request,
        CreateAvailabilityRuleCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->createRuleRules());
        /** @var array<string, mixed> $validated */
        $rule = $handler->handle(new CreateAvailabilityRuleCommand($providerId, $validated));

        return response()->json([
            'status' => 'availability_rule_created',
            'data' => $rule->toArray(),
        ], 201);
    }

    public function delete(
        string $providerId,
        string $ruleId,
        DeleteAvailabilityRuleCommandHandler $handler,
    ): JsonResponse {
        $rule = $handler->handle(new DeleteAvailabilityRuleCommand($providerId, $ruleId));

        return response()->json([
            'status' => 'availability_rule_deleted',
            'data' => $rule->toArray(),
        ]);
    }

    public function list(string $providerId, ListAvailabilityRulesQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($rule): array => $rule->toArray(),
                $handler->handle(new ListAvailabilityRulesQuery($providerId)),
            ),
        ]);
    }

    public function rebuild(
        string $providerId,
        Request $request,
        RebuildAvailabilityCacheCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->slotWindowRules());
        /** @var array{date_from: string, date_to: string, limit?: int} $validated */
        $result = $handler->handle(new RebuildAvailabilityCacheCommand(
            providerId: $providerId,
            dateFrom: $validated['date_from'],
            dateTo: $validated['date_to'],
            limit: $validated['limit'] ?? null,
        ));

        return response()->json([
            'status' => 'availability_cache_rebuilt',
            'data' => $result->toArray(),
        ]);
    }

    public function slots(
        string $providerId,
        Request $request,
        GetAvailabilitySlotsQueryHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->slotWindowRules());
        /** @var array{date_from: string, date_to: string, limit?: int} $validated */
        $result = $handler->handle(new GetAvailabilitySlotsQuery(
            providerId: $providerId,
            dateFrom: $validated['date_from'],
            dateTo: $validated['date_to'],
            limit: $validated['limit'] ?? null,
        ));

        return response()->json([
            'data' => $result->toArray(),
        ]);
    }

    public function update(
        string $providerId,
        string $ruleId,
        Request $request,
        UpdateAvailabilityRuleCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate($this->updateRuleRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $rule = $handler->handle(new UpdateAvailabilityRuleCommand($providerId, $ruleId, $validated));

        return response()->json([
            'status' => 'availability_rule_updated',
            'data' => $rule->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRuleRules(): array
    {
        return [
            'scope_type' => ['required', 'string', 'in:'.implode(',', AvailabilityScopeType::all())],
            'availability_type' => ['required', 'string', 'in:'.implode(',', AvailabilityType::all())],
            'weekday' => ['nullable', 'string', 'in:'.implode(',', AvailabilityWeekday::all())],
            'specific_date' => ['nullable', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function slotWindowRules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
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
    private function updateRuleRules(): array
    {
        return [
            'scope_type' => ['sometimes', 'string', 'in:'.implode(',', AvailabilityScopeType::all())],
            'availability_type' => ['sometimes', 'string', 'in:'.implode(',', AvailabilityType::all())],
            'weekday' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', AvailabilityWeekday::all())],
            'specific_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
