<?php

namespace App\Modules\Observability\Presentation\Http\Controllers;

use App\Modules\Observability\Application\Commands\SetFeatureFlagsCommand;
use App\Modules\Observability\Application\Data\FeatureFlagData;
use App\Modules\Observability\Application\Handlers\ListFeatureFlagsQueryHandler;
use App\Modules\Observability\Application\Handlers\SetFeatureFlagsCommandHandler;
use App\Modules\Observability\Application\Queries\ListFeatureFlagsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FeatureFlagController
{
    public function list(ListFeatureFlagsQueryHandler $handler): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                static fn (FeatureFlagData $flag): array => $flag->toArray(),
                $handler->handle(new ListFeatureFlagsQuery),
            ),
        ]);
    }

    public function update(Request $request, SetFeatureFlagsCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'flags' => ['required', 'array', 'min:1'],
            'flags.*.key' => ['required', 'string', 'max:64'],
            'flags.*.enabled' => ['required', 'boolean'],
        ]);
        /** @var array<string, mixed> $validated */

        return response()->json([
            'status' => 'feature_flags_updated',
            'data' => array_map(
                static fn (FeatureFlagData $flag): array => $flag->toArray(),
                $handler->handle(new SetFeatureFlagsCommand($this->featureFlagsPayload($validated))),
            ),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, bool>
     */
    private function featureFlagsPayload(array $validated): array
    {
        /** @var array<string, bool> $payload */
        $payload = [];
        $items = $validated['flags'] ?? [];

        if (! is_array($items)) {
            return $payload;
        }

        /** @var list<array<string, mixed>> $items */
        $items = array_values(array_filter(
            $items,
            static fn (mixed $flag): bool => is_array($flag),
        ));

        foreach ($items as $flag) {
            $key = $flag['key'] ?? null;
            $enabled = $flag['enabled'] ?? null;

            if (! is_string($key) || ! is_bool($enabled)) {
                continue;
            }

            $payload[$key] = $enabled;
        }

        return $payload;
    }
}
