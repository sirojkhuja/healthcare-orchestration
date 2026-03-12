<?php

namespace App\Modules\Provider\Presentation\Http\Controllers;

use App\Modules\Provider\Application\Commands\CreateProviderCommand;
use App\Modules\Provider\Application\Commands\DeleteProviderCommand;
use App\Modules\Provider\Application\Commands\UpdateProviderCommand;
use App\Modules\Provider\Application\Data\ProviderSearchCriteria;
use App\Modules\Provider\Application\Handlers\CreateProviderCommandHandler;
use App\Modules\Provider\Application\Handlers\DeleteProviderCommandHandler;
use App\Modules\Provider\Application\Handlers\GetProviderQueryHandler;
use App\Modules\Provider\Application\Handlers\ListProvidersQueryHandler;
use App\Modules\Provider\Application\Handlers\SearchProvidersQueryHandler;
use App\Modules\Provider\Application\Handlers\UpdateProviderCommandHandler;
use App\Modules\Provider\Application\Queries\GetProviderQuery;
use App\Modules\Provider\Application\Queries\ListProvidersQuery;
use App\Modules\Provider\Application\Queries\SearchProvidersQuery;
use App\Modules\Provider\Domain\Providers\ProviderType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ProviderController
{
    public function create(Request $request, CreateProviderCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $provider = $handler->handle(new CreateProviderCommand($validated));

        return response()->json([
            'status' => 'provider_created',
            'data' => $provider->toArray(),
        ], 201);
    }

    public function delete(string $providerId, DeleteProviderCommandHandler $handler): JsonResponse
    {
        $provider = $handler->handle(new DeleteProviderCommand($providerId));

        return response()->json([
            'status' => 'provider_deleted',
            'data' => $provider->toArray(),
        ]);
    }

    public function list(ListProvidersQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn ($provider): array => $provider->toArray(),
                $handler->handle(new ListProvidersQuery),
            ),
        ]);
    }

    public function search(Request $request, SearchProvidersQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->searchRules());
        /** @var array<string, mixed> $validated */
        $criteria = new ProviderSearchCriteria(
            query: $this->nullableString($validated, 'q'),
            providerType: $this->nullableString($validated, 'provider_type'),
            clinicId: $this->nullableString($validated, 'clinic_id'),
            hasEmail: $this->nullableBoolean($validated, 'has_email'),
            hasPhone: $this->nullableBoolean($validated, 'has_phone'),
            limit: $this->integerValue($validated, 'limit', 25),
        );

        return response()->json([
            'data' => array_map(
                static fn ($provider): array => $provider->toArray(),
                $handler->handle(new SearchProvidersQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function show(string $providerId, GetProviderQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetProviderQuery($providerId))->toArray(),
        ]);
    }

    public function update(string $providerId, Request $request, UpdateProviderCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $provider = $handler->handle(new UpdateProviderCommand($providerId, $validated));

        return response()->json([
            'status' => 'provider_updated',
            'data' => $provider->toArray(),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'middle_name' => ['nullable', 'string', 'max:120'],
            'preferred_name' => ['nullable', 'string', 'max:120'],
            'provider_type' => ['required', 'string', 'in:'.implode(',', ProviderType::all())],
            'email' => ['nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'clinic_id' => ['nullable', 'uuid'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'first_name' => ['sometimes', 'filled', 'string', 'max:120'],
            'last_name' => ['sometimes', 'filled', 'string', 'max:120'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferred_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'provider_type' => ['sometimes', 'filled', 'string', 'in:'.implode(',', ProviderType::all())],
            'email' => ['sometimes', 'nullable', 'email:rfc,dns', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'clinic_id' => ['sometimes', 'nullable', 'uuid'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function searchRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'provider_type' => ['nullable', 'string', 'in:'.implode(',', ProviderType::all())],
            'clinic_id' => ['nullable', 'uuid'],
            'has_email' => ['nullable', 'string', 'in:true,false,1,0'],
            'has_phone' => ['nullable', 'string', 'in:true,false,1,0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
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
     * @param  array<array-key, mixed>  $validated
     */
    private function integerValue(array $validated, string $key, int $default): int
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableBoolean(array $validated, string $key): ?bool
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return match ($value) {
            true, 'true', '1' => true,
            false, 'false', '0' => false,
            default => null,
        };
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
