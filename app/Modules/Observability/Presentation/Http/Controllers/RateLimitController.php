<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Commands\UpdateRateLimitsCommand;
use App\Modules\Observability\Application\Data\RateLimitData;
use App\Modules\Observability\Application\Handlers\GetRateLimitsQueryHandler;
use App\Modules\Observability\Application\Handlers\UpdateRateLimitsCommandHandler;
use App\Modules\Observability\Application\Queries\GetRateLimitsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RateLimitController
{
    public function show(GetRateLimitsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (RateLimitData $limit): array => $limit->toArray(),
                $handler->handle(new GetRateLimitsQuery),
            ),
        ]);
    }

    public function update(Request $request, UpdateRateLimitsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'limits' => ['required', 'array', 'min:1'],
            'limits.*.bucket_key' => ['required', 'string', 'max:96'],
            'limits.*.requests_per_minute' => ['required', 'integer', 'min:1'],
            'limits.*.burst' => ['required', 'integer', 'min:1'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'rate_limits_updated',
            'data' => array_map(
                static fn (RateLimitData $limit): array => $limit->toArray(),
                $handler->handle(new UpdateRateLimitsCommand($this->limitsPayload($validated))),
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, array{requests_per_minute: int, burst: int}>
     */
    private function limitsPayload(array $validated): array
    {
        /** @var array<string, array{requests_per_minute: int, burst: int}> $payload */
        $payload = [];
        $items = $validated['limits'] ?? [];

        if (! is_array($items)) {
            return $payload;
        }

        /** @var list<array<string, mixed>> $items */
        $items = array_values(array_filter(
            $items,
            static fn (mixed $limit): bool => is_array($limit),
        ));

        foreach ($items as $limit) {
            $bucketKey = $limit['bucket_key'] ?? null;
            $requestsPerMinute = $limit['requests_per_minute'] ?? null;
            $burst = $limit['burst'] ?? null;

            if (! is_string($bucketKey) || ! is_int($requestsPerMinute) || ! is_int($burst)) {
                continue;
            }

            $payload[$bucketKey] = [
                'requests_per_minute' => $requestsPerMinute,
                'burst' => $burst,
            ];
        }

        return $payload;
    }
}
