<?php

namespace App\Modules\Insurance\Presentation\Http\Controllers;

use App\Modules\Insurance\Application\Commands\CreateInsuranceRuleCommand;
use App\Modules\Insurance\Application\Commands\DeleteInsuranceRuleCommand;
use App\Modules\Insurance\Application\Commands\UpdateInsuranceRuleCommand;
use App\Modules\Insurance\Application\Handlers\CreateInsuranceRuleCommandHandler;
use App\Modules\Insurance\Application\Handlers\DeleteInsuranceRuleCommandHandler;
use App\Modules\Insurance\Application\Handlers\ListInsuranceRulesQueryHandler;
use App\Modules\Insurance\Application\Handlers\UpdateInsuranceRuleCommandHandler;
use App\Modules\Insurance\Application\Queries\ListInsuranceRulesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class InsuranceRuleController
{
    public function create(Request $request, CreateInsuranceRuleCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $rule = $handler->handle(new CreateInsuranceRuleCommand($validated));

        return response()->json([
            'status' => 'insurance_rule_created',
            'data' => $rule->toArray(),
        ], 201);
    }

    public function delete(string $ruleId, DeleteInsuranceRuleCommandHandler $handler): JsonResponse
    {
        $rule = $handler->handle(new DeleteInsuranceRuleCommand($ruleId));

        return response()->json([
            'status' => 'insurance_rule_deleted',
            'data' => $rule->toArray(),
        ]);
    }

    public function list(Request $request, ListInsuranceRulesQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->listRules());
        /** @var array<string, mixed> $validated */
        $query = new ListInsuranceRulesQuery(
            query: $this->stringValue($validated, 'q'),
            payerId: $this->stringValue($validated, 'payer_id'),
            serviceCategory: $this->stringValue($validated, 'service_category'),
            isActive: array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null,
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 25,
        );

        return response()->json([
            'data' => array_map(
                static fn ($rule): array => $rule->toArray(),
                $handler->handle($query),
            ),
            'meta' => [
                'filters' => [
                    'q' => $query->query,
                    'payer_id' => $query->payerId,
                    'service_category' => $query->serviceCategory,
                    'is_active' => $query->isActive,
                    'limit' => $query->limit,
                ],
            ],
        ]);
    }

    public function update(string $ruleId, Request $request, UpdateInsuranceRuleCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $rule = $handler->handle(new UpdateInsuranceRuleCommand($ruleId, $validated));

        return response()->json([
            'status' => 'insurance_rule_updated',
            'data' => $rule->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'payer_id' => ['required', 'uuid'],
            'code' => ['required', 'string', 'max:48'],
            'name' => ['required', 'string', 'max:160'],
            'service_category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'requires_primary_policy' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'max_claim_amount' => ['sometimes', 'nullable', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/'],
            'submission_window_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function listRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'payer_id' => ['nullable', 'uuid'],
            'service_category' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'payer_id' => ['sometimes', 'filled', 'uuid'],
            'code' => ['sometimes', 'filled', 'string', 'max:48'],
            'name' => ['sometimes', 'filled', 'string', 'max:160'],
            'service_category' => ['sometimes', 'nullable', 'string', 'max:120'],
            'requires_primary_policy' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'max_claim_amount' => ['sometimes', 'nullable', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/'],
            'submission_window_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['sometimes', 'boolean'],
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
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }
}
