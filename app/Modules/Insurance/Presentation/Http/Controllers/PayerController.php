<?php

namespace App\Modules\Insurance\Presentation\Http\Controllers;

use App\Modules\Insurance\Application\Commands\CreatePayerCommand;
use App\Modules\Insurance\Application\Commands\DeletePayerCommand;
use App\Modules\Insurance\Application\Commands\UpdatePayerCommand;
use App\Modules\Insurance\Application\Handlers\CreatePayerCommandHandler;
use App\Modules\Insurance\Application\Handlers\DeletePayerCommandHandler;
use App\Modules\Insurance\Application\Handlers\ListPayersQueryHandler;
use App\Modules\Insurance\Application\Handlers\UpdatePayerCommandHandler;
use App\Modules\Insurance\Application\Queries\ListPayersQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PayerController
{
    public function create(Request $request, CreatePayerCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $payer = $handler->handle(new CreatePayerCommand($validated));

        return response()->json([
            'status' => 'payer_created',
            'data' => $payer->toArray(),
        ], 201);
    }

    public function delete(string $payerId, DeletePayerCommandHandler $handler): JsonResponse
    {
        $payer = $handler->handle(new DeletePayerCommand($payerId));

        return response()->json([
            'status' => 'payer_deleted',
            'data' => $payer->toArray(),
        ]);
    }

    public function list(Request $request, ListPayersQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->listRules());
        /** @var array<string, mixed> $validated */
        $query = new ListPayersQuery(
            query: $this->stringValue($validated, 'q'),
            insuranceCode: $this->stringValue($validated, 'insurance_code'),
            isActive: array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null,
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 25,
        );

        return response()->json([
            'data' => array_map(
                static fn ($payer): array => $payer->toArray(),
                $handler->handle($query),
            ),
            'meta' => [
                'filters' => [
                    'q' => $query->query,
                    'insurance_code' => $query->insuranceCode,
                    'is_active' => $query->isActive,
                    'limit' => $query->limit,
                ],
            ],
        ]);
    }

    public function update(string $payerId, Request $request, UpdatePayerCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $payer = $handler->handle(new UpdatePayerCommand($payerId, $validated));

        return response()->json([
            'status' => 'payer_updated',
            'data' => $payer->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:48'],
            'name' => ['required', 'string', 'max:160'],
            'insurance_code' => ['required', 'string', 'max:64'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'contact_email' => ['sometimes', 'nullable', 'email:rfc', 'max:190'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
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
            'insurance_code' => ['nullable', 'string', 'max:64'],
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
            'code' => ['sometimes', 'filled', 'string', 'max:48'],
            'name' => ['sometimes', 'filled', 'string', 'max:160'],
            'insurance_code' => ['sometimes', 'filled', 'string', 'max:64'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:160'],
            'contact_email' => ['sometimes', 'nullable', 'email:rfc', 'max:190'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:40'],
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
