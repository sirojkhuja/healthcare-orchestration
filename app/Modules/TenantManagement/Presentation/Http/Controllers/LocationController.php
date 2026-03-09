<?php

namespace App\Modules\TenantManagement\Presentation\Http\Controllers;

use App\Modules\TenantManagement\Application\Handlers\ListCitiesQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\ListDistrictsQueryHandler;
use App\Modules\TenantManagement\Application\Handlers\SearchLocationsQueryHandler;
use App\Modules\TenantManagement\Application\Queries\ListCitiesQuery;
use App\Modules\TenantManagement\Application\Queries\ListDistrictsQuery;
use App\Modules\TenantManagement\Application\Queries\SearchLocationsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LocationController
{
    public function cities(Request $request, ListCitiesQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var array<string, mixed> $validated */
        $items = [];

        foreach ($handler->handle(new ListCitiesQuery($this->nullableString($validated, 'q'))) as $city) {
            $items[] = $city->toArray();
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function districts(Request $request, ListDistrictsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'city_code' => ['required', 'string', 'max:64'],
        ]);
        /** @var array<string, mixed> $validated */
        $items = [];

        foreach ($handler->handle(new ListDistrictsQuery($this->validatedString($validated, 'city_code'))) as $district) {
            $items[] = $district->toArray();
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    public function search(Request $request, SearchLocationsQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);
        /** @var array<string, mixed> $validated */
        $items = [];

        foreach ($handler->handle(new SearchLocationsQuery($this->validatedString($validated, 'q'))) as $result) {
            $items[] = $result->toArray();
        }

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $validated
     */
    private function validatedString(array $validated, string $key): string
    {
        /** @var mixed $value */
        $value = $validated[$key] ?? null;

        return is_string($value) ? $value : '';
    }
}
