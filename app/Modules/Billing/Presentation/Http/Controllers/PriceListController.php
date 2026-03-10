<?php

namespace App\Modules\Billing\Presentation\Http\Controllers;

use App\Modules\Billing\Application\Commands\CreatePriceListCommand;
use App\Modules\Billing\Application\Commands\DeletePriceListCommand;
use App\Modules\Billing\Application\Commands\SetPriceListItemsCommand;
use App\Modules\Billing\Application\Commands\UpdatePriceListCommand;
use App\Modules\Billing\Application\Data\PriceListListCriteria;
use App\Modules\Billing\Application\Handlers\CreatePriceListCommandHandler;
use App\Modules\Billing\Application\Handlers\DeletePriceListCommandHandler;
use App\Modules\Billing\Application\Handlers\GetPriceListQueryHandler;
use App\Modules\Billing\Application\Handlers\ListPriceListsQueryHandler;
use App\Modules\Billing\Application\Handlers\SetPriceListItemsCommandHandler;
use App\Modules\Billing\Application\Handlers\UpdatePriceListCommandHandler;
use App\Modules\Billing\Application\Queries\GetPriceListQuery;
use App\Modules\Billing\Application\Queries\ListPriceListsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PriceListController
{
    public function create(Request $request, CreatePriceListCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->createRules());
        /** @var array<string, mixed> $validated */
        $priceList = $handler->handle(new CreatePriceListCommand($validated));

        return response()->json([
            'status' => 'price_list_created',
            'data' => $priceList->toArray(),
        ], 201);
    }

    public function delete(string $priceListId, DeletePriceListCommandHandler $handler): JsonResponse
    {
        $priceList = $handler->handle(new DeletePriceListCommand($priceListId));

        return response()->json([
            'status' => 'price_list_deleted',
            'data' => $priceList->toArray(),
        ]);
    }

    public function list(Request $request, ListPriceListsQueryHandler $handler): JsonResponse
    {
        $criteria = $this->criteria($request);

        return response()->json([
            'data' => array_map(
                static fn ($priceList): array => $priceList->toArray(),
                $handler->handle(new ListPriceListsQuery($criteria)),
            ),
            'meta' => [
                'filters' => $criteria->toArray(),
            ],
        ]);
    }

    public function setItems(string $priceListId, Request $request, SetPriceListItemsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['present', 'array', 'max:500'],
            'items.*.service_id' => ['required', 'uuid', 'distinct'],
            'items.*.amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
        ]);
        /** @var array<string, mixed> $validated */
        /** @var mixed $rawItems */
        $rawItems = $validated['items'] ?? [];
        /** @var list<array<string, mixed>> $items */
        $items = is_array($rawItems) ? array_values($rawItems) : [];
        $priceList = $handler->handle(new SetPriceListItemsCommand($priceListId, $items));

        return response()->json([
            'status' => 'price_list_items_replaced',
            'data' => $priceList->toArray(),
        ]);
    }

    public function show(string $priceListId, GetPriceListQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => $handler->handle(new GetPriceListQuery($priceListId))->toArray(),
        ]);
    }

    public function update(string $priceListId, Request $request, UpdatePriceListCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate($this->updateRules());
        /** @var array<string, mixed> $validated */
        $this->assertNonEmptyPatch($validated);
        $priceList = $handler->handle(new UpdatePriceListCommand($priceListId, $validated));

        return response()->json([
            'status' => 'price_list_updated',
            'data' => $priceList->toArray(),
        ]);
    }

    private function criteria(Request $request): PriceListListCriteria
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'active_on' => ['nullable', 'date_format:Y-m-d'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        /** @var array<string, mixed> $validated */
        /** @var mixed $activeOnValue */
        $activeOnValue = $validated['active_on'] ?? null;

        return new PriceListListCriteria(
            query: $this->stringValue($validated, 'q'),
            isActive: array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : null,
            isDefault: array_key_exists('is_default', $validated) ? (bool) $validated['is_default'] : null,
            activeOn: is_string($activeOnValue)
                ? CarbonImmutable::createFromFormat('Y-m-d', $activeOnValue)
                : null,
            limit: array_key_exists('limit', $validated) && is_numeric($validated['limit'])
                ? (int) $validated['limit']
                : 25,
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function createRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'effective_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'effective_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function updateRules(): array
    {
        return [
            'code' => ['sometimes', 'filled', 'string', 'max:120'],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'currency' => ['sometimes', 'filled', 'string', 'size:3', 'alpha'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'effective_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'effective_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
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
    private function stringValue(array $validated, string $key): ?string
    {
        return array_key_exists($key, $validated) && is_string($validated[$key]) && $validated[$key] !== ''
            ? $validated[$key]
            : null;
    }
}
