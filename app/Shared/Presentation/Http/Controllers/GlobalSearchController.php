<?php

namespace App\Shared\Presentation\Http\Controllers;

use App\Shared\Application\Data\GlobalSearchCriteria;
use App\Shared\Application\Handlers\GlobalSearchQueryHandler;
use App\Shared\Application\Queries\GlobalSearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GlobalSearchController
{
    public function search(Request $request, GlobalSearchQueryHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:120'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:patient,provider,appointment,invoice,claim'],
            'limit_per_type' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);
        /** @var array<string, mixed> $validated */
        /** @var string $query */
        $query = $validated['q'];
        /** @var list<string> $types */
        $types = [];

        if (isset($validated['types']) && is_array($validated['types'])) {
            /** @var list<string> $types */
            $types = $validated['types'];
        }

        $result = $handler->handle(new GlobalSearchQuery(new GlobalSearchCriteria(
            query: $query,
            types: $types,
            limitPerType: $this->integerValue($validated['limit_per_type'] ?? null, 5),
        )));

        return response()->json($result->toArray());
    }

    private function integerValue(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }
}
